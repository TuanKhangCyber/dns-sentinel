<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ScanHost extends Model
{
    protected $fillable = [
        'scan_id', 'ip_address', 'hostname', 'status', 'mac_address', 'vendor',
        'operating_system', 'os_accuracy', 'response_time', 'raw_data',
    ];

    protected function casts(): array
    {
        return ['raw_data' => 'array', 'os_accuracy' => 'integer', 'response_time' => 'integer'];
    }

    public function scan(): BelongsTo
    {
        return $this->belongsTo(Scan::class);
    }

    public function ports(): HasMany
    {
        return $this->hasMany(ScanPort::class);
    }

    public function findings(): HasMany
    {
        return $this->hasMany(ScanFinding::class);
    }
}
