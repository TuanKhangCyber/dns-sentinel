<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    protected $fillable = ['key', 'value', 'type', 'is_public', 'updated_by'];

    protected function casts(): array
    {
        return ['is_public' => 'boolean'];
    }
}
