<?php

namespace App\Http\Controllers;

use App\Http\Resources\TransactionResource;
use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Services\TransactionService;
use App\Services\ExchangeRateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class TransactionController extends Controller
{
    protected $transactionService;

    public function __construct(TransactionService $transactionService)
    {
        $this->transactionService = $transactionService;
    }

    /**
     * Display a listing of the transactions.
     */
    public function index(Request $request)
    {
        // Get the authenticated user
        $user = Auth::user();
        
        // Get user accounts
        $accounts = Account::where('user_id', $user->id)->get();
        $accountIds = $accounts->pluck('id')->toArray();
        
        // Build query
        $query = Transaction::whereIn('account_id', $accountIds);
        
        // Apply filters if provided
        if ($request->has('account_id') && $request->account_id) {
            $query->where('account_id', $request->account_id);
        }
        
        if ($request->has('type') && $request->type) {
            $query->where('type', $request->type);
        }
        
        if ($request->has('category_id') && $request->category_id) {
            $query->where('category_id', $request->category_id);
        }
        
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('transaction_date', [$request->start_date, $request->end_date]);
        }
        
        // Get transactions with pagination
        $transactions = $query->with(['account', 'category'])
            ->orderBy('transaction_date', 'desc')
            ->paginate(15);
        
        // Get categories for filtering
        $categories = Category::all();
        
        return view('admin.transactions.index', compact('transactions', 'accounts', 'categories'));
    }    /**
     * Show the form for creating a new transaction.
     */
    public function create()
    {
        // Get the authenticated user
        $user = Auth::user();
        
        // Get user accounts only
        $accounts = Account::where('user_id', $user->id)->get();
        
        // Get categories
        $categories = Category::all();
        
        return view('admin.transactions.create', compact('accounts', 'categories'));
    }/**
     * Store a newly created transaction in storage.
     */
    public function store(Request $request)
    {
        // Get the authenticated user
        $user = Auth::user();
        
        // Validate input with custom validation for user accounts and categories
        $validator = Validator::make($request->all(), [
            'account_id' => [
                'required',
                'exists:accounts,id',
                function ($attribute, $value, $fail) use ($user) {
                    $account = Account::find($value);
                    if ($account && $account->user_id !== $user->id) {
                        $fail('The selected account does not belong to you.');
                    }
                }
            ],
            'amount' => 'required|numeric|min:0.01',
            'type' => 'required|in:income,expense,transfer',
            'category_id' => [
                'nullable',
                'exists:categories,id',
                function ($attribute, $value, $fail) use ($request) {
                    if ($value) {
                        $category = Category::find($value);
                        if ($category) {
                            // Validate category type matches transaction type
                            $transactionType = $request->input('type');
                            if ($transactionType && $category->type !== $transactionType) {
                                $fail('The selected category type does not match the transaction type.');
                            }
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
            return redirect()->route('admin.transactions.create')
                ->withErrors($validator)
                ->withInput();
        }
        
        try {
            // Create the transaction using the service
            $transaction = $this->transactionService->createTransaction($request->all());
            
            return redirect()->route('admin.transactions.index')
                ->with('success', 'Transaction created successfully.');
        } catch (\Exception $e) {
            return redirect()->route('admin.transactions.create')
                ->with('error', 'Error creating transaction: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Display the specified transaction.
     */
    public function show(Transaction $transaction)
    {
        // Authorize that the user can view this transaction
        $user = Auth::user();
        $account = Account::find($transaction->account_id);
        
        if ($account->user_id !== $user->id) {
            abort(403, 'Unauthorized action.');        }
        
        // Load relationships
        $transaction->load(['account', 'category']);
        
        return view('admin.transactions.show', compact('transaction'));
    }    /**
     * Show the form for editing the specified transaction.
     */
    public function edit(Transaction $transaction)
    {
        // Authorize that the user can edit this transaction
        $user = Auth::user();
        $account = Account::find($transaction->account_id);
        
        if ($account->user_id !== $user->id) {
            abort(403, 'Unauthorized action.');
        }
        
        // Get user accounts only
        $accounts = Account::where('user_id', $user->id)->get();
        
        // Get categories
        $categories = Category::all();
        
        return view('admin.transactions.edit', compact('transaction', 'accounts', 'categories'));
    }/**
     * Update the specified transaction in storage.
     */
    public function update(Request $request, Transaction $transaction)
    {
        // Authorize that the user can update this transaction
        $user = Auth::user();
        $account = Account::find($transaction->account_id);
        
        if ($account->user_id !== $user->id) {
            abort(403, 'Unauthorized action.');
        }
        
        // Validate input with custom validation for user accounts and categories
        $validator = Validator::make($request->all(), [
            'account_id' => [
                'required',
                'exists:accounts,id',
                function ($attribute, $value, $fail) use ($user) {
                    $account = Account::find($value);
                    if ($account && $account->user_id !== $user->id) {
                        $fail('The selected account does not belong to you.');
                    }
                }
            ],
            'amount' => 'required|numeric|min:0.01',
            'type' => 'required|in:income,expense,transfer',
            'category_id' => [
                'nullable',
                'exists:categories,id',
                function ($attribute, $value, $fail) use ($request) {
                    if ($value) {
                        $category = Category::find($value);
                        if ($category) {
                            // Validate category type matches transaction type
                            $transactionType = $request->input('type');
                            if ($transactionType && $category->type !== $transactionType) {
                                $fail('The selected category type does not match the transaction type.');
                            }
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
            return redirect()->route('admin.transactions.edit', $transaction)
                ->withErrors($validator)
                ->withInput();
        }
        
        try {
            // Update the transaction using the service
            $transaction = $this->transactionService->updateTransaction($transaction, $request->all());
            
            return redirect()->route('admin.transactions.index')
                ->with('success', 'Transaction updated successfully.');
        } catch (\Exception $e) {
            return redirect()->route('admin.transactions.edit', $transaction)
                ->with('error', 'Error updating transaction: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Remove the specified transaction from storage.
     */
    public function destroy(Transaction $transaction)
    {
        // Authorize that the user can delete this transaction
        $user = Auth::user();
        $account = Account::find($transaction->account_id);
        
        if ($account->user_id !== $user->id) {
            abort(403, 'Unauthorized action.');
        }
        
        try {
            // Delete the transaction using the service
            $this->transactionService->deleteTransaction($transaction);
            
            return redirect()->route('admin.transactions.index')
                ->with('success', 'Transaction deleted successfully.');
        } catch (\Exception $e) {
            return redirect()->route('admin.transactions.index')
                ->with('error', 'Error deleting transaction: ' . $e->getMessage());
        }
    }

    /**
     * Display transaction statistics and analytics.
     */
    public function statistics(Request $request)
    {
        // Get the authenticated user
        $user = Auth::user();
        
        // Get user accounts
        $accounts = Account::where('user_id', $user->id)->get();
        $accountIds = $accounts->pluck('id')->toArray();
        
        // Get date range
        $startDate = $request->input('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->input('end_date', now()->format('Y-m-d'));
        $accountId = $request->input('account_id');
        
        // Validate that the account belongs to the user
        if ($accountId && !in_array($accountId, $accountIds)) {
            abort(403, 'Unauthorized action.');
        }        // Get statistics using the service
        $rawStats = $this->transactionService->getStatistics($startDate, $endDate, $accountId);
          // Format statistics for the view
        $stats = [
            'total_income' => $rawStats['total_income'],
            'total_expense' => $rawStats['total_expense'],
            'net' => $rawStats['net'],
            'income_formatted' => number_format($rawStats['total_income'], 2),
            'expense_formatted' => number_format($rawStats['total_expense'], 2),
            'balance_formatted' => number_format($rawStats['net'], 2),
            'transaction_count' => count($rawStats['categories']),
            'categories' => $rawStats['categories']
        ];
        
        // Calculate current total balance across all accounts or selected account
        if ($accountId) {
            $account = Account::find($accountId);
            $initialBalance = $account->initial_balance;
            $currentBalance = $account->getCurrentBalanceAttribute();
            $stats['initial_balance'] = $initialBalance;
            $stats['current_balance'] = $currentBalance;
            $stats['initial_balance_formatted'] = number_format($initialBalance, 2);
            $stats['current_balance_formatted'] = number_format($currentBalance, 2);
        } else {
            $initialTotalBalance = $accounts->sum('initial_balance');
            $currentTotalBalance = $accounts->sum(function($account) {
                return $account->getCurrentBalanceAttribute();
            });
            $stats['initial_balance'] = $initialTotalBalance;
            $stats['current_balance'] = $currentTotalBalance;
            $stats['initial_balance_formatted'] = number_format($initialTotalBalance, 2);
            $stats['current_balance_formatted'] = number_format($currentTotalBalance, 2);
        }
        
        // Get category breakdown for categorized transactions
        $categorizedBreakdown = DB::table('transactions')
            ->select('categories.name', 'transactions.type', DB::raw('SUM(transactions.amount) as total'))
            ->join('categories', 'transactions.category_id', '=', 'categories.id')
            ->whereIn('transactions.account_id', $accountIds)
            ->whereBetween('transactions.transaction_date', [$startDate, $endDate])
            ->groupBy('categories.name', 'transactions.type')
            ->get();
            
        // Get category breakdown for uncategorized transactions
        $uncategorizedBreakdown = DB::table('transactions')
            ->select(DB::raw("'Uncategorized' as name"), 'type', DB::raw('SUM(amount) as total'))
            ->whereNull('category_id')
            ->whereIn('account_id', $accountIds)
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->groupBy('type')
            ->get();
            
        // Combine both results
        $categoryBreakdown = $categorizedBreakdown->concat($uncategorizedBreakdown);
          // Get monthly trend - ensure we get data for past 12 months
        $monthlyTrend = DB::table('transactions')
            ->select(DB::raw('DATE_FORMAT(transaction_date, "%Y-%m") as month'), 
                    'type', 
                    DB::raw('SUM(amount) as total'))
            ->whereIn('account_id', $accountIds)
            ->whereDate('transaction_date', '>=', now()->subMonths(12))
            ->whereDate('transaction_date', '<=', now())
            ->groupBy('month', 'type')
            ->orderBy('month', 'asc')
            ->get();
        
        return view('admin.transactions.statistics', compact('stats', 'accounts', 'startDate', 'endDate', 'categoryBreakdown', 'monthlyTrend'));
    }
    /**
     * Process a transfer between accounts.
     */    
    public function processTransfer(Request $request)
    {
        // Prepare validation rules
        $rules = [
            'from_account_id' => 'required|exists:accounts,id|different:to_account_id',
            'to_account_id' => 'required|exists:accounts,id',
            'amount' => 'required|numeric|min:0.01',
            'description' => 'nullable|string|max:255',
            'transaction_date' => 'required|date',
            'use_real_time_rate' => 'boolean',
        ];
        
        // Get the authenticated user
        $user = Auth::user();
        
        // Verify account ownership
        $fromAccount = Account::find($request->from_account_id);
        $toAccount = Account::find($request->to_account_id);
        
        if (!$fromAccount || !$toAccount || $fromAccount->user_id !== $user->id || $toAccount->user_id !== $user->id) {
            abort(403, 'Unauthorized action.');
        }
        
        // Check if this is a multi-currency transfer
        $isCurrencyTransfer = $fromAccount->currency_id !== $toAccount->currency_id;
        
        // Add destination amount validation for currency transfers
        if ($isCurrencyTransfer && !($request->use_real_time_rate ?? false)) {
            $rules['destination_amount'] = 'required|numeric|min:0.01';
        }
        
        // Validate input
        $validator = Validator::make($request->all(), $rules);
        
        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }
        
        try {
            if ($isCurrencyTransfer) {
                if ($request->use_real_time_rate ?? false) {
                    // Use real-time exchange rate for different currency transfer
                    $result = $this->transactionService->createCurrencyTransferWithRealTimeRate([
                        'source_account_id' => $request->from_account_id,
                        'destination_account_id' => $request->to_account_id,
                        'source_amount' => $request->amount,
                        'description' => $request->description,
                        'transaction_date' => $request->transaction_date,
                        'is_reconciled' => $request->is_reconciled ?? false,
                    ]);
                    
                    $message = 'Currency transfer completed successfully using real-time exchange rate. Rate: 1 ' . 
                        $fromAccount->currency->code . ' = ' . 
                        number_format($result['exchange_rate'], 4) . ' ' . 
                        $toAccount->currency->code;
                } else {
                    // Manual exchange rate for different currency transfer
                    $result = $this->transactionService->createCurrencyTransfer([
                        'source_account_id' => $request->from_account_id,
                        'destination_account_id' => $request->to_account_id,
                        'source_amount' => $request->amount,
                        'destination_amount' => $request->destination_amount,
                        'description' => $request->description,
                        'transaction_date' => $request->transaction_date,
                        'is_reconciled' => $request->is_reconciled ?? false,
                    ]);
                    
                    $message = 'Currency transfer completed successfully. Rate: 1 ' . 
                        $fromAccount->currency->code . ' = ' . 
                        number_format($request->destination_amount / $request->amount, 4) . ' ' . 
                        $toAccount->currency->code;
                }
            } else {
                // Same currency transfer
                $result = $this->transactionService->createTransfer([
                    'source_account_id' => $request->from_account_id,
                    'destination_account_id' => $request->to_account_id,
                    'amount' => $request->amount,
                    'description' => $request->description,
                    'transaction_date' => $request->transaction_date,
                    'is_reconciled' => $request->is_reconciled ?? false,
                ]);
                
                $message = 'Transfer completed successfully.';
            }
            
            return redirect()->route('admin.transactions.index')
                ->with('success', $message);
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Error processing transfer: ' . $e->getMessage())
                ->withInput();
        }
    }
    
    /**
     * Create a transfer between accounts with the same currency.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function transfer(Request $request)
    {
        try {
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
            
            // Check if accounts have the same currency
            if ($sourceAccount->currency_id !== $destinationAccount->currency_id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Accounts have different currencies. Use currency-transfer endpoint instead.'
                ], 422);
            }
            
            // Prepare transfer data
            $transferData = [
                'source_account_id' => $sourceAccount->id,
                'destination_account_id' => $destinationAccount->id,
                'amount' => $request->amount,
                'description' => $request->description ?? 'Account transfer',
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
                    'source_account' => [
                        'id' => $sourceAccount->uuid,
                        'name' => $sourceAccount->name,
                        'new_balance' => $sourceAccount->getCurrentBalanceAttribute()
                    ],
                    'destination_account' => [
                        'id' => $destinationAccount->uuid,
                        'name' => $destinationAccount->name,
                        'new_balance' => $destinationAccount->getCurrentBalanceAttribute()
                    ]
                ]
            ], 201);
            
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Transfer failed: ' . $e->getMessage()
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
            
            // If using real-time exchange rate
            if ($request->use_real_time_rate ?? false) {
                $exchangeRateService = app(ExchangeRateService::class);
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
                    'outgoing' => new TransactionResource($result['outgoing']),
                    'incoming' => new TransactionResource($result['incoming']),
                    'exchange_rate' => $result['exchange_rate'],
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
            
            $fromCurrency = strtoupper($request->from_currency);
            $toCurrency = strtoupper($request->to_currency);
            $amount = $request->amount ?? 1;
            
            // Get the exchange rate service
            $exchangeRateService = app(ExchangeRateService::class);
            
            // Get the exchange rate
            $rate = $exchangeRateService->getRate($fromCurrency, $toCurrency);
            
            if ($rate === null) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Failed to get exchange rate for the specified currencies'
                ], 500);
            }
            
            $result = [
                'from_currency' => $fromCurrency,
                'to_currency' => $toCurrency,
                'exchange_rate' => $rate,
                'amount' => $amount,
                'updated_at' => now()->toISOString()
            ];
            
            // Calculate converted amount if provided
            if ($request->has('amount')) {
                $result['converted_amount'] = round($amount * $rate, 2);
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
    
    /**
     * Generate API token for authenticated user
     */
    public function generateApiToken()
    {
        $user = Auth::user();
        $token = $user->createToken('web-transaction-' . time());
        
        return response()->json([
            'token' => $token->plainTextToken
        ]);
    }
}
