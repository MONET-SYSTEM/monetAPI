<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\Account;
use App\Models\Category;
use App\Models\Transfer;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Http\Resources\TransactionResource;
use App\Http\Resources\CategoryResource;

class TransactionService
{
    protected $maxRetries = 3;
    protected $retryDelay = 1; // seconds

    /**
     * Get a paginated list of transactions with optional filtering.
     *
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getTransactions(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Transaction::query();
        
        // Apply account filter
        if (isset($filters['account_id']) && $filters['account_id']) {
            $query->where('account_id', $filters['account_id']);
        }
        
        // Apply transaction type filter
        if (isset($filters['type']) && $filters['type']) {
            $query->where('type', $filters['type']);
        }
        
        // Apply category filter
        if (isset($filters['category_id']) && $filters['category_id']) {
            // Check if we have a UUID or an ID
            $category = Category::where('uuid', $filters['category_id'])
                ->orWhere('id', $filters['category_id'])
                ->first();
                
            if ($category) {
                $query->where('category_id', $category->id);
            }
        }
        
        // Apply date range filters
        if (isset($filters['start_date']) && isset($filters['end_date'])) {
            $query->whereBetween('transaction_date', [$filters['start_date'], $filters['end_date']]);
        } else if (isset($filters['start_date'])) {
            $query->where('transaction_date', '>=', $filters['start_date']);
        } else if (isset($filters['end_date'])) {
            $query->where('transaction_date', '<=', $filters['end_date']);
        }
        
        // Apply search filter
        if (isset($filters['search']) && $filters['search']) {
            $query->where(function($q) use ($filters) {
                $q->where('description', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('reference', 'like', '%' . $filters['search'] . '%');
            });
        }
        
        // Apply reconciliation status filter
        if (isset($filters['is_reconciled']) && $filters['is_reconciled'] !== null) {
            $query->where('is_reconciled', $filters['is_reconciled']);
        }
        
        // Always include related models for better API responses
        $query->with(['account', 'category']);
        
        // Apply ordering
        $query->orderBy('transaction_date', 'desc');
        
        return $query->paginate($perPage);
    }
      /**
     * Find a transaction by UUID.
     *
     * @param string $uuid
     * @return Transaction|null
     */
    public function findByUuid(string $uuid): ?Transaction
    {
        return Transaction::with(['account', 'category'])
            ->where('uuid', $uuid)
            ->first();
    }

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

                // Handle category relationship - this will check both UUID and ID
                $categoryId = null;
                if (isset($data['category_id'])) {
                    $category = Category::where('uuid', $data['category_id'])
                        ->orWhere('id', $data['category_id'])
                        ->first();
                    
                    if ($category) {
                        $categoryId = $category->id;
                    }
                }

                // Prepare transaction data with UUID
                $transactionData = [
                    'uuid' => $data['uuid'] ?? (string) Str::uuid(),
                    'account_id' => $data['account_id'],
                    'category_id' => $categoryId,
                    'amount' => $data['amount'],
                    'type' => $data['type'],
                    'description' => $data['description'] ?? null,
                    'transaction_date' => $data['transaction_date'],
                    'is_reconciled' => $data['is_reconciled'] ?? false,
                    'reference' => $data['reference'] ?? null,
                ];
                
                // Create transaction
                $transaction = Transaction::create($transactionData);
                
                // Refresh the model to get relationships
                $transaction->refresh();
                
                return $transaction;
            });
        });
    }

    /**
     * Update an existing transaction
     *
     * @param Transaction $transaction
     * @param array $data
     * @return Transaction
     * @throws \Exception If transaction would cause negative balance
     */
    public function updateTransaction(Transaction $transaction, array $data)
    {
        return $this->executeWithRetry(function () use ($transaction, $data) {
            return DB::transaction(function () use ($transaction, $data) {
                // Check if this transaction would cause a negative balance
                if (isset($data['type']) && $data['type'] === 'expense' && 
                    isset($data['amount']) && $data['amount'] > $transaction->amount) {
                    $account = Account::findOrFail($data['account_id'] ?? $transaction->account_id);
                    $currentBalance = $account->getCurrentBalanceAttribute();
                    $amountDifference = $data['amount'] - $transaction->amount;
                    if (($currentBalance - $amountDifference) < 0) {
                        throw new \Exception('Transaction update would cause negative balance. Current balance: ' . $currentBalance);
                    }
                }

                // Handle category relationship - this will check both UUID and ID
                if (isset($data['category_id'])) {
                    $category = Category::where('uuid', $data['category_id'])
                        ->orWhere('id', $data['category_id'])
                        ->first();
                    
                    if ($category) {
                        $data['category_id'] = $category->id;
                    } else {
                        // If category wasn't found but was provided, remove it to avoid errors
                        unset($data['category_id']);
                    }
                }                // Update transaction
                $transaction->update($data);
                
                // Instead of refresh, get a fresh instance from database
                $freshTransaction = Transaction::find($transaction->id);
                
                return $freshTransaction;
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
                return $transaction->delete();
            });
        });
    }

    /**
     * Get transaction statistics for a given period
     *
     * @param string $startDate
     * @param string $endDate
     * @param int|null $accountId
     * @return array
     */
    public function getStatistics($startDate, $endDate, $accountId = null)
    {
        $query = Transaction::query()
            ->whereBetween('transaction_date', [$startDate, $endDate]);

        if ($accountId) {
            $query->where('account_id', $accountId);
        } else {
            // If no specific account is selected, get all accounts for the current user
            $userAccounts = Auth::user()->accounts()->pluck('id')->toArray();
            $query->whereIn('account_id', $userAccounts);
        }

        // Get income and expense totals
        $incomeTotal = (clone $query)->where('type', 'income')->sum('amount');
        $expenseTotal = (clone $query)->where('type', 'expense')->sum('amount');
        
        // Calculate net
        $net = $incomeTotal - $expenseTotal;

        // Get category breakdown
        $categories = DB::table('transactions')
            ->select(
                'categories.id',
                'categories.uuid',
                'categories.name',
                'categories.colour_code',
                'categories.icon',
                'transactions.type',
                DB::raw('SUM(transactions.amount) as total')
            )
            ->join('categories', 'transactions.category_id', '=', 'categories.id')
            ->whereBetween('transaction_date', [$startDate, $endDate]);

        if ($accountId) {
            $categories->where('account_id', $accountId);
        } else {
            $categories->whereIn('account_id', Auth::user()->accounts()->pluck('id')->toArray());
        }

        $categories = $categories->groupBy('categories.id', 'categories.uuid', 'categories.name', 'categories.colour_code', 'categories.icon', 'transactions.type')
            ->orderBy('total', 'desc')
            ->get();

        // Get uncategorized transactions
        $uncategorized = DB::table('transactions')
            ->select(
                DB::raw("'Uncategorized' as name"),
                'type',
                DB::raw('SUM(amount) as total')
            )
            ->whereNull('category_id')
            ->whereBetween('transaction_date', [$startDate, $endDate]);

        if ($accountId) {
            $uncategorized->where('account_id', $accountId);
        } else {
            $uncategorized->whereIn('account_id', Auth::user()->accounts()->pluck('id')->toArray());
        }

        $uncategorized = $uncategorized->groupBy('type')
            ->get();

        return [
            'total_income' => $incomeTotal,
            'total_expense' => $expenseTotal,
            'net' => $net,
            'categories' => $categories,
            'uncategorized' => $uncategorized
        ];
    }

    /**
     * Get monthly trend data for charts
     *
     * @param int|null $accountId
     * @param int $months Number of past months to include
     * @return array
     */
    public function getMonthlyTrend($accountId = null, $months = 12)
    {
        $query = DB::table('transactions')
            ->select(
                DB::raw('DATE_FORMAT(transaction_date, "%Y-%m") as month'),
                'type',
                DB::raw('SUM(amount) as total')
            )
            ->whereDate('transaction_date', '>=', now()->subMonths($months))
            ->whereDate('transaction_date', '<=', now());

        if ($accountId) {
            $query->where('account_id', $accountId);
        } else {
            // If no specific account is selected, get all accounts for the current user
            $userAccounts = Auth::user()->accounts()->pluck('id')->toArray();
            $query->whereIn('account_id', $userAccounts);
        }

        return $query->groupBy('month', 'type')
            ->orderBy('month', 'asc')
            ->get();
    }
    
    /**
     * Get recent transactions
     *
     * @param int|null $accountId
     * @param int $limit
     * @return Collection
     */
    public function getRecentTransactions($accountId = null, $limit = 5): Collection
    {
        $query = Transaction::with(['account', 'category'])
            ->orderBy('transaction_date', 'desc');

        if ($accountId) {
            $query->where('account_id', $accountId);
        } else {
            // If no specific account is selected, get all accounts for the current user
            $userAccounts = Auth::user()->accounts()->pluck('id')->toArray();
            $query->whereIn('account_id', $userAccounts);
        }

        return $query->limit($limit)->get();
    }
    
    /**
     * Get transactions by category
     *
     * @param string $categoryId UUID or ID of the category
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getTransactionsByCategory($categoryId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        // Find the category by UUID or ID
        $category = Category::where('uuid', $categoryId)
            ->orWhere('id', $categoryId)
            ->first();
            
        if (!$category) {
            return new LengthAwarePaginator([], 0, $perPage);
        }
        
        $query = Transaction::query()
            ->where('category_id', $category->id);
            
        // Apply date range filters
        if (isset($filters['start_date']) && isset($filters['end_date'])) {
            $query->whereBetween('transaction_date', [$filters['start_date'], $filters['end_date']]);
        }
        
        // Apply account filter
        if (isset($filters['account_id']) && $filters['account_id']) {
            $query->where('account_id', $filters['account_id']);
        }
        
        // Include related models
        $query->with(['account', 'category']);
        
        // Apply ordering
        $query->orderBy('transaction_date', 'desc');
        
        return $query->paginate($perPage);
    }

    /**
     * Get category transaction statistics for a given period
     *
     * @param string $categoryId UUID or ID of the category
     * @param string $startDate
     * @param string $endDate
     * @param int|null $accountId
     * @return array
     */
    public function getCategoryStatistics($categoryId, $startDate, $endDate, $accountId = null)
    {
        // Find the category by UUID or ID
        $category = Category::where('uuid', $categoryId)
            ->orWhere('id', $categoryId)
            ->first();
            
        if (!$category) {
            return [
                'total' => 0,
                'count' => 0,
                'transactions' => []
            ];
        }
        
        $query = Transaction::query()
            ->where('category_id', $category->id)
            ->whereBetween('transaction_date', [$startDate, $endDate]);

        if ($accountId) {
            $query->where('account_id', $accountId);
        } else {
            // If no specific account is selected, get all accounts for the current user
            $userAccounts = Auth::user()->accounts()->pluck('id')->toArray();
            $query->whereIn('account_id', $userAccounts);
        }

        // Get income and expense totals for this category
        $incomeTotal = (clone $query)->where('type', 'income')->sum('amount');
        $expenseTotal = (clone $query)->where('type', 'expense')->sum('amount');
        
        // Get count of transactions
        $incomeCount = (clone $query)->where('type', 'income')->count();
        $expenseCount = (clone $query)->where('type', 'expense')->count();
        
        // Get monthly breakdown
        $monthlyData = DB::table('transactions')
            ->select(
                DB::raw('DATE_FORMAT(transaction_date, "%Y-%m") as month'),
                'type',
                DB::raw('SUM(amount) as total'),
                DB::raw('COUNT(*) as count')
            )
            ->where('category_id', $category->id)
            ->whereBetween('transaction_date', [$startDate, $endDate]);

        if ($accountId) {
            $monthlyData->where('account_id', $accountId);
        } else {
            $monthlyData->whereIn('account_id', Auth::user()->accounts()->pluck('id')->toArray());
        }

        $monthlyData = $monthlyData->groupBy('month', 'type')
            ->orderBy('month', 'asc')
            ->get();
        
        return [
            'category' => [
                'id' => $category->uuid,
                'name' => $category->name,
                'icon' => $category->icon,
                'colour_code' => $category->colour_code,
                'type' => $category->type
            ],
            'total_income' => $incomeTotal,
            'total_expense' => $expenseTotal,
            'income_count' => $incomeCount,
            'expense_count' => $expenseCount,
            'net' => $incomeTotal - $expenseTotal,
            'monthly_data' => $monthlyData
        ];
    }

    /**
     * Create a transfer between two accounts
     *
     * @param array $data Including source_account_id, destination_account_id, amount, description, transaction_date
     * @return array Array containing both the outgoing and incoming transactions
     * @throws \Exception If the transfer would cause negative balance
     */
    public function createTransfer(array $data)
    {
        // Start a database transaction
        return DB::transaction(function () use ($data) {
            // Create the outgoing transaction
            $sourceTransaction = $this->createTransaction([
                'account_id' => $data['source_account_id'],
                'amount' => $data['amount'],
                'type' => 'transfer',
                'description' => $data['description'] ?? 'Transfer to another account',
                'transaction_date' => $data['transaction_date'],
                'is_reconciled' => $data['is_reconciled'] ?? false,
                'category_id' => $data['category_id'] ?? null
            ]);

            // Create the incoming transaction
            $destinationTransaction = $this->createTransaction([
                'account_id' => $data['destination_account_id'],
                'amount' => $data['destination_amount'] ?? $data['amount'],
                'type' => 'transfer',
                'description' => $data['description'] ?? 'Transfer from another account',
                'transaction_date' => $data['transaction_date'],
                'is_reconciled' => $data['is_reconciled'] ?? false,
                'category_id' => $data['category_id'] ?? null
            ]);

            // Calculate exchange rate if different amounts
            $exchangeRate = null;
            $usedRealTimeRate = false;
            
            if (isset($data['destination_amount']) && $data['destination_amount'] != $data['amount']) {
                $exchangeRate = $data['destination_amount'] / $data['amount'];
                $usedRealTimeRate = $data['use_real_time_rate'] ?? false;
            }

            // Create the transfer record linking both transactions
            $transfer = Transfer::create([
                'source_transaction_id' => $sourceTransaction->id,
                'destination_transaction_id' => $destinationTransaction->id,
                'exchange_rate' => $exchangeRate,
                'used_real_time_rate' => $usedRealTimeRate
            ]);

            return [
                'outgoing' => $sourceTransaction,
                'incoming' => $destinationTransaction,
                'transfer' => $transfer,
                'exchange_rate' => $exchangeRate
            ];
        });
    }

    /**
     * Create a transfer between two accounts with currency conversion
     *
     * @param array $data Including source_account_id, destination_account_id, source_amount, destination_amount, etc.
     * @return array Array containing both the outgoing and incoming transactions
     * @throws \Exception If the transfer would cause negative balance
     */
    public function createCurrencyTransfer(array $data)
    {
        return DB::transaction(function () use ($data) {
            // Calculate exchange rate
            $exchangeRate = null;
            $useRealTimeRate = $data['use_real_time_rate'] ?? false;
            
            if (isset($data['source_amount']) && isset($data['destination_amount'])) {
                // Calculate exchange rate: destination_amount / source_amount
                $exchangeRate = round($data['destination_amount'] / $data['source_amount'], 6);
            }
            
            // Create the outgoing transaction (source account)
            $sourceTransaction = $this->createTransaction([
                'account_id' => $data['source_account_id'],
                'amount' => $data['source_amount'],
                'type' => 'transfer',
                'description' => $data['description'] ?? 'Currency transfer',
                'transaction_date' => $data['transaction_date'],
                'is_reconciled' => $data['is_reconciled'] ?? false,
                'reference' => $data['reference'] ?? 'TRANSFER-OUT-FX-' . strtoupper(Str::random(8))
            ]);

            // Create the incoming transaction (destination account)
            $destinationTransaction = $this->createTransaction([
                'account_id' => $data['destination_account_id'],
                'amount' => $data['destination_amount'],
                'type' => 'transfer',
                'description' => $data['description'] ?? 'Currency transfer',
                'transaction_date' => $data['transaction_date'],
                'is_reconciled' => $data['is_reconciled'] ?? false,
                'reference' => $data['reference'] ?? 'TRANSFER-IN-FX-' . strtoupper(Str::random(8))
            ]);

            // Create the transfer record that links the two transactions
            $transfer = new \App\Models\Transfer();
            $transfer->source_transaction_id = $sourceTransaction->id;
            $transfer->destination_transaction_id = $destinationTransaction->id;
            $transfer->exchange_rate = $exchangeRate;
            $transfer->used_real_time_rate = $useRealTimeRate;
            $transfer->save();

            return [
                'outgoing' => $sourceTransaction,
                'incoming' => $destinationTransaction,
                'transfer' => $transfer,  // Make sure this is included
                'exchange_rate' => $exchangeRate
            ];
        });
    }

    /**
     * Create a currency transfer between accounts with different currencies using real-time rates.
     * 
     * @param array $data
     * @return array
     * @throws \Exception
     */
    public function createCurrencyTransferWithRealTimeRate(array $data)
    {
        // Make sure we have all required data
        if (!isset($data['source_account_id']) || !isset($data['destination_account_id']) || !isset($data['source_amount'])) {
            throw new \InvalidArgumentException('Missing required transfer data');
        }
        
        return $this->executeWithRetry(function() use ($data) {
            return DB::transaction(function() use ($data) {
                // Get the accounts
                $sourceAccount = Account::findOrFail($data['source_account_id']);
                $destinationAccount = Account::findOrFail($data['destination_account_id']);
                
                // Verify the accounts have different currencies
                if ($sourceAccount->currency_id === $destinationAccount->currency_id) {
                    throw new \Exception('Accounts must have different currencies for a currency transfer');
                }
                
                // Get the current balance
                $currentBalance = $sourceAccount->balance;
                
                // Verify there's enough balance for the transfer
                if (($currentBalance - $data['source_amount']) < 0) {
                    throw new \Exception('Transfer would cause negative balance in the source account. Current balance: ' . $currentBalance);
                }
                
                // Get the exchange rate service
                $exchangeRateService = app(ExchangeRateService::class);
                
                // Get source and destination currency codes
                $sourceCurrency = $sourceAccount->currency->code;
                $destinationCurrency = $destinationAccount->currency->code;
                
                // Calculate destination amount using real-time rate
                $destinationAmount = $exchangeRateService->convertAmount(
                    $data['source_amount'], 
                    $sourceCurrency, 
                    $destinationCurrency
                );
                
                if ($destinationAmount === null) {
                    throw new \Exception('Failed to get real-time exchange rate');
                }
                
                // Calculate exchange rate for reference
                $exchangeRate = $destinationAmount / $data['source_amount'];
                
                // Generate a unique transfer reference
                $transferRef = 'FX-RT-TRANSFER-' . Str::upper(Str::random(8));
                
                // Find transfer category
                $category = Category::where('type', 'transfer')->first();
                $categoryId = $category ? $category->id : null;
                
                // Create outgoing transaction (from source account)
                $outgoingData = [
                    'uuid' => $data['uuid'] ?? (string) Str::uuid(),
                    'account_id' => $sourceAccount->id,
                    'category_id' => $categoryId,
                    'amount' => $data['source_amount'],
                    'type' => 'transfer',
                    'description' => $data['description'] ?? sprintf(
                        'Currency transfer to %s (Rate: 1 %s = %.4f %s)', 
                        $destinationAccount->name,
                        $sourceAccount->currency->code,
                        $exchangeRate,
                        $destinationAccount->currency->code
                    ),
                    'transaction_date' => $data['transaction_date'] ?? now(),
                    'is_reconciled' => $data['is_reconciled'] ?? false,
                    'reference' => 'TRANSFER-OUT-' . $transferRef,
                ];
                
                $outgoingTransaction = Transaction::create($outgoingData);
                
                // Create incoming transaction (to destination account)
                $incomingData = [
                    'uuid' => (string) Str::uuid(),
                    'account_id' => $destinationAccount->id,
                    'category_id' => $categoryId,
                    'amount' => $destinationAmount,
                    'type' => 'transfer',
                    'description' => $data['description'] ?? sprintf(
                        'Currency transfer from %s (Rate: 1 %s = %.4f %s)', 
                        $sourceAccount->name,
                        $sourceAccount->currency->code,
                        $exchangeRate,
                        $destinationAccount->currency->code
                    ),
                    'transaction_date' => $data['transaction_date'] ?? now(),
                    'is_reconciled' => $data['is_reconciled'] ?? false,
                    'reference' => 'TRANSFER-IN-' . $transferRef,
                ];
                
                $incomingTransaction = Transaction::create($incomingData);
                
                // Refresh the models to get relationships
                $outgoingTransaction->refresh();
                $incomingTransaction->refresh();
                
                return [
                    'outgoing' => $outgoingTransaction,
                    'incoming' => $incomingTransaction,
                    'exchange_rate' => $exchangeRate,
                ];
            });
        });
    }

    /**
     * Execute a callback function with retry logic
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
                $lastException = $e;
                $attempts++;
                if ($attempts < $this->maxRetries) {
                    Log::warning("Transaction operation failed, retrying ({$attempts}/{$this->maxRetries}): " . $e->getMessage());
                    sleep($this->retryDelay);
                }
            }
        }

        Log::error("Transaction operation failed after {$this->maxRetries} attempts: " . $lastException->getMessage());
        throw $lastException;
    }
}