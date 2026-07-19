<?php

namespace App\Services\Scanner;

use App\Exceptions\Scanner\ScanCancelledException;
use App\Exceptions\Scanner\ScannerExecutionException;
use App\Models\Scan;
use App\Services\Scanner\Contracts\VulnerabilityScannerInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NessusApiService implements VulnerabilityScannerInterface
{
    public function run(Scan $scan): array
    {
        $started = microtime(true);
        $this->validateConfiguration();

        $created = $this->request('POST', '/scans', [
            'uuid' => $this->templateFor($scan),
            'settings' => array_filter([
                'name' => 'Authorized scan #'.$scan->id.' - '.$scan->target,
                'text_targets' => $scan->target,
                'enabled' => true,
                'scanner_id' => config('scanner.nessus.scanner_id'),
                'folder_id' => config('scanner.nessus.folder_id'),
            ], fn (mixed $value) => $value !== null && $value !== ''),
        ]);
        $remoteId = (int) ($created['scan']['id'] ?? 0);
        if ($remoteId < 1) {
            throw new ScannerExecutionException(__('scanner.errors.nessus_invalid_response'));
        }

        $options = $scan->options ?? [];
        $options['remote_scan_id'] = $remoteId;
        $scan->update(['options' => $options, 'progress' => 25]);
        $this->request('POST', "/scans/{$remoteId}/launch");
        $this->waitUntilComplete($scan, $remoteId);
        $details = $this->request('GET', "/scans/{$remoteId}");
        $details['duration_seconds'] = (int) round(microtime(true) - $started);

        return $details;
    }

    private function waitUntilComplete(Scan $scan, int $remoteId): void
    {
        $deadline = time() + max(30, (int) data_get($scan->options, 'timeout', 900));
        $interval = max(0, (int) config('scanner.nessus.poll_interval_seconds', 5));

        do {
            $scan->refresh();
            if ($scan->cancel_requested_at !== null) {
                try {
                    $this->request('POST', "/scans/{$remoteId}/stop");
                } catch (\Throwable $exception) {
                    Log::warning('Remote scanner stop request failed.', ['scan_id' => $scan->id, 'exception' => $exception::class]);
                }
                throw new ScanCancelledException(__('scanner.errors.cancelled'));
            }

            $payload = $this->request('GET', "/scans/{$remoteId}/latest-status");
            $status = strtolower((string) ($payload['status'] ?? 'unknown'));
            if (in_array($status, ['completed', 'stopped'], true)) {
                return;
            }
            if (in_array($status, ['aborted', 'canceled', 'cancelled', 'error'], true)) {
                throw new ScannerExecutionException(__('scanner.errors.nessus_remote_failed', ['status' => $status]));
            }

            $scan->update(['progress' => min(75, max(30, $scan->progress + 2)), 'stage' => 'assessing_vulnerabilities']);
            if ($interval > 0) {
                sleep($interval);
            }
        } while (time() <= $deadline);

        throw new ScannerExecutionException(__('scanner.errors.nessus_timeout'));
    }

    private function validateConfiguration(): void
    {
        $url = (string) config('scanner.nessus.url');
        $parts = parse_url($url);
        $port = (int) ($parts['port'] ?? 443);
        if (! config('scanner.nessus.enabled')
            || ($parts['scheme'] ?? null) !== 'https'
            || empty($parts['host'])
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])
            || ! in_array($port, [443, 8834], true)
            || blank(config('scanner.nessus.access_key'))
            || blank(config('scanner.nessus.secret_key'))) {
            throw new ScannerExecutionException(__('scanner.errors.nessus_not_configured'));
        }
    }

    private function templateFor(Scan $scan): string
    {
        $key = $scan->profile === 'compliance'
            ? 'scanner.nessus.compliance_template_uuid'
            : 'scanner.nessus.template_uuid';
        $template = (string) config($key);
        if ($template === '') {
            throw new ScannerExecutionException(__('scanner.errors.nessus_not_configured'));
        }

        return $template;
    }

    private function request(string $method, string $path, array $data = []): array
    {
        try {
            $response = Http::baseUrl(rtrim((string) config('scanner.nessus.url'), '/'))
                ->withHeaders(['X-ApiKeys' => sprintf('accessKey=%s; secretKey=%s', config('scanner.nessus.access_key'), config('scanner.nessus.secret_key'))])
                ->acceptJson()->asJson()
                ->connectTimeout(min(10, (int) config('scanner.nessus.timeout_seconds', 30)))
                ->timeout((int) config('scanner.nessus.timeout_seconds', 30))
                ->withOptions([
                    'allow_redirects' => false,
                    'verify' => (bool) config('scanner.nessus.verify_tls', true),
                    ...$this->responseLimitOptions(),
                ])
                ->send($method, $path, $data === [] ? [] : ['json' => $data]);
        } catch (ConnectionException $exception) {
            throw new ScannerExecutionException(__('scanner.errors.nessus_connection'), previous: $exception);
        }

        $this->guardResponse($response);
        $decoded = $response->json();
        if (! is_array($decoded)) {
            throw new ScannerExecutionException(__('scanner.errors.nessus_invalid_response'));
        }

        return $decoded;
    }

    private function guardResponse(Response $response): void
    {
        if ($response->redirect()) {
            throw new ScannerExecutionException(__('scanner.errors.nessus_redirect'));
        }
        if (strlen($response->body()) > (int) config('scanner.nessus.response_max_bytes', 5 * 1024 * 1024)) {
            throw new ScannerExecutionException(__('scanner.errors.nessus_response_too_large'));
        }
        if ($response->status() === 401 || $response->status() === 403) {
            throw new ScannerExecutionException(__('scanner.errors.nessus_authentication'));
        }
        if ($response->status() === 429) {
            throw new ScannerExecutionException(__('scanner.errors.nessus_rate_limited'));
        }
        if (! $response->successful()) {
            throw new ScannerExecutionException(__('scanner.errors.nessus_http_error', ['status' => $response->status()]));
        }
    }

    private function responseLimitOptions(): array
    {
        $maxBytes = (int) config('scanner.nessus.response_max_bytes', 5 * 1024 * 1024);

        return [
            'on_headers' => static function ($response) use ($maxBytes): void {
                if ((int) $response->getHeaderLine('Content-Length') > $maxBytes) {
                    throw new ScannerExecutionException(__('scanner.errors.nessus_response_too_large'));
                }
            },
            'progress' => static function (int $downloadTotal, int $downloaded) use ($maxBytes): void {
                if ($downloaded > $maxBytes || $downloadTotal > $maxBytes) {
                    throw new ScannerExecutionException(__('scanner.errors.nessus_response_too_large'));
                }
            },
        ];
    }
}
