<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Budget extends Model
{
    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'uuid',
        'user_id',
        'category_id',
        'name',
        'description',
        'amount',
        'spent_amount',
        'period',
        'start_date',
        'end_date',
        'status',
        'send_notifications',
        'notification_threshold',
        'color'
    ];

    /**
     * The model's default values for attributes.
     *
     * @var array
     */
    protected $attributes = [
        'spent_amount' => 0.00,
        'period' => 'monthly',
        'status' => 'active',
        'send_notifications' => true,
        'notification_threshold' => 80,
        'color' => '#007bff',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'spent_amount' => 'decimal:2',
            'start_date' => 'date',
            'end_date' => 'date',
            'send_notifications' => 'boolean',
            'notification_threshold' => 'integer',
        ];
    }

    /**
     * The "booted" method of the model.
     *
     * @return void
     */
    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });

        static::updated(function ($budget) {
            // Check if spent_amount was updated and notifications are enabled
            if ($budget->wasChanged('spent_amount') && $budget->send_notifications) {
                $service = app(\App\Services\BudgetNotificationService::class);
                $service->checkBudgetThreshold($budget);
            }
        });
    }

    /**
     * Get the user that owns the budget.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the category associated with the budget.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Get the transactions for this budget's category within the budget period.
     */
    public function transactions()
    {
        return Transaction::whereHas('account', function ($query) {
                $query->where('user_id', $this->user_id);
            })
            ->when($this->category_id, function ($query) {
                return $query->where('category_id', $this->category_id);
            })
            ->where('type', 'expense')
            ->whereBetween('transaction_date', [$this->start_date, $this->end_date]);
    }

    /**
     * Get the remaining amount for this budget.
     */
    public function getRemainingAmountAttribute(): float
    {
        return max(0, $this->amount - $this->spent_amount);
    }

    /**
     * Get the spent percentage for this budget.
     */
    public function getSpentPercentageAttribute(): float
    {
        if ($this->amount == 0) {
            return 0;
        }
        return min(100, ($this->spent_amount / $this->amount) * 100);
    }

    /**
     * Check if budget is exceeded.
     */
    public function getIsExceededAttribute(): bool
    {
        return $this->spent_amount > $this->amount;
    }

    /**
     * Check if budget should send notification.
     */
    public function shouldSendNotification(): bool
    {
        return $this->send_notifications && 
               $this->spent_percentage >= $this->notification_threshold;
    }

    /**
     * Update spent amount based on transactions.
     */
    public function updateSpentAmount(): void
    {
        $spentAmount = $this->transactions()->sum('amount');
        $this->update(['spent_amount' => $spentAmount]);
        
        // Update status based on spending
        if ($this->spent_amount > $this->amount) {
            $this->update(['status' => 'exceeded']);
        } elseif ($this->end_date < now()) {
            $this->update(['status' => 'completed']);
        }
    }
}
