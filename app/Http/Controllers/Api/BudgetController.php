<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Budget;
use App\Models\Account;
use App\Models\Category;
use App\Models\Currency;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Carbon\Carbon;

class BudgetController extends Controller
{
    /**
     * Get all budgets for the authenticated user.
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json([
                'message' => 'User not authenticated'
            ], 401);
        }

        $query = $user->budgets()->with(['account.account_type', 'category', 'currency']);

        // Filter by status
        if ($request->has('is_active')) {
            $isActive = filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN);
            $query->where('is_active', $isActive);
        }

        // Filter by period type
        if ($request->has('period_type')) {
            $query->where('period_type', $request->period_type);
        }

        // Filter by account
        if ($request->has('account_uuid')) {
            $account = Account::where('uuid', $request->account_uuid)->first();
            if ($account) {
                $query->where('account_id', $account->id);
            }
        }

        // Filter by category
        if ($request->has('category_uuid')) {
            $category = Category::where('uuid', $request->category_uuid)->first();
            if ($category) {
                $query->where('category_id', $category->id);
            }
        }

        // Filter by current budgets only
        if ($request->has('current_only') && filter_var($request->current_only, FILTER_VALIDATE_BOOLEAN)) {
            $query->current();
        }

        $perPage = $request->get('per_page', 15);
        $budgets = $query->latest()->paginate($perPage);

        return JsonResource::collection($budgets);
    }

    /**
     * Get a specific budget.
     */
    public function show($uuid)
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json([
                'message' => 'User not authenticated'
            ], 401);
        }

        $budget = $user->budgets()->with(['account.account_type', 'category', 'currency'])
                     ->where('uuid', $uuid)->firstOrFail();
        
        return new JsonResource($budget);
    }

    /**
     * Create a new budget.
     */
    public function store(Request $request)
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json([
                'message' => 'User not authenticated'
            ], 401);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'sometimes|string|max:1000',
            'account_uuid' => 'sometimes|nullable|string|exists:accounts,uuid',
            'category_uuid' => 'required|string|exists:categories,uuid',
            'currency_uuid' => 'required|string|exists:currencies,uuid',
            'amount' => 'required|numeric|min:0.01|max:999999999.99',
            'period_type' => ['required', Rule::in(['daily', 'weekly', 'monthly', 'quarterly', 'yearly'])],
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'required|date|after:start_date',
            'auto_renew' => 'sometimes|boolean',
            'alert_threshold' => 'sometimes|numeric|min:0|max:100',
            'alert_enabled' => 'sometimes|boolean',
        ]);

        // Get the related models
        $account = null;
        if (isset($validated['account_uuid'])) {
            $account = Account::where('uuid', $validated['account_uuid'])
                             ->where('user_id', Auth::id())
                             ->firstOrFail();
        }
        
        $category = Category::where('uuid', $validated['category_uuid'])->firstOrFail();
        $currency = Currency::where('uuid', $validated['currency_uuid'])->firstOrFail();

        // Check if budget already exists for this account/category/period
        $existingBudgetQuery = Budget::where('user_id', Auth::id())
            ->where('category_id', $category->id)
            ->where('start_date', '<=', $validated['end_date'])
            ->where('end_date', '>=', $validated['start_date'])
            ->where('is_active', true);

        if ($account) {
            $existingBudgetQuery->where('account_id', $account->id);
        } else {
            $existingBudgetQuery->whereNull('account_id');
        }

        $existingBudget = $existingBudgetQuery->first();

        if ($existingBudget) {
            return response()->json([
                'message' => 'A budget already exists for this ' . ($account ? 'account and ' : '') . 'category in the specified period.',
                'existing_budget' => new JsonResource($existingBudget)
            ], 422);
        }

        $budget = Budget::create([
            'user_id' => Auth::id(),
            'account_id' => $account ? $account->id : null,
            'category_id' => $category->id,
            'currency_id' => $currency->id,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'amount' => $validated['amount'],
            'period_type' => $validated['period_type'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'auto_renew' => $validated['auto_renew'] ?? false,
            'alert_threshold' => $validated['alert_threshold'] ?? 80.00,
            'alert_enabled' => $validated['alert_enabled'] ?? true,
        ]);

        // Calculate initial spent amount
        $budget->calculateSpentAmount();

        return response()->json([
            'data' => new JsonResource($budget->load(['account.account_type', 'category', 'currency']))
        ], 201);
    }

    /**
     * Update an existing budget.
     */
    public function update(Request $request, $uuid)
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json([
                'message' => 'User not authenticated'
            ], 401);
        }

        $budget = $user->budgets()->where('uuid', $uuid)->firstOrFail();

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'sometimes|nullable|string|max:1000',
            'account_uuid' => 'sometimes|nullable|string|exists:accounts,uuid',
            'category_uuid' => 'sometimes|string|exists:categories,uuid',
            'currency_uuid' => 'sometimes|string|exists:currencies,uuid',
            'amount' => 'sometimes|numeric|min:0.01|max:999999999.99',
            'period_type' => ['sometimes', Rule::in(['daily', 'weekly', 'monthly', 'quarterly', 'yearly'])],
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date|after:start_date',
            'is_active' => 'sometimes|boolean',
            'auto_renew' => 'sometimes|boolean',
            'alert_threshold' => 'sometimes|numeric|min:0|max:100',
            'alert_enabled' => 'sometimes|boolean',
        ]);

        // Handle related model updates
        if (isset($validated['account_uuid'])) {
            if ($validated['account_uuid'] === null) {
                $validated['account_id'] = null;
            } else {
                $account = Account::where('uuid', $validated['account_uuid'])
                                 ->where('user_id', Auth::id())
                                 ->firstOrFail();
                $validated['account_id'] = $account->id;
            }
            unset($validated['account_uuid']);
        }

        if (isset($validated['category_uuid'])) {
            $category = Category::where('uuid', $validated['category_uuid'])->firstOrFail();
            $validated['category_id'] = $category->id;
            unset($validated['category_uuid']);
        }

        if (isset($validated['currency_uuid'])) {
            $currency = Currency::where('uuid', $validated['currency_uuid'])->firstOrFail();
            $validated['currency_id'] = $currency->id;
            unset($validated['currency_uuid']);
        }

        $budget->update($validated);

        // Recalculate spent amount if account or category changed
        if (isset($validated['account_id']) || isset($validated['category_id'])) {
            $budget->calculateSpentAmount();
        }

        return response()->json([
            'data' => new JsonResource($budget->load(['account.account_type', 'category', 'currency']))
        ], 200);
    }

    /**
     * Delete a budget.
     */
    public function destroy($uuid)
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json([
                'message' => 'User not authenticated'
            ], 401);
        }

        $budget = $user->budgets()->where('uuid', $uuid)->firstOrFail();
        
        $budget->delete();

        return response()->json([
            'message' => 'Budget deleted successfully'
        ], 200);
    }

    /**
     * Recalculate spent amount for a budget.
     */
    public function recalculate($uuid)
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json([
                'message' => 'User not authenticated'
            ], 401);
        }

        $budget = $user->budgets()->where('uuid', $uuid)->firstOrFail();
        
        $spentAmount = $budget->calculateSpentAmount();

        return response()->json([
            'message' => 'Budget recalculated successfully',
            'spent_amount' => $spentAmount,
            'budget' => [
                'data' => new JsonResource($budget->load(['account.account_type', 'category', 'currency']))
            ]
        ], 200);
    }

    /**
     * Get budget statistics and analytics.
     */
    public function statistics(Request $request)
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json([
                'message' => 'User not authenticated'
            ], 401);
        }
        
        $query = $user->budgets();

        // Filter by period if provided
        if ($request->has('period_type')) {
            $query->where('period_type', $request->period_type);
        }

        $budgets = $query->with(['account', 'category', 'currency'])->get();

        $stats = [
            'total_budgets' => $budgets->count(),
            'active_budgets' => $budgets->where('is_active', true)->count(),
            'current_budgets' => $budgets->filter(function ($budget) {
                return method_exists($budget, 'isCurrentlyActive') ? $budget->isCurrentlyActive() : false;
            })->count(),
            'over_budget_count' => $budgets->filter(function ($budget) {
                return method_exists($budget, 'isOverBudget') ? $budget->isOverBudget() : false;
            })->count(),
            'over_threshold_count' => $budgets->filter(function ($budget) {
                return method_exists($budget, 'isOverThreshold') ? $budget->isOverThreshold() : false;
            })->count(),
            'total_budgeted_amount' => $budgets->sum('amount'),
            'total_spent_amount' => $budgets->sum('spent_amount'),
            'total_remaining_amount' => $budgets->sum('remaining_amount'),
            'average_spent_percentage' => $budgets->avg('spent_percentage'),
            'by_period_type' => $budgets->groupBy('period_type')->map(function ($group) {
                return [
                    'count' => $group->count(),
                    'total_amount' => $group->sum('amount'),
                    'total_spent' => $group->sum('spent_amount'),
                ];
            }),
            'by_category' => $budgets->groupBy('category.name')->map(function ($group) {
                return [
                    'count' => $group->count(),
                    'total_amount' => $group->sum('amount'),
                    'total_spent' => $group->sum('spent_amount'),
                ];
            }),
        ];

        return response()->json($stats);
    }

    /**
     * Get budget alerts (over threshold or over budget).
     */
    public function alerts()
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json([
                'message' => 'User not authenticated'
            ], 401);
        }

        $budgetsQuery = $user->budgets()
                           ->with(['account.account_type', 'category', 'currency']);
        
        // Add method existence checks for model scopes
        if (method_exists(Budget::class, 'scopeActive')) {
            $budgetsQuery->active();
        } else {
            $budgetsQuery->where('is_active', true);
        }

        if (method_exists(Budget::class, 'scopeCurrent')) {
            $budgetsQuery->current();
        } else {
            $budgetsQuery->where('start_date', '<=', now())
                        ->where('end_date', '>=', now());
        }

        $budgets = $budgetsQuery->get();

        $alerts = $budgets->filter(function ($budget) {
            $isOverThreshold = method_exists($budget, 'isOverThreshold') ? $budget->isOverThreshold() : false;
            $isOverBudget = method_exists($budget, 'isOverBudget') ? $budget->isOverBudget() : false;
            return $isOverThreshold || $isOverBudget;
        });

        return JsonResource::collection($alerts);
    }

    /**
     * Quick create budget with smart defaults.
     */
    public function quickCreate(Request $request)
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json([
                'message' => 'User not authenticated'
            ], 401);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'account_uuid' => 'sometimes|nullable|string|exists:accounts,uuid',
            'category_uuid' => 'required|string|exists:categories,uuid',
            'amount' => 'required|numeric|min:0.01|max:999999999.99',
            'period_type' => ['sometimes', Rule::in(['daily', 'weekly', 'monthly', 'quarterly', 'yearly'])],
        ]);

        // Get the account if provided, otherwise use default currency
        $account = null;
        $currencyId = null;
        
        if (isset($validated['account_uuid'])) {
            $account = Account::where('uuid', $validated['account_uuid'])
                             ->where('user_id', Auth::id())
                             ->with('currency')
                             ->firstOrFail();
            $currencyId = $account->currency_id;
        } else {
            // Use default currency (typically the first currency or user's preferred currency)
            $defaultCurrency = Currency::first();
            if (!$defaultCurrency) {
                return response()->json([
                    'message' => 'No currency available. Please create a currency first.'
                ], 422);
            }
            $currencyId = $defaultCurrency->id;
        }

        $category = Category::where('uuid', $validated['category_uuid'])->firstOrFail();

        $periodType = $validated['period_type'] ?? 'monthly';

        // Calculate start and end dates based on period type
        $startDate = Carbon::now();
        switch ($periodType) {
            case 'daily':
                $startDate = $startDate->startOfDay();
                $endDate = $startDate->copy()->endOfDay();
                break;
            case 'weekly':
                $startDate = $startDate->startOfWeek();
                $endDate = $startDate->copy()->endOfWeek();
                break;
            case 'quarterly':
                $startDate = $startDate->startOfQuarter();
                $endDate = $startDate->copy()->endOfQuarter();
                break;
            case 'yearly':
                $startDate = $startDate->startOfYear();
                $endDate = $startDate->copy()->endOfYear();
                break;
            default: // monthly
                $startDate = $startDate->startOfMonth();
                $endDate = $startDate->copy()->endOfMonth();
                break;
        }

        $budget = Budget::create([
            'user_id' => Auth::id(),
            'account_id' => $account ? $account->id : null,
            'category_id' => $category->id,
            'currency_id' => $currencyId,
            'name' => $validated['name'],
            'amount' => $validated['amount'],
            'period_type' => $periodType,
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'auto_renew' => true, // Default for quick create
        ]);

        // Calculate initial spent amount
        if (method_exists($budget, 'calculateSpentAmount')) {
            $budget->calculateSpentAmount();
        }

        return response()->json([
            'data' => new BudgetResource($budget->load(['account.account_type', 'category', 'currency']))
        ], 201);
    }

    /**
     * Test authentication endpoint for debugging.
     */
    public function test()
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json([
                'message' => 'User not authenticated',
                'auth_user' => null,
                'headers' => request()->headers->all(),
                'bearer_token' => request()->bearerToken()
            ], 401);
        }

        return response()->json([
            'message' => 'Authentication successful',
            'user_id' => $user->id,
            'user_email' => $user->email,
            'auth_guard' => Auth::getDefaultDriver()
        ]);
    }
}
