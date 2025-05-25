<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'name' => $this->name,
            'icon' => $this->icon,
            'type' => $this->type,
            'colour_code' => $this->colour_code,
            'description' => $this->description,            'is_system' => $this->is_system,
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
            // Include additional computed properties when requested
            'transaction_count' => $this->when($request->has('with_transaction_count') || $request->routeIs('api.category.transactions'), function () {
                return $this->transactions->count();
            }),
            'total_income' => $this->when($request->has('with_statistics'), function () {
                return $this->transactions()->where('type', 'income')->sum('amount');
            }),
            'total_expense' => $this->when($request->has('with_statistics'), function () {
                return $this->transactions()->where('type', 'expense')->sum('amount');
            }),
        ];
    }
}
