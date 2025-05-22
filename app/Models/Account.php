<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Account extends BaseModel
{
    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'account_type_id',
        'currency_id',
        'name',
        'initial_balance',
        'current_balance',
        'colour_code',
        'active',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'initial_balance' => 'double',
            'current_balance' => 'double',
            'active' => 'boolean',
        ];
    }

    public function account_type(): BelongsTo
    {
        return $this->belongsTo(AccountType::class, 'account_type_id');
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_id');
    }

    public function getInitialBalanceTextAttribute(): string
    {
        return $this->currency->format($this->initial_balance);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function getCurrentBalanceAttribute(): float
    {
        $income = $this->transactions()->where('type', 'income')->sum('amount') ?? 0;
        $expense = $this->transactions()->where('type', 'expense')->sum('amount') ?? 0;
        
        // Get transfers in (money received from other accounts)
        $transfersIn = Transaction::where('type', 'transfer')
            ->where('account_id', $this->id)
            ->where('reference', 'LIKE', 'TRANSFER-IN%')
            ->sum('amount') ?? 0;
            
        // Get transfers out (money sent to other accounts)
        $transfersOut = Transaction::where('type', 'transfer')
            ->where('account_id', $this->id)
            ->where('reference', 'LIKE', 'TRANSFER-OUT%')
            ->sum('amount') ?? 0;
        
        return $this->initial_balance + $income + $transfersIn - $expense - $transfersOut;
    }

    public function getCurrentBalanceTextAttribute(): string
    {
        return $this->currency->format($this->current_balance);
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}