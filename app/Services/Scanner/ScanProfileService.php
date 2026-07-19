<?php

namespace App\Services\Scanner;

use InvalidArgumentException;

class ScanProfileService
{
    public function all(): array
    {
        return collect(config('scanner.profiles'))
            ->map(function (array $profile, string $key) {
                $profile['key'] = $key;
                $profile['name'] = __($profile['label']);
                $profile['available'] = $this->isAvailable($profile);
                unset($profile['arguments']);

                return $profile;
            })->values()->all();
    }

    public function get(string $key): array
    {
        $profile = config('scanner.profiles.'.$key);
        if (! is_array($profile) || ! ($profile['enabled'] ?? false) || ! $this->isAvailable($profile)) {
            throw new InvalidArgumentException(__('scanner.errors.profile_unavailable'));
        }

        return ['key' => $key, ...$profile];
    }

    public function normalizeOptions(array $profile, array $input): array
    {
        $speed = (string) ($input['speed'] ?? 'normal');
        if (! array_key_exists($speed, config('scanner.speeds'))) {
            throw new InvalidArgumentException(__('scanner.errors.invalid_speed'));
        }

        $maximumTimeout = min(3600, (int) ($profile['timeout'] ?? 300));
        $timeout = max(10, min($maximumTimeout, (int) ($input['timeout'] ?? $maximumTimeout)));
        $portRange = trim((string) ($input['port_range'] ?? ''));
        if ($portRange !== '') {
            if ($profile['key'] !== 'custom_safe' || ! $this->validPortRange($portRange)) {
                throw new InvalidArgumentException(__('scanner.errors.invalid_port_range'));
            }
        }

        return ['speed' => $speed, 'timeout' => $timeout, 'port_range' => $portRange ?: null];
    }

    private function isAvailable(array $profile): bool
    {
        if (! ($profile['enabled'] ?? false)
            || (($profile['privileged'] ?? false) && ! config('scanner.allow_privileged_profiles'))) {
            return false;
        }

        if (($profile['scanner_type'] ?? null) === 'vulnerability') {
            return config('scanner.nessus.enabled')
                && filled(config('scanner.nessus.url'))
                && filled(config('scanner.nessus.access_key'))
                && filled(config('scanner.nessus.secret_key'))
                && filled(config($profile['template_config'] ?? 'scanner.nessus.template_uuid'));
        }

        return true;
    }

    private function validPortRange(string $value): bool
    {
        if (! preg_match('/^\d{1,5}(?:-\d{1,5})?(?:,\d{1,5}(?:-\d{1,5})?)*$/', $value)) {
            return false;
        }

        $count = 0;
        foreach (explode(',', $value) as $part) {
            [$start, $end] = array_pad(array_map('intval', explode('-', $part, 2)), 2, null);
            $end ??= $start;
            if ($start < 1 || $end > 65535 || $start > $end) {
                return false;
            }
            $count += $end - $start + 1;
        }

        return $count <= 1000;
    }
}
