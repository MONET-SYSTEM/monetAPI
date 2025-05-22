<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\Currency;
use App\Services\TransactionService;
use App\Services\ExchangeRateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class TransactionApiController extends Controller
{
    protected $transactionService;    public function __construct(TransactionService $transactionService)
    {
        $this->transactionService = $transactionService;
        
        // Additional verification of authentication in controller constructor
        $this->middleware(function ($request, $next) {
            if (!Auth::check()) {
                abort(response()->json(['status' => 'error', 'message' => 'Unauthenticated'], 401));
            }
            return $next($request);
        });
    }

    /**
     * Display a listing of the transactions.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */    public function index(Request $request)
    {
        try {
            // Prepare filters from request data
            $filters = [
                'account_id' => $request->account_id,
                'type' => $request->type,
                'category_id' => $request->category_id,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'search' => $request->search,
                'is_reconciled' => $request->has('is_reconciled') ? $request->is_reconciled : null,
            ];
            
            // Get transactions using the service
            $perPage = $request->input('per_page', 15);
            $transactions = $this->transactionService->getTransactions($filters, $perPage);
            
            return response()->json([
                'status' => 'success',
                'data' => \App\Http\Resources\TransactionResource::collection($transactions),
                'meta' => [
                    'total' => $transactions->total(),
                    'per_page' => $transactions->perPage(),
                    'current_page' => $transactions->currentPage(),
                    'last_page' => $transactions->lastPage(),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve transactions',
                'error' => $e->getMessage()
            ], 500);
        }
    }    /**
     * Store a newly created transaction in storage.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */    public function store(Request $request)
    {
        try {
            // Get the authenticated user
            $user = Auth::user();
            
            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthenticated user'
                ], 401);
            }
            
            // Validate input            
            $validator = Validator::make($request->all(), [
                'account_id' => 'required|string',
                'amount' => 'required|numeric|min:0.01',
                'type' => 'required|in:income,expense,transfer',
                'category_id' => 'nullable|string',  // Allow category by ID or UUID
                'description' => 'nullable|string|max:255',
                'transaction_date' => 'required|date',
                'is_reconciled' => 'boolean',
                'reference' => 'nullable|string|max:100'
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            // Find account by either ID or UUID
            $account = null;
            if (is_numeric($request->account_id)) {
                $account = Account::find($request->account_id);
            } else {
                // Try to find by UUID
                $account = Account::where('uuid', $request->account_id)->first();
            }
            
            if (!$account) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Account not found'
                ], 404);
            }
            
            if ($account->user_id != $user->id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized access to this account'
                ], 403);
            }
            
            $transactionData = [                
                'account_id' => $account->id, // Use the account's actual ID, not the UUID
                'amount' => $request->amount,
                'type' => $request->type,
                'category_id' => $request->category_id, // TransactionService will handle the lookup
                'description' => $request->description,
                'transaction_date' => $request->transaction_date,
                'is_reconciled' => $request->is_reconciled ?? false,
                'reference' => $request->reference,
            ];
            
            // Create the transaction using the service
            $transaction = $this->transactionService->createTransaction($transactionData);
            
            // Load relationships for the resource
            $transaction->load(['account', 'category']);
            
            return response()->json([
                'status' => 'success',
                'message' => 'Transaction created successfully',
                'data' => new \App\Http\Resources\TransactionResource($transaction)
            ], 201);
        } catch (\Exception $e) {
            // Log the detailed error
            \Illuminate\Support\Facades\Log::error('Transaction creation failed: ' . $e->getMessage(), [
                'exception' => $e,
                'request' => request()->all(),
                'user_id' => Auth::id()
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create transaction',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified transaction.
     *
     * @param string $uuid
     * @return \Illuminate\Http\JsonResponse
     */    public function show($uuid)
    {
        try {
            // Get the authenticated user
            $user = Auth::user();
            
            // Use the service to find the transaction by UUID
            $transaction = $this->transactionService->findByUuid($uuid);
            
            if (!$transaction) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Transaction not found'
                ], 404);
            }
            
            // Verify the account belongs to the user
            $account = Account::find($transaction->account_id);
            if (!$account || $account->user_id !== $user->id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized access to this transaction'
                ], 403);
            }
              return response()->json([
                'status' => 'success',
                'data' => new \App\Http\Resources\TransactionResource($transaction)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve transaction',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified transaction in storage.
     *
     * @param Request $request
     * @param string $uuid
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $uuid)
    {
        try {
            // Get the authenticated user
            $user = Auth::user();
            
            // Find the transaction by UUID
            $transaction = Transaction::where('uuid', $uuid)->first();
            
            if (!$transaction) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Transaction not found'
                ], 404);
            }
            
            // Verify the account belongs to the user
            $account = Account::find($transaction->account_id);
            if (!$account || $account->user_id !== $user->id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized access to this transaction'
                ], 403);
            }
              // Validate input            
            $validator = Validator::make($request->all(), [
                'account_id' => 'sometimes|required|string', // Allow UUIDs
                'amount' => 'sometimes|required|numeric|min:0.01',
                'type' => 'sometimes|required|in:income,expense,transfer',
                'category_id' => 'nullable|string', // Allow category by ID or UUID - service will handle lookup
                'description' => 'nullable|string|max:255',
                'transaction_date' => 'sometimes|required|date',
                'is_reconciled' => 'boolean',
                'reference' => 'nullable|string|max:100'
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }
              // If account_id is being changed, verify the new account belongs to the user
            if ($request->has('account_id')) {
                $newAccount = null;
                
                // Find account by either ID or UUID
                if (is_numeric($request->account_id)) {
                    $newAccount = Account::find($request->account_id);
                } else {
                    // Try to find by UUID
                    $newAccount = Account::where('uuid', $request->account_id)->first();
                }
                
                if (!$newAccount) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Target account not found'
                    ], 404);
                }
                
                if ($newAccount->user_id !== $user->id) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Unauthorized access to the target account'
                    ], 403);
                }
                
                // Replace the UUID with the actual ID
                $request->merge(['account_id' => $newAccount->id]);
            }

            // Determine the account to check for balance
            $accountToCheckBalance = $request->has('account_id') ? $newAccount : $account;
            $newAmount = $request->input('amount', $transaction->amount);
            $newType = $request->input('type', $transaction->type);

            // Negative balance check for expense or transfer out of an account
            if ($newType === 'expense' || ($newType === 'transfer' && $request->input('account_id', $transaction->account_id) == $accountToCheckBalance->id)) {
                // Calculate the actual effect of the original transaction on the balance
                $actualOldEffect = 0;
                if ($transaction->type === 'income') {
                    $actualOldEffect = $transaction->amount;
                } elseif ($transaction->type === 'expense' || $transaction->type === 'transfer') {
                    $actualOldEffect = -$transaction->amount;
                }

                // Calculate the actual effect of the new/updated transaction on the balance
                $actualNewEffect = 0;
                if ($newType === 'income') {
                    $actualNewEffect = $newAmount;
                } elseif ($newType === 'expense' || $newType === 'transfer') {
                    $actualNewEffect = -$newAmount;
                }
                
                // This is the net change that will be applied to the current balance
                $balanceDelta = $actualNewEffect - $actualOldEffect;

                // If changing accounts, the logic is simpler: check the new account's balance against the new transaction's effect.
                if ($request->has('account_id') && $transaction->account_id != $accountToCheckBalance->id) {
                    // Transaction is moving to a new account ($accountToCheckBalance is $newAccount)
                    // The effect on the new account is purely $actualNewEffect.
                    if (($accountToCheckBalance->getCurrentBalanceAttribute() + $actualNewEffect) < 0 && $actualNewEffect < 0) { // only block if new effect is negative and causes negative balance
                        return response()->json([
                            'status' => 'error',
                            'message' => 'Transaction would cause negative balance in the new account. New account current balance: ' . $accountToCheckBalance->getCurrentBalanceAttribute() . '. Effect of new transaction: ' . $actualNewEffect
                        ], 422);
                    }
                } else {
                    // Transaction is in the same account, or only amount/type is changing
                    // We block if the update makes the balance negative AND the delta itself is negative (i.e., makes things worse or more negative)
                    if (($accountToCheckBalance->getCurrentBalanceAttribute() + $balanceDelta) < 0 && $balanceDelta < 0) {
                         return response()->json([
                            'status' => 'error',
                            'message' => 'Transaction update would cause negative balance. Account current balance: ' . $accountToCheckBalance->getCurrentBalanceAttribute() . '. Change: ' . $balanceDelta
                        ], 422);
                    }
                }
            }
            
              // Prepare update data (make sure to only include fillable attributes that have changed)
            $updateData = [];            
            if ($request->has('account_id')) $updateData['account_id'] = $request->account_id; // Already converted to ID if UUID was passed
            if ($request->has('amount')) $updateData['amount'] = $request->amount;
            if ($request->has('type')) $updateData['type'] = $request->type;
            if ($request->has('category_id')) $updateData['category_id'] = $request->category_id; // Corrected: use category_id
            if ($request->has('description')) $updateData['description'] = $request->description;
            if ($request->has('transaction_date')) $updateData['transaction_date'] = $request->transaction_date;
            if ($request->has('is_reconciled')) $updateData['is_reconciled'] = $request->is_reconciled;
            if ($request->has('reference')) $updateData['reference'] = $request->reference;
            
            // Update the transaction using the service
            $updated = $this->transactionService->updateTransaction($transaction, $updateData);
            
            // Load relationships
            $updated->load(['account', 'category']);
              return response()->json([
                'status' => 'success',
                'message' => 'Transaction updated successfully',
                'data' => new \App\Http\Resources\TransactionResource($updated)
            ]);
        } catch (\Exception $e) {
            // Log the detailed error
            \Illuminate\Support\Facades\Log::error('Transaction update failed: ' . $e->getMessage(), [
                'exception' => $e,
                'request' => request()->all(),
                'user_id' => Auth::id(),
                'transaction_uuid' => $uuid
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update transaction',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified transaction from storage.
     *
     * @param string $uuid
     * @return \Illuminate\Http\JsonResponse
     */    public function destroy($uuid)
    {
        try {
            // Get the authenticated user
            $user = Auth::user();
            
            // Find the transaction by UUID using the service
            $transaction = $this->transactionService->findByUuid($uuid);
            
            if (!$transaction) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Transaction not found'
                ], 404);
            }
            
            // Verify the account belongs to the user
            $account = Account::find($transaction->account_id);
            if (!$account || $account->user_id !== $user->id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized access to this transaction'
                ], 403);
            }
            
            // Delete the transaction using the service
            $this->transactionService->deleteTransaction($transaction);
            
            return response()->json([
                'status' => 'success',
                'message' => 'Transaction deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete transaction',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get transaction statistics.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function statistics(Request $request)
    {
        try {
            // Get the authenticated user
            $user = Auth::user();
            
            // Get date range and account ID
            $startDate = $request->input('start_date', now()->startOfMonth()->format('Y-m-d'));
            $endDate = $request->input('end_date', now()->format('Y-m-d'));
            $accountId = $request->input('account_id');
            
            // If account ID is provided, verify it belongs to the user
            if ($accountId) {
                $account = Account::find($accountId);
                if (!$account || $account->user_id !== $user->id) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Unauthorized access to this account'
                    ], 403);
                }
            } else {
                // If no account ID is provided, use all user accounts
                $accountIds = Account::where('user_id', $user->id)->pluck('id')->toArray();
                
                // Get statistics for all user accounts
                $stats = [];
                foreach ($accountIds as $accId) {
                    $stats[$accId] = $this->transactionService->getStatistics($startDate, $endDate, $accId);
                }
                  return response()->json([
                    'status' => 'success',
                    'data' => [
                        'accounts' => $stats,
                        'overall' => $this->transactionService->getStatistics($startDate, $endDate)
                    ]
                ]);
            }
            
            // Get statistics for the specified account
            $stats = $this->transactionService->getStatistics($startDate, $endDate, $accountId);
            
            return response()->json([
                'status' => 'success',
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Create a transfer between accounts.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function transfer(Request $request)
    {
        try {
            // Get the authenticated user
            $user = Auth::user();
            
            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthenticated user'
                ], 401);
            }
            
            // Validate input
            $validator = Validator::make($request->all(), [
                'source_account_id' => 'required|string',
                'destination_account_id' => 'required|string|different:source_account_id',
                'amount' => 'required|numeric|min:0.01',
                'description' => 'nullable|string|max:255',
                'transaction_date' => 'required|date',
                'is_reconciled' => 'boolean',
                'reference' => 'nullable|string|max:100'
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            // Retrieve the accounts
            $sourceAccount = Account::where('uuid', $request->source_account_id)
                ->orWhere('id', $request->source_account_id)
                ->first();
                
            $destinationAccount = Account::where('uuid', $request->destination_account_id)
                ->orWhere('id', $request->destination_account_id)
                ->first();
            
            if (!$sourceAccount || !$destinationAccount) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'One or both accounts not found'
                ], 404);
            }
            
            // Check if both accounts belong to the user
            $userAccounts = $user->accounts()->pluck('id')->toArray();
            
            if (!in_array($sourceAccount->id, $userAccounts) || !in_array($destinationAccount->id, $userAccounts)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized access to one or both accounts'
                ], 403);
            }
            
            // Verify both accounts have the same currency
            if ($sourceAccount->currency_id !== $destinationAccount->currency_id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'For accounts with different currencies, use the currency-transfer endpoint'
                ], 422);
            }
            
            // Prepare transfer data
            $transferData = [
                'source_account_id' => $sourceAccount->id,
                'destination_account_id' => $destinationAccount->id,
                'amount' => $request->amount,
                'description' => $request->description,
                'transaction_date' => $request->transaction_date,
                'is_reconciled' => $request->is_reconciled ?? false,
                'reference' => $request->reference
            ];
            
            // Create the transfer using the service
            $result = $this->transactionService->createTransfer($transferData);
            
            return response()->json([
                'status' => 'success',
                'message' => 'Transfer created successfully',
                'data' => [
                    'outgoing' => new \App\Http\Resources\TransactionResource($result['outgoing']),
                    'incoming' => new \App\Http\Resources\TransactionResource($result['incoming'])
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create transfer',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Create a transfer between accounts with different currencies.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function currencyTransfer(Request $request)
    {
        try {
            // Get the authenticated user
            $user = Auth::user();
            
            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthenticated user'
                ], 401);
            }
            
            // Validate input
            $validator = Validator::make($request->all(), [
                'source_account_id' => 'required|string',
                'destination_account_id' => 'required|string|different:source_account_id',
                'source_amount' => 'required|numeric|min:0.01',
                'destination_amount' => 'required|numeric|min:0.01',
                'use_real_time_rate' => 'boolean',
                'description' => 'nullable|string|max:255',
                'transaction_date' => 'required|date',
                'is_reconciled' => 'boolean',
                'reference' => 'nullable|string|max:100'
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            // Retrieve the accounts
            $sourceAccount = Account::where('uuid', $request->source_account_id)
                ->orWhere('id', $request->source_account_id)
                ->first();
                
            $destinationAccount = Account::where('uuid', $request->destination_account_id)
                ->orWhere('id', $request->destination_account_id)
                ->first();
            
            if (!$sourceAccount || !$destinationAccount) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'One or both accounts not found'
                ], 404);
            }
            
            // Check if both accounts belong to the user
            $userAccounts = $user->accounts()->pluck('id')->toArray();
            
            if (!in_array($sourceAccount->id, $userAccounts) || !in_array($destinationAccount->id, $userAccounts)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized access to one or both accounts'
                ], 403);
            }
            
            // Verify accounts have different currencies
            if ($sourceAccount->currency_id === $destinationAccount->currency_id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'For accounts with the same currency, use the transfer endpoint'
                ], 422);
            }
            
            // Handle real-time exchange rates if requested
            $destinationAmount = $request->destination_amount;
            
            if ($request->use_real_time_rate ?? false) {
                // Get the exchange rate service
                $exchangeRateService = app(ExchangeRateService::class);
                
                // Get source and destination currency codes
                $sourceCurrency = $sourceAccount->currency->code;
                $destinationCurrency = $destinationAccount->currency->code;
                
                // Calculate destination amount using real-time rate
                $calculatedAmount = $exchangeRateService->convertAmount(
                    $request->source_amount, 
                    $sourceCurrency, 
                    $destinationCurrency
                );
                
                if ($calculatedAmount === null) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Failed to get real-time exchange rate'
                    ], 500);
                }
                
                $destinationAmount = $calculatedAmount;
            }
            
            // Prepare transfer data
            $transferData = [
                'source_account_id' => $sourceAccount->id,
                'destination_account_id' => $destinationAccount->id,
                'source_amount' => $request->source_amount,
                'destination_amount' => $destinationAmount,
                'description' => $request->description,
                'transaction_date' => $request->transaction_date,
                'is_reconciled' => $request->is_reconciled ?? false,
                'reference' => $request->reference
            ];
            
            // Create the currency transfer using the service
            $result = $this->transactionService->createCurrencyTransfer($transferData);
            
            return response()->json([
                'status' => 'success',
                'message' => 'Currency transfer created successfully',
                'data' => [
                    'outgoing' => new \App\Http\Resources\TransactionResource($result['outgoing']),
                    'incoming' => new \App\Http\Resources\TransactionResource($result['incoming']),
                    'exchange_rate' => $result['exchange_rate']
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create currency transfer',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get exchange rate between two currencies.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getExchangeRate(Request $request)
    {
        try {
            // Validate input
            $validator = Validator::make($request->all(), [
                'from_currency' => 'required|string|size:3',
                'to_currency' => 'required|string|size:3',
                'amount' => 'nullable|numeric|min:0.01'
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            // Get the exchange rate service
            $exchangeRateService = app(ExchangeRateService::class);
            
            // Get the exchange rate
            $rate = $exchangeRateService->getRate(
                $request->from_currency, 
                $request->to_currency
            );
            
            if ($rate === null) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Failed to get exchange rate'
                ], 500);
            }
            
            $result = [
                'from_currency' => $request->from_currency,
                'to_currency' => $request->to_currency,
                'rate' => $rate,
                'timestamp' => now()->timestamp,
                'date' => now()->toDateTimeString()
            ];
            
            // Calculate converted amount if provided
            if ($request->has('amount')) {
                $result['amount'] = $request->amount;
                $result['converted_amount'] = round($request->amount * $rate, 2);
            }
            
            return response()->json([
                'status' => 'success',
                'data' => $result
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get exchange rate',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
