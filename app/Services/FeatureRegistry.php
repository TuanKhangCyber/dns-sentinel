<?php

namespace App\Services;

class FeatureRegistry
{
    public function all(): array
    {
        return config('features.registry', []);
    }

    public function has(string $code): bool
    {
        return array_key_exists($code, $this->all());
    }

    public function get(string $code): ?array
    {
        return $this->all()[$code] ?? null;
    }
}
