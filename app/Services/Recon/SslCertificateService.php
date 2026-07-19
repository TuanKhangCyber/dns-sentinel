<?php

namespace App\Services\Recon;

use App\Exceptions\Recon\ReconLookupException;
use App\Services\Recon\Concerns\BuildsReconResults;
use Carbon\CarbonImmutable;
use ErrorException;

class SslCertificateService
{
    use BuildsReconResults;

    public function __construct(private readonly TargetGuard $guard) {}

    public function lookup(string $domain): array
    {
        return $this->cached('ssl_certificate', $domain, config('recon.cache_minutes'), function () use ($domain) {
            $ips = $this->guard->assertPublicDomain($domain);
            $ip = $ips[0];
            $socketHost = str_contains($ip, ':') ? '['.$ip.']' : $ip;
            $context = stream_context_create(['ssl' => [
                'capture_peer_cert' => true,
                'verify_peer' => true,
                'verify_peer_name' => true,
                'peer_name' => $domain,
                'SNI_enabled' => true,
            ]]);
            set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
                throw new ErrorException($message, 0, $severity, $file, $line);
            });
            try {
                $socket = stream_socket_client(
                    'ssl://'.$socketHost.':443',
                    $errorCode,
                    $errorMessage,
                    config('recon.timeout_seconds'),
                    STREAM_CLIENT_CONNECT,
                    $context,
                );
            } catch (\Throwable $exception) {
                throw new ReconLookupException(__('ui.ssl_connection_failed'), previous: $exception);
            } finally {
                restore_error_handler();
            }
            if ($socket === false) {
                throw new ReconLookupException(__('ui.ssl_connection_failed'));
            }

            $certificate = stream_context_get_params($socket)['options']['ssl']['peer_certificate'] ?? null;
            fclose($socket);
            $parsed = $certificate ? openssl_x509_parse($certificate) : false;
            if (! is_array($parsed)) {
                throw new ReconLookupException(__('ui.ssl_invalid_certificate'));
            }

            $expiresAt = CarbonImmutable::createFromTimestampUTC($parsed['validTo_time_t']);
            $issuedAt = CarbonImmutable::createFromTimestampUTC($parsed['validFrom_time_t']);
            $daysRemaining = now()->startOfDay()->diffInDays($expiresAt->startOfDay(), false);
            $warnings = [];
            if ($daysRemaining < 0) {
                $warnings[] = 'certificate_expired';
            } elseif ($daysRemaining <= 30) {
                $warnings[] = 'certificate_expires_soon';
            }
            $status = $daysRemaining < 0 ? 'danger' : ($daysRemaining <= 30 ? 'warning' : 'safe');
            $score = $daysRemaining < 0 ? 0 : ($daysRemaining <= 30 ? 60 : 100);

            $sans = [];
            foreach (explode(',', $parsed['extensions']['subjectAltName'] ?? '') as $san) {
                $san = trim(preg_replace('/^[A-Z]+:/', '', trim($san)));
                if ($san !== '') {
                    $sans[] = $san;
                }
            }

            return $this->result('ssl_certificate', $domain, $status, $score, [
                'issuer' => $parsed['issuer'] ?? [],
                'subject' => $parsed['subject'] ?? [],
                'san' => array_values(array_unique($sans)),
                'issued_at' => $issuedAt->toIso8601String(),
                'expires_at' => $expiresAt->toIso8601String(),
                'days_remaining' => (int) $daysRemaining,
                'signature_algorithm' => $parsed['signatureTypeSN'] ?? $parsed['signatureTypeLN'] ?? null,
                'serial_number' => $parsed['serialNumberHex'] ?? null,
            ], $warnings);
        });
    }
}
