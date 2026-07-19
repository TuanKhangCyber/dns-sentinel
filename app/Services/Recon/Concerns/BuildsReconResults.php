<?php

namespace App\Services\Recon\Concerns;

use App\Exceptions\Recon\ReconLookupException;
use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

trait BuildsReconResults
{
    protected function cached(string $service, string $target, int $minutes, Closure $callback): array
    {
        $key = 'recon:'.$service.':v2:'.hash('sha256', $target);
        $cached = Cache::get($key);
        if (is_array($cached)) {
            return $cached;
        }

        try {
            $result = $callback();
        } catch (InvalidArgumentException|ReconLookupException $exception) {
            return $this->result($service, $target, 'error', null, [], [], $exception->getMessage());
        } catch (\Throwable $exception) {
            Log::warning('Recon lookup failed.', [
                'service' => $service, 'target' => $target,
                'exception' => $exception::class,
            ]);

            return $this->result($service, $target, 'error', null, [], [], __('ui.service_failed'));
        }

        // Temporary network failures should remain retryable instead of being cached.
        if (($result['status'] ?? null) !== 'error') {
            Cache::put($key, $result, now()->addMinutes($minutes));
        }

        return $result;
    }

    protected function result(
        string $service,
        string $target,
        string $status,
        ?int $score,
        array $data,
        array $warnings = [],
        ?string $error = null,
    ): array {
        return [
            'service' => $service,
            'target' => $target,
            'status' => $status,
            'score' => $score,
            'data' => $data,
            'warnings' => array_values($warnings),
            'error' => $error,
            'checked_at' => now()->toIso8601String(),
        ];
    }
}
