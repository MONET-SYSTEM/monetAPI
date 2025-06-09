<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\BudgetResource;
use App\Models\Budget;
use App\Models\Category;
use App\Services\BudgetService;
use App\Services\BudgetNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class BudgetApiController extends Controller
{
    protected $budgetService;
    protected $notificationService;

    public function __construct(BudgetService $budgetService, BudgetNotificationService $notificationService)
    {
        $this->budgetService = $budgetService;
        $this->notificationService = $notificationService;
    }

    /**
     * Display a listing of budgets.
     */
    public function index(Request $request)
    {
        try {
            $user = Auth::user();
            
            $query = Budget::where('user_id', $user->id)
                ->with(['category']);

            // Apply filters
            if ($request->has('status') && $request->status) {
                $query->where('status', $request->status);
            }

            if ($request->has('period') && $request->period) {
                $query->where('period', $request->period);
            }

            if ($request->has('category_id') && $request->category_id) {
                $query->where('category_id', $request->category_id);
            }

            $budgets = $query->orderBy('created_at', 'desc')
                ->paginate($request->get('per_page', 15));

            return response()->json([
                'status' => 'success',
                'data' => BudgetResource::collection($budgets),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve budgets',
                'error' => $e->getMessage()
            ], 500);
        }
    }    /**
     * Store a newly created budget.
     */
    public function store(Request $request)
    {
        try {            
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'amount' => 'required|numeric|min:0.01',
                'category_id' => [
                    'nullable',
                    'string',
                    function ($attribute, $value, $fail) {
                        if ($value) {
                            // Check if category exists by UUID or ID
                            $category = Category::where('uuid', $value)
                                ->orWhere('id', $value)
                                ->first();
                            
                            if (!$category) {
                                $fail('The selected category does not exist.');
                            }
                        }
                    }
                ],
                'period' => 'required|in:daily,weekly,monthly,quarterly,yearly',
                'start_date' => 'required|date',
                'end_date' => 'required|date|after_or_equal:start_date',
                'send_notifications' => 'boolean',
                'notification_threshold' => 'integer|min:1|max:100',
                'color' => 'string|max:7',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }            $data = $validator->validated();
            $data['user_id'] = Auth::id();

            // Convert category UUID to ID if provided
            if (isset($data['category_id'])) {
                $category = Category::where('uuid', $data['category_id'])
                    ->orWhere('id', $data['category_id'])
                    ->first();
                
                if ($category) {
                    $data['category_id'] = $category->id;
                } else {
                    unset($data['category_id']); // Remove if not found
                }
            }

            $budget = $this->budgetService->createBudget($data);

            // Send notification for budget creation
            $this->notificationService->sendBudgetCreatedNotification($budget);

            return response()->json([
                'status' => 'success',
                'message' => 'Budget created successfully',
                'data' => new BudgetResource($budget)
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create budget',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified budget.
     */
    public function show(string $uuid)
    {
        try {
            $budget = Budget::where('uuid', $uuid)
                ->where('user_id', Auth::id())
                ->with(['category'])
                ->firstOrFail();

            return response()->json([
                'status' => 'success',
                'data' => new BudgetResource($budget)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Budget not found'
            ], 404);
        }
    }    /**
     * Update the specified budget.
     */
    public function update(Request $request, string $uuid)
    {
        try {
            $budget = Budget::where('uuid', $uuid)
                ->where('user_id', Auth::id())
                ->firstOrFail();            $validator = Validator::make($request->all(), [
                'name' => 'string|max:255',
                'description' => 'nullable|string',
                'amount' => 'numeric|min:0.01',
                'category_id' => [
                    'nullable',
                    'string',
                    function ($attribute, $value, $fail) {
                        if ($value) {
                            // Check if category exists by UUID or ID
                            $category = Category::where('uuid', $value)
                                ->orWhere('id', $value)
                                ->first();
                            
                            if (!$category) {
                                $fail('The selected category does not exist.');
                            }
                        }
                    }
                ],
                'period' => 'in:daily,weekly,monthly,quarterly,yearly',
                'start_date' => 'date',
                'end_date' => 'date|after_or_equal:start_date',
                'status' => 'in:active,inactive,completed,exceeded',
                'send_notifications' => 'boolean',
                'notification_threshold' => 'integer|min:1|max:100',
                'color' => 'string|max:7',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }            $data = $validator->validated();

            // Convert category UUID to ID if provided
            if (isset($data['category_id'])) {
                $category = Category::where('uuid', $data['category_id'])
                    ->orWhere('id', $data['category_id'])
                    ->first();
                
                if ($category) {
                    $data['category_id'] = $category->id;
                } else {
                    unset($data['category_id']); // Remove if not found
                }
            }

            $budget = $this->budgetService->updateBudget($budget, $data);

            // Send notification for budget update
            $this->notificationService->sendBudgetUpdatedNotification($budget);

            return response()->json([
                'status' => 'success',
                'message' => 'Budget updated successfully',
                'data' => new BudgetResource($budget)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update budget',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified budget.
     */
    public function destroy(string $uuid)
    {
        try {
            $budget = Budget::where('uuid', $uuid)
                ->where('user_id', Auth::id())
                ->firstOrFail();

            $budgetName = $budget->name;
            $userId = $budget->user_id;
            
            $budget->delete();

            // Send notification for budget deletion
            $this->notificationService->sendBudgetDeletedNotification($budgetName, $userId);

            return response()->json([
                'status' => 'success',
                'message' => 'Budget deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete budget'
            ], 500);
        }
    }

    /**
     * Get budget statistics.
     */
    public function statistics()
    {
        try {
            $user = Auth::user();
            $statistics = $this->budgetService->getBudgetStatistics($user->id);

            return response()->json([
                'status' => 'success',
                'data' => $statistics
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve budget statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get budget performance data.
     */
    public function performance(string $uuid)
    {
        try {
            $budget = Budget::where('uuid', $uuid)
                ->where('user_id', Auth::id())
                ->firstOrFail();

            $performance = $this->budgetService->getBudgetPerformance($budget);

            return response()->json([
                'status' => 'success',
                'data' => $performance
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve budget performance',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}