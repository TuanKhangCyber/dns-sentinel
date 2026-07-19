<?php

namespace App\Services\Scanner;

use App\Services\Recon\TargetGuard;
use InvalidArgumentException;

class TargetValidationService
{
    public function __construct(private readonly TargetGuard $guard) {}

    public function validate(string $input): array
    {
        if (preg_match('/[\x00-\x1F\x7F]/', $input)) {
            throw new InvalidArgumentException(__('scanner.errors.invalid_target'));
        }

        $target = strtolower(trim($input));
        if ($target === '' || strlen($target) > 253 || preg_match('/[\s@?#;&|`$<>"\'{}\[\]\\\\]/', $target)) {
            throw new InvalidArgumentException(__('scanner.errors.invalid_target'));
        }

        if (str_contains($target, '/')) {
            return $this->validateCidr($target);
        }

        if (filter_var($target, FILTER_VALIDATE_IP) !== false) {
            $this->guard->assertPublicIp($target);
            $this->assertAllowed($target, [$target], 'ip');

            return ['target' => $target, 'target_type' => str_contains($target, ':') ? 'ipv6' : 'ipv4', 'resolved_ips' => [$target], 'host_count' => 1];
        }

        $domain = $this->guard->normalize($target, false);
        $ips = $this->guard->assertPublicDomain($domain);
        $this->assertAllowed($domain, $ips, 'hostname');

        return ['target' => $domain, 'target_type' => 'hostname', 'resolved_ips' => $ips, 'host_count' => count($ips)];
    }

    private function validateCidr(string $target): array
    {
        if (substr_count($target, '/') !== 1) {
            throw new InvalidArgumentException(__('scanner.errors.invalid_cidr'));
        }
        [$ip, $prefixValue] = explode('/', $target, 2);
        if (filter_var($ip, FILTER_VALIDATE_IP) === false || ! ctype_digit($prefixValue)) {
            throw new InvalidArgumentException(__('scanner.errors.invalid_cidr'));
        }
        $bits = str_contains($ip, ':') ? 128 : 32;
        $minimumPrefix = $bits === 128 ? config('scanner.min_ipv6_prefix') : config('scanner.min_ipv4_prefix');
        $prefix = (int) $prefixValue;
        if ($prefix < $minimumPrefix || $prefix > $bits) {
            throw new InvalidArgumentException(__('scanner.errors.cidr_too_large'));
        }

        $network = $this->networkAddress($ip, $prefix);
        $this->guard->assertPublicIp($network);
        $hostCount = 2 ** ($bits - $prefix);
        if ($hostCount > config('scanner.max_hosts_per_scan')) {
            throw new InvalidArgumentException(__('scanner.errors.too_many_hosts'));
        }
        $normalized = $network.'/'.$prefix;
        $this->assertAllowed($normalized, [$network], 'cidr');

        return ['target' => $normalized, 'target_type' => $bits === 128 ? 'ipv6_cidr' : 'ipv4_cidr', 'resolved_ips' => [$network], 'host_count' => $hostCount];
    }

    private function assertAllowed(string $target, array $ips, string $type): void
    {
        $allowlist = config('scanner.allowlist', []);
        if ($allowlist === []) {
            throw new InvalidArgumentException(__('scanner.errors.allowlist_empty'));
        }

        if ($type === 'hostname' && collect($allowlist)->contains(function (string $allowed) use ($target) {
            $allowed = strtolower($allowed);

            return $allowed === $target || (str_starts_with($allowed, '*.') && str_ends_with($target, substr($allowed, 1)));
        })) {
            return;
        }

        if ($type === 'cidr') {
            [$network, $prefix] = explode('/', $target, 2);
            foreach ($allowlist as $allowed) {
                if (str_contains($allowed, '/') && $this->cidrContainsCidr($allowed, $network, (int) $prefix)) {
                    return;
                }
            }
            throw new InvalidArgumentException(__('scanner.errors.target_not_allowed'));
        }

        $allAllowed = collect($ips)->every(function (string $ip) use ($allowlist) {
            foreach ($allowlist as $allowed) {
                if ($allowed === $ip || (str_contains($allowed, '/') && $this->ipInCidr($ip, $allowed))) {
                    return true;
                }
            }

            return false;
        });
        if (! $allAllowed) {
            throw new InvalidArgumentException(__('scanner.errors.target_not_allowed'));
        }
    }

    private function cidrContainsCidr(string $allowed, string $network, int $prefix): bool
    {
        [$allowedNetwork, $allowedPrefix] = explode('/', $allowed, 2);

        return filter_var($allowedNetwork, FILTER_VALIDATE_IP) !== false
            && (int) $allowedPrefix <= $prefix
            && $this->ipInCidr($network, $allowed);
    }

    private function ipInCidr(string $ip, string $cidr): bool
    {
        [$network, $prefix] = explode('/', $cidr, 2);
        $ipBytes = inet_pton($ip);
        $networkBytes = inet_pton($network);
        if ($ipBytes === false || $networkBytes === false || strlen($ipBytes) !== strlen($networkBytes)
            || ! ctype_digit($prefix)) {
            return false;
        }
        $prefix = (int) $prefix;
        $bits = strlen($networkBytes) * 8;
        if ($prefix < 0 || $prefix > $bits) {
            return false;
        }
        $bytes = intdiv($prefix, 8);
        $remainder = $prefix % 8;
        if ($bytes && substr($ipBytes, 0, $bytes) !== substr($networkBytes, 0, $bytes)) {
            return false;
        }

        return $remainder === 0 || ((ord($ipBytes[$bytes]) & (0xFF << (8 - $remainder))) === (ord($networkBytes[$bytes]) & (0xFF << (8 - $remainder))));
    }

    private function networkAddress(string $ip, int $prefix): string
    {
        $bytes = inet_pton($ip);
        $length = strlen($bytes);
        for ($index = 0; $index < $length; $index++) {
            $remaining = $prefix - ($index * 8);
            $mask = $remaining >= 8 ? 0xFF : ($remaining <= 0 ? 0 : (0xFF << (8 - $remaining)) & 0xFF);
            $bytes[$index] = chr(ord($bytes[$index]) & $mask);
        }

        return inet_ntop($bytes);
    }
}
