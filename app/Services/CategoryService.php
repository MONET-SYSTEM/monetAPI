<?php

namespace App\Services;

use App\Models\Category;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class CategoryService
{
    /**
     * Get a paginated list of categories with optional filtering.
     *
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getCategories(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Category::query();
        
        // Apply type filter if provided
        if (isset($filters['type']) && $filters['type']) {
            $query->where('type', $filters['type']);
        }
        
        // Apply search filter if provided
        if (isset($filters['search']) && $filters['search']) {
            $query->where(function($q) use ($filters) {
                $q->where('name', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('description', 'like', '%' . $filters['search'] . '%');
            });
        }
        
        // Apply ordering
        $query->orderBy('name', 'asc');
        
        return $query->paginate($perPage);
    }
    
    /**
     * Find a category by UUID.
     *
     * @param string $uuid
     * @return Category|null
     */
    public function findByUuid(string $uuid): ?Category
    {
        return Category::where('uuid', $uuid)->first();
    }
    
    /**
     * Create a new category.
     *
     * @param array $data
     * @return Category
     */
    public function createCategory(array $data): Category
    {
        $category = new Category();
        $category->uuid = (string) Str::uuid();
        $category->name = $data['name'];
        $category->icon = $data['icon'] ?? null;
        $category->type = $data['type'];
        $category->colour_code = $data['colour_code'] ?? null;
        $category->description = $data['description'] ?? null;
        $category->is_system = false; // User-created categories are not system categories
        $category->save();
        
        return $category;
    }
    
    /**
     * Update an existing category.
     *
     * @param Category $category
     * @param array $data
     * @return Category
     */
    public function updateCategory(Category $category, array $data): Category
    {
        if (isset($data['name'])) {
            $category->name = $data['name'];
        }
        
        if (isset($data['icon'])) {
            $category->icon = $data['icon'];
        }
        
        if (isset($data['type'])) {
            $category->type = $data['type'];
        }
        
        if (isset($data['colour_code'])) {
            $category->colour_code = $data['colour_code'];
        }
        
        if (isset($data['description'])) {
            $category->description = $data['description'];
        }
        
        $category->save();
        
        return $category;
    }
    
    /**
     * Delete a category.
     *
     * @param Category $category
     * @return bool
     * @throws \Exception If the category is a system category or has transactions
     */
    public function deleteCategory(Category $category): bool
    {
        if ($category->is_system) {
            throw new \Exception("Cannot delete system categories");
        }
        
        $transactionCount = $category->transactions()->count();
        if ($transactionCount > 0) {
            throw new \Exception("Cannot delete category that is in use by {$transactionCount} transactions");
        }
        
        return $category->delete();
    }
    
    /**
     * Get all income categories.
     *
     * @return Collection
     */
    public function getIncomeCategories(): Collection
    {
        return Category::where('type', 'income')->orderBy('name', 'asc')->get();
    }
    
    /**
     * Get all expense categories.
     *
     * @return Collection
     */
    public function getExpenseCategories(): Collection
    {
        return Category::where('type', 'expense')->orderBy('name', 'asc')->get();
    }
    
    /**
     * Check if a category is in use by any transaction.
     *
     * @param Category $category
     * @return bool
     */
    public function isCategoryInUse(Category $category): bool
    {
        return $category->transactions()->exists();
    }
}
