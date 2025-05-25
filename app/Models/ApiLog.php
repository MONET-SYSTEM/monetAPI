<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ApiLog extends Model
{
    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'request_id',
        'method',
        'url',
        'ip_address',
        'user_agent',
        'request_payload',
        'response_code',
        'response_body',
        'user_id',
        'duration',
        'status'
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'request_payload' => 'array',
        'response_body' => 'array',
        'duration' => 'float',
    ];

    /**
     * The possible statuses for an API log.
     */
    const STATUS_SUCCESS = 'success';
    const STATUS_ERROR = 'error';
    const STATUS_WARNING = 'warning';

    /**
     * Get the user that made the API request.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope a query to only include successful API calls.
     */
    public function scopeSuccessful($query)
    {
        return $query->where('status', self::STATUS_SUCCESS);
    }

    /**
     * Scope a query to only include failed API calls.
     */
    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_ERROR);
    }

    /**
     * Scope a query to only include API calls with warning.
     */
    public function scopeWarning($query)
    {
        return $query->where('status', self::STATUS_WARNING);
    }

    /**
     * Get the duration in human-readable format.
     */
    public function getHumanDurationAttribute()
    {
        if ($this->duration < 1) {
            return round($this->duration * 1000) . ' ms';
        }
        
        return round($this->duration, 2) . ' s';
    }
}
