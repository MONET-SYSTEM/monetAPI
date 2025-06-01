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
     * Get the transfer where this transaction is the source.
     */
    public function outgoingTransfer()
    {
        return $this->hasOne(Transfer::class, 'source_transaction_id');
    }

    /**
     * Get the transfer where this transaction is the destination.
     */
    public function incomingTransfer()
    {
        return $this->hasOne(Transfer::class, 'destination_transaction_id');
    }

    /**
     * Get the related transfer, regardless of direction.
     */
    public function transfer()
    {
        return $this->outgoingTransfer()->orWhere(function($query) {
            $query->where('destination_transaction_id', $this->id);
        });
    }

    /**
     * Determine if this transaction is part of a transfer.
     */
    public function isTransfer(): bool
    {
        return $this->type === 'transfer';
    }

    /**
     * Determine if this transaction is outgoing (source) of a transfer.
     */
    public function isTransferOut(): bool
    {
        return $this->isTransfer() && $this->outgoingTransfer()->exists();
    }

    /**
     * Determine if this transaction is incoming (destination) of a transfer.
     */
    public function isTransferIn(): bool
    {
        return $this->isTransfer() && $this->incomingTransfer()->exists();
    }

    /**
     * Check if this transaction is a currency transfer
     */
    public function isCurrencyTransfer(): bool
    {
        if (!$this->isTransfer()) {
            return false;
        }
        
        $transfer = $this->outgoingTransfer ?? $this->incomingTransfer;
        return $transfer && !is_null($transfer->exchange_rate);
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
