<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReconHistory extends Model
{
    protected $fillable = [
        'user_id',
        'target',
        'status',
        'overall_score',
        'results',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'results' => 'array',
            'completed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
