<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class Currency extends BaseModel
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'code',
        'symbol',
        'symbol_position',
        'thousand_separator',
        'decimal_separator',
        'decimal_places',
        'active',
    ];

    public function getSampleAttribute(): string
    {
        return $this->format(1000);
    }

    public function format($value): string
    {
        $value = number_format($value, $this->decimal_places, $this->decimal_separator, $this->thousand_separator);

        return ($this->symbol_position == 'after')
            ? ($value . ' ' . $this->symbol)
            : ($this->symbol . ' ' . $value);
    }

    /**
     * Get all accounts that use this currency
     */
    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class);
    }
}
