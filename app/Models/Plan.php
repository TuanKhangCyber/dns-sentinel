<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    protected $fillable = ['code', 'name', 'description', 'is_active', 'monthly_credit_allowance', 'history_retention_days'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'monthly_credit_allowance' => 'integer', 'history_retention_days' => 'integer'];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function features(): BelongsToMany
    {
        return $this->belongsToMany(Feature::class)->withPivot(['is_enabled', 'credit_cost_override', 'usage_limit'])->withTimestamps();
    }
}
