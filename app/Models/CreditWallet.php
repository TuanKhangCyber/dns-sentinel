<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditWallet extends Model
{
    protected $fillable = ['user_id', 'balance', 'last_monthly_reset_at'];

    protected function casts(): array
    {
        return ['balance' => 'integer', 'last_monthly_reset_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
