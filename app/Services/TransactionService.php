<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\Account;
use App\Models\ApiLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TransactionService
{
    protected $maxRetries = 3;
    protected $retryDelay = 1; // seconds

    /**
     * Create a new transaction and update account balance
     *
     * @param array $data
     * @return Transaction
     * @throws \Exception If transaction would cause negative balance
     */
    public function createTransaction(array $data)
    {
        return $this->executeWithRetry(function () use ($data) {
            return DB::transaction(function () use ($data) {
                // Check if this transaction would cause a negative balance
                if ($data['type'] === 'expense') {
                    $account = Account::findOrFail($data['account_id']);
                    $currentBalance = $account->getCurrentBalanceAttribute();
                    if (($currentBalance - $data['amount']) < 0) {
                        throw new \Exception('Transaction would cause negative balance. Current balance: ' . $currentBalance);
                    }
                }

                // Handle category relationship
                $categoryId = null;
                if (isset($data['category_id'])) {
                    $category = \App\Models\Category::where('id', $data['category_id'])
                        ->orWhere('uuid', $data['category_id'])
                        ->first();
                    if ($category) {
                        $categoryId = $category->id;
                    }
                }

                // Prepare transaction data
                $transactionData = array_merge($data, [
                    'category_id' => $categoryId
                ]);

                // Create the transaction
                $transaction = Transaction::create($transactionData);

                // Log the successful operation
                $this->logApiCall(
                    'POST', 
                    'create_transaction', 
                    $data, 
                    ['transaction_id' => $transaction->uuid], 
                    201, 
                    ApiLog::STATUS_SUCCESS
                );

                return $transaction;
            });
        });
    }

    /**
     * Update a transaction
     *
     * @param Transaction $transaction
     * @param array $data
     * @return Transaction
     */
    public function updateTransaction(Transaction $transaction, array $data)
    {
        return $this->executeWithRetry(function () use ($transaction, $data) {
            return DB::transaction(function () use ($transaction, $data) {
                $oldAccountId = $transaction->account_id;
                
                // Update the transaction
                $transaction->update($data);

                // Log the successful operation
                $this->logApiCall(
                    'PUT', 
                    'update_transaction', 
                    $data, 
                    ['transaction_id' => $transaction->uuid], 
                    200, 
                    ApiLog::STATUS_SUCCESS
                );

                return $transaction;
            });
        });
    }

    /**
     * Delete a transaction
     *
     * @param Transaction $transaction
     * @return bool
     */
    public function deleteTransaction(Transaction $transaction)
    {
        return $this->executeWithRetry(function () use ($transaction) {
            return DB::transaction(function () use ($transaction) {
                $transactionId = $transaction->uuid;
                
                // Delete the transaction
                $transaction->delete();

                // Log the successful operation
                $this->logApiCall(
                    'DELETE', 
                    'delete_transaction', 
                    ['transaction_id' => $transactionId], 
                    ['success' => true], 
                    200, 
                    ApiLog::STATUS_SUCCESS
                );

                return true;
            });
        });
    }

    /**
     * Get transaction statistics
     *
     * @param string $startDate
     * @param string $endDate
     * @param int|null $accountId
     * @return array
     */
    public function getStatistics(string $startDate, string $endDate, ?int $accountId = null)
    {
        $query = Transaction::whereBetween('transaction_date', [$startDate, $endDate]);
        
        if ($accountId) {
            $query->where('account_id', $accountId);
        }
        
        $income = (clone $query)->income()->sum('amount');
        $expense = (clone $query)->expense()->sum('amount');
        
        $categories = (clone $query)
            ->selectRaw('category, type, SUM(amount) as total')
            ->groupBy('category', 'type')
            ->get();
        
        $this->logApiCall(
            'GET', 
            'transaction_statistics', 
            [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'account_id' => $accountId
            ], 
            [
                'income' => $income,
                'expense' => $expense,
                'net' => $income - $expense
            ], 
            200, 
            ApiLog::STATUS_SUCCESS
        );
        
        return [
            'total_income' => $income,
            'total_expense' => $expense,
            'net' => $income - $expense,
            'categories' => $categories
        ];
    }

    /**
     * Execute a function with retry mechanism
     *
     * @param callable $callback
     * @return mixed
     * @throws \Exception
     */
    protected function executeWithRetry(callable $callback)
    {
        $attempts = 0;
        $lastException = null;

        while ($attempts < $this->maxRetries) {
            try {
                return $callback();
            } catch (\Exception $e) {
                $attempts++;
                $lastException = $e;
                
                // Log the failure
                $this->logApiCall(
                    'ERROR', 
                    'retry_attempt', 
                    [
                        'attempt' => $attempts,
                        'max_retries' => $this->maxRetries
                    ], 
                    [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ], 
                    500, 
                    ApiLog::STATUS_WARNING
                );
                
                if ($attempts < $this->maxRetries) {
                    sleep($this->retryDelay);
                }
            }
        }

        // Log final failure
        $this->logApiCall(
            'ERROR', 
            'max_retries_exceeded', 
            [
                'max_retries' => $this->maxRetries
            ], 
            [
                'error' => $lastException->getMessage(),
                'trace' => $lastException->getTraceAsString()
            ], 
            500, 
            ApiLog::STATUS_ERROR
        );

        throw $lastException;
    }

    /**
     * Log API call details
     *
     * @param string $method HTTP method or operation name
     * @param string $endpoint API endpoint or operation description
     * @param array $requestData Request data
     * @param array $responseData Response data
     * @param int $statusCode HTTP status code
     * @param string $status Status (success, error, warning)
     * @return ApiLog
     */
    protected function logApiCall(string $method, string $endpoint, array $requestData, array $responseData, int $statusCode, string $status)
    {
        // Sanitize data to remove sensitive information
        $sanitizedRequest = $this->sanitizeData($requestData);
        $sanitizedResponse = $this->sanitizeData($responseData);
          return ApiLog::create([
            'request_id' => (string) Str::uuid(),
            'method' => strtoupper($method),
            'url' => $endpoint,
            'ip_address' => request()->ip() ?? '127.0.0.1',
            'user_agent' => request()->userAgent() ?? 'System',
            'request_payload' => $sanitizedRequest,
            'response_code' => $statusCode,
            'response_body' => $sanitizedResponse,
            'user_id' => Auth::check() ? Auth::id() : null,
            'duration' => 0, // You would calculate this in a real implementation
            'status' => $status
        ]);
    }

    /**
     * Sanitize sensitive data
     *
     * @param array $data
     * @return array
     */
    protected function sanitizeData(array $data): array
    {
        $sensitiveFields = ['password', 'token', 'key', 'secret'];
        
        foreach ($data as $key => $value) {
            if (in_array(strtolower($key), $sensitiveFields)) {
                $data[$key] = '[REDACTED]';
            } else if (is_array($value)) {
                $data[$key] = $this->sanitizeData($value);
            }
        }
        
        return $data;
    }
}
