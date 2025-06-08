<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\TransactionResource;
use App\Models\Account;
use App\Models\Category;
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
            // Get the authenticated user
            $user = Auth::user();
            
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
            
            // Get transactions using the service with user filtering for security
            $perPage = $request->input('per_page', 15);
            $transactions = $this->transactionService->getTransactions($filters, $perPage, $user->id);
            
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
            
            // Validate input with custom validation for user accounts and categories            
            $validator = Validator::make($request->all(), [
                'account_id' => [
                    'required',
                    'string',
                    function ($attribute, $value, $fail) use ($user) {
                        // Find account by either ID or UUID
                        $account = null;
                        if (is_numeric($value)) {
                            $account = Account::find($value);
                        } else {
                            $account = Account::where('uuid', $value)->first();
                        }
                        
                        if (!$account) {
                            $fail('The selected account does not exist.');
                            return;
                        }
                        
                        if ($account->user_id !== $user->id) {
                            $fail('The selected account does not belong to you.');
                        }
                    }
                ],
                'amount' => 'required|numeric|min:0.01',
                'type' => 'required|in:income,expense,transfer',
                'category_id' => [
                    'nullable',
                    'string',
                    function ($attribute, $value, $fail) use ($request) {
                        if ($value) {
                            // Find category by either ID or UUID
                            $category = Category::where('uuid', $value)
                                ->orWhere('id', $value)
                                ->first();
                            
                            if (!$category) {
                                $fail('The selected category does not exist.');
                                return;
                            }
                            
                            // Validate category type matches transaction type
                            $transactionType = $request->input('type');
                            if ($transactionType && $category->type !== $transactionType) {
                                $fail('The selected category type does not match the transaction type.');
                            }
                        }
                    }
                ],
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
            
            // Find account by either ID or UUID (we know it exists and belongs to user from validation)
            $account = null;
            if (is_numeric($request->account_id)) {
                $account = Account::find($request->account_id);
            } else {
                $account = Account::where('uuid', $request->account_id)->first();
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
            
            // Find the transaction by UUID with user authorization
            $transaction = $this->transactionService->findByUuid($uuid, $user->id);
            
            if (!$transaction) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Transaction not found or unauthorized access'
                ], 404);
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
            
            // Find the transaction by UUID with user authorization
            $transaction = $this->transactionService->findByUuid($uuid, $user->id);
            
            if (!$transaction) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Transaction not found or unauthorized access'
                ], 404);
            }
              // Validate input with custom validation for user accounts and categories
            $validator = Validator::make($request->all(), [
                'account_id' => [
                    'sometimes',
                    'required',
                    'string',
                    function ($attribute, $value, $fail) use ($user) {
                        // Find account by either ID or UUID
                        $account = null;
                        if (is_numeric($value)) {
                            $account = Account::find($value);
                        } else {
                            $account = Account::where('uuid', $value)->first();
                        }
                        
                        if (!$account) {
                            $fail('The selected account does not exist.');
                            return;
                        }
                        
                        if ($account->user_id !== $user->id) {
                            $fail('The selected account does not belong to you.');
                        }
                    }
                ],
                'amount' => 'sometimes|required|numeric|min:0.01',
                'type' => 'sometimes|required|in:income,expense,transfer',
                'category_id' => [
                    'nullable',
                    'string',
                    function ($attribute, $value, $fail) use ($request) {
                        if ($value) {
                            // Find category by either ID or UUID
                            $category = Category::where('uuid', $value)
                                ->orWhere('id', $value)
                                ->first();
                            
                            if (!$category) {
                                $fail('The selected category does not exist.');
                                return;
                            }
                            
                            // Validate category type matches transaction type
                            $transactionType = $request->input('type');
                            if ($transactionType && $category->type !== $transactionType) {
                                $fail('The selected category type does not match the transaction type.');
                            }
                        }
                    }
                ],
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
            // Prepare update data (make sure to only include fillable attributes that have changed)
            $updateData = [];            
            if ($request->has('account_id')) {
                // Find account by either ID or UUID (already validated)
                if (is_numeric($request->account_id)) {
                    $newAccount = Account::find($request->account_id);
                } else {
                    $newAccount = Account::where('uuid', $request->account_id)->first();
                }
                $updateData['account_id'] = $newAccount->id; // Use actual ID
            }
            if ($request->has('amount')) $updateData['amount'] = $request->amount;
            if ($request->has('type')) $updateData['type'] = $request->type;
            if ($request->has('category_id')) $updateData['category_id'] = $request->category_id; // TransactionService will handle UUID to ID conversion
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
            
            // Find the transaction by UUID with user authorization
            $transaction = $this->transactionService->findByUuid($uuid, $user->id);
            
            if (!$transaction) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Transaction not found or unauthorized access'
                ], 404);
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
                
                // Get statistics for all user accounts with user ID parameter
                $stats = [];
                foreach ($accountIds as $accId) {
                    $stats[$accId] = $this->transactionService->getStatistics($startDate, $endDate, $accId, $user->id);
                }
                  return response()->json([
                    'status' => 'success',
                    'data' => [
                        'accounts' => $stats,
                        'overall' => $this->transactionService->getStatistics($startDate, $endDate, null, $user->id)
                    ]
                ]);
            }
            
            // Get statistics for the specified account with user ID parameter
            $stats = $this->transactionService->getStatistics($startDate, $endDate, $accountId, $user->id);
            
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
                    'outgoing' => new TransactionResource($result['outgoing']),
                    'incoming' => new TransactionResource($result['incoming']),
                    'transfer_id' => $result['transfer']->uuid,
                    'exchange_rate' => $result['exchange_rate']
                ]
            ], 201);
            
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
            // Validate input
            $validator = Validator::make($request->all(), [
                'source_account_id' => 'required|string',
                'destination_account_id' => 'required|string|different:source_account_id',
                'source_amount' => 'required|numeric|min:0.01',
                'destination_amount' => 'required_without:use_real_time_rate|numeric|min:0.01',
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
            
            // Get authenticated user
            $user = Auth::user();
            
            // Find accounts by UUID
            $sourceAccount = Account::where('uuid', $request->source_account_id)->first();
            $destinationAccount = Account::where('uuid', $request->destination_account_id)->first();
            
            if (!$sourceAccount || !$destinationAccount) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'One or both accounts not found'
                ], 404);
            }
            
            // Verify user owns both accounts
            if ($sourceAccount->user_id !== $user->id || $destinationAccount->user_id !== $user->id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized access to accounts'
                ], 403);
            }
            
            $destinationAmount = $request->destination_amount;
            $useRealTimeRate = $request->use_real_time_rate ?? false;
            
            // If using real-time exchange rate
            if ($useRealTimeRate) {
                try {
                    $exchangeRateService = app(ExchangeRateService::class);
                    $sourceCurrency = $sourceAccount->currency->code;
                    $destinationCurrency = $destinationAccount->currency->code;
                    
                    // Calculate destination amount using real-time rate
                    $destinationAmount = $exchangeRateService->convertAmount(
                        $request->source_amount, 
                        $sourceCurrency, 
                        $destinationCurrency
                    );
                    
                    if ($destinationAmount === null) {
                        return response()->json([
                            'status' => 'error',
                            'message' => 'Failed to get real-time exchange rate'
                        ], 500);
                    }
                } catch (\Exception $e) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Exchange rate service error: ' . $e->getMessage()
                    ], 500);
                }
            }
            
            // Prepare transfer data
            $transferData = [
                'source_account_id' => $sourceAccount->id,
                'destination_account_id' => $destinationAccount->id,
                'source_amount' => $request->source_amount,
                'destination_amount' => $destinationAmount,
                'description' => $request->description ?? 'Currency transfer',
                'transaction_date' => $request->transaction_date,
                'is_reconciled' => $request->is_reconciled ?? false,
                'reference' => $request->reference,
                'use_real_time_rate' => $useRealTimeRate
            ];
            
            // Create the currency transfer using the service
            $result = $this->transactionService->createCurrencyTransfer($transferData);
            
            return response()->json([
                'status' => 'success',
                'message' => 'Currency transfer created successfully',
                'data' => [
                    'outgoing' => new TransactionResource($result['outgoing']),
                    'incoming' => new TransactionResource($result['incoming']),
                    'transfer_id' => isset($result['transfer']) && $result['transfer']->uuid ? $result['transfer']->uuid : null,
                    'exchange_rate' => $result['exchange_rate'],
                    'used_real_time_rate' => $useRealTimeRate,
                    'source_account' => [
                        'id' => $sourceAccount->uuid,
                        'name' => $sourceAccount->name,
                        'currency' => $sourceAccount->currency->code,
                        'new_balance' => $sourceAccount->getCurrentBalanceAttribute()
                    ],
                    'destination_account' => [
                        'id' => $destinationAccount->uuid,
                        'name' => $destinationAccount->name,
                        'currency' => $destinationAccount->currency->code,
                        'new_balance' => $destinationAccount->getCurrentBalanceAttribute()
                    ]
                ]
            ], 201);
            
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Currency transfer failed: ' . $e->getMessage()
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
            $validator = Validator::make($request->all(), [
                'from_currency' => 'required|string',
                'to_currency' => 'required|string',
                'amount' => 'nullable|numeric|min:0.01'
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            $fromCurrency = $request->from_currency;
            $toCurrency = $request->to_currency;
            
            // Check if we received currency IDs instead of codes
            if (strlen($fromCurrency) != 3) {
                // Try to find it as a UUID first
                $currency = Currency::where('uuid', $fromCurrency)->first();
                if (!$currency) {
                    // Then try as a numeric ID
                    $currency = Currency::find($fromCurrency);
                }
                
                if (!$currency) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Source currency not found'
                    ], 404);
                }
                
                $fromCurrency = $currency->code;
            }
            
            if (strlen($toCurrency) != 3) {
                // Try to find it as a UUID first
                $currency = Currency::where('uuid', $toCurrency)->first();
                if (!$currency) {
                    // Then try as a numeric ID
                    $currency = Currency::find($toCurrency);
                }
                
                if (!$currency) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Destination currency not found'
                    ], 404);
                }
                
                $toCurrency = $currency->code;
            }
            
            // Ensure codes are uppercase
            $fromCurrency = strtoupper($fromCurrency);
            $toCurrency = strtoupper($toCurrency);
              // Get the exchange rate service
            $exchangeRateService = app(ExchangeRateService::class);
            
            // Get the exchange rate
            $rate = $exchangeRateService->getRate($fromCurrency, $toCurrency);
            
            if ($rate === null) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Failed to get exchange rate'
                ], 500);
            }
            else if ($rate <= 0) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid exchange rate received'
                ], 422);
            }
            
            $result = [
                'from_currency' => $fromCurrency,
                'to_currency' => $toCurrency,
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
                'message' => 'Failed to get exchange rate: ' . $e->getMessage()
            ], 500);
        }
    }
    
  
}
