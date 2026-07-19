<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ScanPort extends Model
{
    protected $fillable = [
        'scan_host_id', 'port', 'protocol', 'state', 'service', 'product',
        'version', 'extra_info', 'cpe', 'banner',
    ];

    protected function casts(): array
    {
        return ['port' => 'integer'];
    }

    public function host(): BelongsTo
    {
        return $this->belongsTo(ScanHost::class, 'scan_host_id');
    }

    public function findings(): HasMany
    {
        return $this->hasMany(ScanFinding::class);
    }
}
