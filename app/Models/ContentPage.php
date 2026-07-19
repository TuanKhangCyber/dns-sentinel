<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContentPage extends Model
{
    protected $fillable = ['slug', 'title', 'content', 'is_published', 'updated_by'];

    protected function casts(): array
    {
        return ['title' => 'array', 'content' => 'array', 'is_published' => 'boolean'];
    }

    public function translated(string $field): string
    {
        $values = $this->{$field} ?? [];

        return (string) ($values[app()->getLocale()] ?? $values['en'] ?? $values['vi'] ?? '');
    }
}
