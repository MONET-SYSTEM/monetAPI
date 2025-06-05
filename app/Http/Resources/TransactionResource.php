<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionResource extends JsonResource
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
            'account_id' => $this->whenLoaded('account', function() {
                return $this->account->uuid;
            }),
            'account_name' => $this->whenLoaded('account', function() {
                return $this->account->name;
            }),
            'category' => $this->whenLoaded('category', function() {
                return [
                    'id' => $this->category->uuid,
                    'name' => $this->category->name,
                    'type' => $this->category->type,
                    'colour_code' => $this->category->colour_code,
                    'icon' => $this->category->icon,
                ];
            }),
            'amount' => $this->amount,
            'amount_formatted' => $this->amount_text,
            'type' => $this->type,
            'description' => $this->description,
            'transaction_date' => $this->transaction_date->format('Y-m-d'),
            'is_reconciled' => $this->is_reconciled,
            'reference' => $this->reference,
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
        ];
    }
}
