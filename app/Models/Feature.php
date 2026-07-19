<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Feature extends Model
{
    protected $fillable = ['code', 'name', 'description', 'category', 'is_enabled', 'is_visible', 'credit_cost', 'sort_order'];

    protected function casts(): array
    {
        return ['is_enabled' => 'boolean', 'is_visible' => 'boolean', 'credit_cost' => 'integer', 'sort_order' => 'integer'];
    }

    public function plans(): BelongsToMany
    {
        return $this->belongsToMany(Plan::class)->withPivot(['is_enabled', 'credit_cost_override', 'usage_limit'])->withTimestamps();
    }
}
