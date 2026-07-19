<?php

namespace App\Services\Recon;

use ErrorException;
use Throwable;

class DnsResolver
{
    public function records(string $hostname, int $type): ?array
    {
        $records = $this->call(fn () => dns_get_record($hostname, $type));

        return is_array($records) ? $records : null;
    }

    public function ipv4Addresses(string $hostname): array
    {
        $addresses = $this->call(fn () => gethostbynamel($hostname));

        return is_array($addresses) ? array_values(array_filter($addresses, 'is_string')) : [];
    }

    private function call(\Closure $callback): mixed
    {
        set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
            throw new ErrorException($message, 0, $severity, $file, $line);
        });

        try {
            return $callback();
        } catch (Throwable) {
            return false;
        } finally {
            restore_error_handler();
        }
    }
}
