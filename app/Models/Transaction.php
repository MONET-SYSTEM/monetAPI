<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transaction extends BaseModel
{
    use SoftDeletes;    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */    
    protected $fillable = [
        'account_id',
        'category_id',
        'amount',
        'type',
        'description',
        'transaction_date',
        'is_reconciled',
        'reference'
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'double',
            'transaction_date' => 'date',
            'is_reconciled' => 'boolean',
        ];
    }

    /**
     * The attributes that should be appended to the model.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'amount_text',
    ];    /**
     * Get the account that owns the transaction.
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    /**
     * Get the category that the transaction belongs to.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    /**
     * Get the attachments for this transaction.
     */
    public function attachments()
    {
        return $this->hasMany(TransactionAttachment::class);
    }

    /**
     * Get formatted amount with currency symbol
     */
    public function getAmountTextAttribute(): string
    {
        if ($this->account && $this->account->currency) {
            return $this->account->currency->format($this->amount);
        }
        
        return number_format($this->amount, 2);
    }

    /**
     * Scope a query to only include income transactions.
     */
    public function scopeIncome($query)
    {
        return $query->where('type', 'income');
    }

    /**
     * Scope a query to only include expense transactions.
     */
    public function scopeExpense($query)
    {
        return $query->where('type', 'expense');
    }

    /**
     * Scope a query to only include transfer transactions.
     */
    public function scopeTransfer($query)
    {
        return $query->where('type', 'transfer');
    }

    /**
     * Get route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * Check if this transaction is a transfer
     *
     * @return bool
     */
    public function isTransfer(): bool
    {
        return $this->type === 'transfer';
    }

    /**
     * Check if this transaction is a transfer out (money leaving account)
     *
     * @return bool
     */
    public function isTransferOut(): bool
    {
        return $this->isTransfer() && str_starts_with($this->reference ?? '', 'TRANSFER-OUT');
    }

    /**
     * Check if this transaction is a transfer in (money entering account)
     *
     * @return bool
     */
    public function isTransferIn(): bool
    {
        return $this->isTransfer() && str_starts_with($this->reference ?? '', 'TRANSFER-IN');
    }

    /**
     * Check if this transaction is a currency transfer
     *
     * @return bool
     */
    public function isCurrencyTransfer(): bool
    {
        return $this->isTransfer() && str_contains($this->reference ?? '', 'FX-TRANSFER');
    }

    /**
     * Get the transfer direction label
     *
     * @return string
     */
    public function getTransferDirectionLabel(): string
    {
        if ($this->isTransferOut()) {
            return 'Out';
        } elseif ($this->isTransferIn()) {
            return 'In';
        }
        return '';
    }
}
