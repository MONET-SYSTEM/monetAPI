<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BudgetResource extends JsonResource
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
            'uuid' => $this->uuid,
            'name' => $this->name,
            'description' => $this->description ?? '',
            'amount' => (float) $this->amount,
            'spent_amount' => (float) $this->spent_amount,
            'remaining_amount' => $this->remaining_amount,
            'spent_percentage' => round($this->spent_percentage, 2),
            'period' => $this->period,
            'start_date' => $this->start_date ? $this->start_date->format('Y-m-d') : null,
            'end_date' => $this->end_date ? $this->end_date->format('Y-m-d') : null,
            'status' => $this->status,
            'send_notifications' => $this->send_notifications,
            'notification_threshold' => $this->notification_threshold ?? 80,
            'color' => $this->color ?? '#007bff',
            'is_exceeded' => $this->is_exceeded,
            'days_remaining' => $this->end_date ? max(0, $this->end_date->diffInDays(now(), false)) : 0,
            'category' => new CategoryResource($this->whenLoaded('category')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
