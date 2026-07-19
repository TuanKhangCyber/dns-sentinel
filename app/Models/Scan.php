<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Scan extends Model
{
    public const TERMINAL_STATUSES = ['completed', 'failed', 'cancelled'];

    protected $fillable = [
        'user_id', 'credit_transaction_id', 'target', 'target_type', 'profile', 'status', 'stage', 'progress',
        'scanner_type', 'scheduled_at', 'started_at', 'completed_at', 'cancel_requested_at',
        'authorization_confirmed_at', 'duration_seconds', 'error_message', 'options', 'summary',
    ];

    protected function casts(): array
    {
        return [
            'options' => 'array', 'summary' => 'array', 'progress' => 'integer',
            'scheduled_at' => 'datetime', 'started_at' => 'datetime', 'completed_at' => 'datetime',
            'cancel_requested_at' => 'datetime', 'authorization_confirmed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function creditTransaction(): BelongsTo
    {
        return $this->belongsTo(CreditTransaction::class);
    }

    public function hosts(): HasMany
    {
        return $this->hasMany(ScanHost::class);
    }

    public function findings(): HasMany
    {
        return $this->hasMany(ScanFinding::class);
    }

    public function ports(): HasManyThrough
    {
        return $this->hasManyThrough(ScanPort::class, ScanHost::class);
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, self::TERMINAL_STATUSES, true);
    }
}
