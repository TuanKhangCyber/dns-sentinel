<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScanFinding extends Model
{
    protected $fillable = [
        'scan_id', 'scan_host_id', 'scan_port_id', 'plugin_id', 'title', 'severity',
        'cvss_score', 'cvss_vector', 'cve', 'description', 'evidence', 'solution',
        'references', 'status', 'first_seen_at', 'last_seen_at', 'raw_data',
    ];

    protected function casts(): array
    {
        return [
            'cve' => 'array', 'references' => 'array', 'raw_data' => 'array',
            'cvss_score' => 'decimal:1', 'first_seen_at' => 'datetime', 'last_seen_at' => 'datetime',
        ];
    }

    public function scan(): BelongsTo
    {
        return $this->belongsTo(Scan::class);
    }

    public function host(): BelongsTo
    {
        return $this->belongsTo(ScanHost::class, 'scan_host_id');
    }

    public function port(): BelongsTo
    {
        return $this->belongsTo(ScanPort::class, 'scan_port_id');
    }
}
