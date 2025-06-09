<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Models\Account;
use App\Models\Category;
use App\Services\CategoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class CategoryApiController extends Controller
{
    protected $categoryService;
    
    /**
     * Create a new controller instance.
     *
     * @param CategoryService $categoryService
     */
    public function __construct(CategoryService $categoryService)
    {
        $this->categoryService = $categoryService;
    }

    /**
     * Display a listing of categories.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            $filters = [
                'type' => $request->type,
                'search' => $request->search
            ];
            
            $categories = $this->categoryService->getCategories($filters);
            
            return response()->json([
                'status' => 'success',
                'data' => CategoryResource::collection($categories),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve categories',
                'error' => $e->getMessage()
            ], 500);
        }
    }    /**
     * Store a newly created category in storage.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        try {
            // Get the authenticated user
            $user = Auth::user();
            
            // Validate input
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:100',
                'icon' => 'nullable|string|max:50',
                'type' => 'required|in:income,expense,transfer',
                'colour_code' => 'nullable|string|max:20',
                'description' => 'nullable|string|max:255',
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            // Create the category using service
            $category = $this->categoryService->createCategory($request->all());
            
            return response()->json([
                'status' => 'success',
                'message' => 'Category created successfully',
                'data' => new CategoryResource($category)
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create category',
                'error' => $e->getMessage()
            ], 500);
        }
    }    /**
     * Display the specified category.
     *
     * @param string $uuid
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($uuid)
    {
        try {
            // Find the category by UUID using service
            $category = $this->categoryService->findByUuid($uuid);
            
            if (!$category) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Category not found'
                ], 404);
            }
            
            return response()->json([
                'status' => 'success',
                'data' => new CategoryResource($category)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve category',
                'error' => $e->getMessage()
            ], 500);
        }
    }    /**
     * Update the specified category in storage.
     *
     * @param Request $request
     * @param string $uuid
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $uuid)
    {
        try {
            // Find the category by UUID using service
            $category = $this->categoryService->findByUuid($uuid);
            
            if (!$category) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Category not found'
                ], 404);
            }
            
            // Check if this is a system category
            if ($category->is_system) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Cannot modify system categories'
                ], 403);
            }
            
            // Validate input
            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|required|string|max:100',
                'icon' => 'nullable|string|max:50',
                'type' => 'sometimes|required|in:income,expense,transfer',
                'colour_code' => 'nullable|string|max:20',
                'description' => 'nullable|string|max:255',
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            // Update the category using service
            $category = $this->categoryService->updateCategory($category, $request->all());
            
            return response()->json([
                'status' => 'success',
                'message' => 'Category updated successfully',
                'data' => new CategoryResource($category)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update category',
                'error' => $e->getMessage()
            ], 500);
        }
    }    /**
     * Remove the specified category from storage.
     *
     * @param string $uuid
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($uuid)
    {
        try {
            // Find the category by UUID using service
            $category = $this->categoryService->findByUuid($uuid);
            
            if (!$category) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Category not found'
                ], 404);
            }
            
            // Delete the category using service (will handle validation)
            $this->categoryService->deleteCategory($category);
            
            return response()->json([
                'status' => 'success',
                'message' => 'Category deleted successfully'
            ]);
        } catch (\Exception $e) {
            // Different error codes based on the exception message
            $statusCode = 500;
            if (strpos($e->getMessage(), 'Cannot delete system categories') !== false) {
                $statusCode = 403;
            } else if (strpos($e->getMessage(), 'Cannot delete category that is in use') !== false) {
                $statusCode = 409;
            }
            
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }    /**
     * Get transactions for a specific category.
     *
     * @param Request $request
     * @param string $uuid
     * @return \Illuminate\Http\JsonResponse
     */
    public function transactions(Request $request, $uuid)
    {
        try {
            // Get the authenticated user
            $user = Auth::user();
            
            // Find the category by UUID
            $category = $this->categoryService->findByUuid($uuid);
            
            if (!$category) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Category not found'
                ], 404);
            }
            
            // Validate account ownership if account_id is provided
            $accountId = $request->input('account_id');
            if ($accountId) {
                $account = Account::find($accountId);
                if (!$account || $account->user_id !== $user->id) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Unauthorized access to this account'
                    ], 403);
                }
            }
            
            // Prepare filters
            $filters = [
                'start_date' => $request->input('start_date'),
                'end_date' => $request->input('end_date'),
                'account_id' => $accountId
            ];
            
            $perPage = $request->input('per_page', 15);
              // Get transactions by category using the transaction service with user ID for security
            $transactionService = app(\App\Services\TransactionService::class);
            $transactions = $transactionService->getTransactionsByCategory($category->uuid, $filters, $perPage, $user->id);
            
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
                'message' => 'Failed to retrieve transactions for this category',
                'error' => $e->getMessage()
            ], 500);
        }
    }    /**
     * Get statistics for a specific category.
     *
     * @param Request $request
     * @param string $uuid
     * @return \Illuminate\Http\JsonResponse
     */
    public function statistics(Request $request, $uuid)
    {
        try {
            // Get the authenticated user
            $user = Auth::user();
            
            // Find the category by UUID
            $category = $this->categoryService->findByUuid($uuid);
            
            if (!$category) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Category not found'
                ], 404);
            }
            
            // Get date range and account ID
            $startDate = $request->input('start_date', now()->startOfMonth()->format('Y-m-d'));
            $endDate = $request->input('end_date', now()->format('Y-m-d'));
            $accountId = $request->input('account_id');
            
            // Validate account ownership if account_id is provided
            if ($accountId) {
                $account = Account::find($accountId);
                if (!$account || $account->user_id !== $user->id) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Unauthorized access to this account'
                    ], 403);
                }
            }
            
            // Get category statistics with user ID for security
            $transactionService = app(\App\Services\TransactionService::class);
            $statistics = $transactionService->getCategoryStatistics($category->uuid, $startDate, $endDate, $accountId, $user->id);
            
            return response()->json([
                'status' => 'success',
                'data' => $statistics
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve statistics for this category',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
