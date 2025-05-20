<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Services\TransactionService;
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
    }

    /**
     * Show the form for creating a new transaction.
     */
    public function create()
    {
        // Get the authenticated user
        $user = Auth::user();
        
        // Get user accounts
        $accounts = Account::where('user_id', $user->id)->get();
        
        // Get categories
        $categories = Category::all();
        
        return view('admin.transactions.create', compact('accounts', 'categories'));
    }

    /**
     * Store a newly created transaction in storage.
     */
    public function store(Request $request)
    {
        // Validate input
        $validator = Validator::make($request->all(), [
            'account_id' => 'required|exists:accounts,id',
            'amount' => 'required|numeric|min:0.01',
            'type' => 'required|in:income,expense,transfer',
            'category_id' => 'nullable|exists:categories,id',
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
            abort(403, 'Unauthorized action.');
        }
        
        // Load relationships
        $transaction->load(['account', 'category', 'attachments']);
        
        return view('admin.transactions.show', compact('transaction'));
    }

    /**
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
        
        // Get user accounts
        $accounts = Account::where('user_id', $user->id)->get();
        
        // Get categories
        $categories = Category::all();
        
        return view('admin.transactions.edit', compact('transaction', 'accounts', 'categories'));
    }

    /**
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
        
        // Validate input
        $validator = Validator::make($request->all(), [
            'account_id' => 'required|exists:accounts,id',
            'amount' => 'required|numeric|min:0.01',
            'type' => 'required|in:income,expense,transfer',
            'category_id' => 'nullable|exists:categories,id',
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
        }
        
        // Get statistics using the service
        $stats = $this->transactionService->getStatistics($startDate, $endDate, $accountId);
        
        // Get category breakdown
        $categoryBreakdown = DB::table('transactions')
            ->select('categories.name', 'transactions.type', DB::raw('SUM(transactions.amount) as total'))
            ->join('categories', 'transactions.category_id', '=', 'categories.id')
            ->whereIn('transactions.account_id', $accountIds)
            ->whereBetween('transactions.transaction_date', [$startDate, $endDate])
            ->groupBy('categories.name', 'transactions.type')
            ->get();
        
        // Get monthly trend
        $monthlyTrend = DB::table('transactions')
            ->select(DB::raw('DATE_FORMAT(transaction_date, "%Y-%m") as month'), 
                    'type', 
                    DB::raw('SUM(amount) as total'))
            ->whereIn('account_id', $accountIds)
            ->whereDate('transaction_date', '>=', now()->subMonths(12))
            ->groupBy('month', 'type')
            ->orderBy('month')
            ->get();
        
        return view('admin.transactions.statistics', compact('stats', 'accounts', 'startDate', 'endDate', 'categoryBreakdown', 'monthlyTrend'));
    }
}
