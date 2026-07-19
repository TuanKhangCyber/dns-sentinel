<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FaqItem extends Model
{
    protected $fillable = ['code', 'question', 'answer', 'category', 'sort_order', 'is_published', 'updated_by'];

    protected function casts(): array
    {
        return ['question' => 'array', 'answer' => 'array', 'sort_order' => 'integer', 'is_published' => 'boolean'];
    }

    public function translated(string $field): string
    {
        $values = $this->{$field} ?? [];

        return (string) ($values[app()->getLocale()] ?? $values['en'] ?? $values['vi'] ?? '');
    }
}
