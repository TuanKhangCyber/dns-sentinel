<?php

namespace App\Jobs;

use App\Exceptions\Scanner\ScanCancelledException;
use App\Exceptions\Scanner\ScannerExecutionException;
use App\Models\Scan;
use App\Services\CreditService;
use App\Services\Scanner\NmapScannerService;
use App\Services\Scanner\ScanRiskService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

class RunNmapScanJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 930;

    public int $backoff = 10;

    public bool $failOnTimeout = true;

    public function __construct(public readonly int $scanId)
    {
        $this->onQueue('scanner');
    }

    public function uniqueId(): string
    {
        return 'nmap-scan-'.$this->scanId;
    }

    public function handle(NmapScannerService $scanner, ScanRiskService $risk): void
    {
        $scan = Scan::findOrFail($this->scanId);
        $claimed = Scan::query()->whereKey($scan->id)
            ->where('status', 'queued')->whereNull('cancel_requested_at')
            ->update([
                'status' => 'validating', 'stage' => 'validating', 'progress' => 5,
                'started_at' => $scan->started_at ?? now(), 'error_message' => null,
            ]);
        if ($claimed === 0) {
            $scan->refresh();
            if (! $scan->isTerminal() && $scan->cancel_requested_at !== null) {
                $this->markCancelled($scan);
            }

            return;
        }

        $scan->refresh();
        Log::info('Scanner job started.', ['scan_id' => $scan->id, 'user_id' => $scan->user_id, 'target' => $scan->target, 'profile' => $scan->profile]);

        try {
            $this->transition($scan, 'validating', ['status' => 'scanning_ports', 'stage' => 'scanning_ports', 'progress' => 20]);
            $parsed = $scanner->run($scan);
            $this->ensureActive($scan);
            $this->transition($scan, 'scanning_ports', ['status' => 'parsing', 'stage' => 'parsing', 'progress' => 80]);

            $completed = DB::transaction(function () use ($scan, $parsed, $risk): bool {
                $locked = Scan::query()->whereKey($scan->id)->lockForUpdate()->firstOrFail();
                if ($locked->isTerminal()) {
                    return false;
                }
                if ($locked->cancel_requested_at !== null || $locked->status !== 'parsing') {
                    throw new ScanCancelledException(__('scanner.errors.cancelled'));
                }
                foreach ($parsed['hosts'] as $hostData) {
                    $ports = $hostData['ports'] ?? [];
                    unset($hostData['ports']);
                    $host = $locked->hosts()->create($hostData);
                    foreach ($ports as $port) {
                        $host->ports()->create($port);
                    }
                }
                $completedAt = now();
                $locked->update([
                    'status' => 'completed', 'stage' => 'completed', 'progress' => 100,
                    'completed_at' => $completedAt,
                    'duration_seconds' => $parsed['duration_seconds'] ?? $locked->started_at?->diffInSeconds($completedAt),
                    'summary' => $risk->summarize($locked),
                ]);

                return true;
            });
            if (! $completed) {
                return;
            }
            $scan->refresh();
            Log::info('Scanner job completed.', ['scan_id' => $scan->id, 'duration_seconds' => $scan->duration_seconds]);
        } catch (ScanCancelledException) {
            $this->markCancelled($scan);
            app(CreditService::class)->refund($scan->fresh()->creditTransaction, __('platform.credit_refund_cancelled'));
        } catch (Throwable $exception) {
            $scan->refresh();
            if ($scan->isTerminal()) {
                return;
            }
            if ($scan->cancel_requested_at !== null) {
                $this->markCancelled($scan);

                return;
            }
            $completedAt = now();
            Scan::query()->whereKey($scan->id)->whereNotIn('status', Scan::TERMINAL_STATUSES)->update([
                'status' => 'failed', 'stage' => 'failed', 'completed_at' => $completedAt,
                'duration_seconds' => $scan->started_at?->diffInSeconds($completedAt),
                'error_message' => $this->publicFailureMessage($exception),
            ]);
            app(CreditService::class)->refund($scan->fresh()->creditTransaction, __('platform.credit_refund_failed'));
            Log::error('Scanner job failed.', ['scan_id' => $scan->id, 'exception' => $exception::class]);
            throw $exception;
        }
    }

    public function failed(?Throwable $exception): void
    {
        $scan = Scan::find($this->scanId);
        if (! $scan) {
            return;
        }

        $completedAt = now();
        $updated = Scan::query()->whereKey($scan->id)->whereNotIn('status', Scan::TERMINAL_STATUSES)->update([
            'status' => 'failed', 'stage' => 'failed', 'completed_at' => $completedAt,
            'duration_seconds' => $scan->started_at?->diffInSeconds($completedAt),
            'error_message' => __('scanner.errors.job_failed'),
        ]);
        if ($updated > 0) {
            app(CreditService::class)->refund($scan->fresh()->creditTransaction, __('platform.credit_refund_failed'));
            Log::error('Scanner job reached terminal failure.', [
                'scan_id' => $scan->id, 'exception' => $exception ? $exception::class : null,
            ]);
        }
    }

    private function markCancelled(Scan $scan): void
    {
        $updated = Scan::query()->whereKey($scan->id)->whereNotIn('status', Scan::TERMINAL_STATUSES)
            ->update(['status' => 'cancelled', 'stage' => 'cancelled', 'completed_at' => now()]);
        if ($updated > 0) {
            Log::notice('Scanner job cancelled.', ['scan_id' => $scan->id, 'user_id' => $scan->user_id]);
        }
    }

    private function ensureActive(Scan $scan): void
    {
        $scan->refresh();
        if ($scan->cancel_requested_at !== null) {
            throw new ScanCancelledException(__('scanner.errors.cancelled'));
        }
        if ($scan->isTerminal()) {
            throw new ScanCancelledException(__('scanner.errors.cancelled'));
        }
    }

    private function transition(Scan $scan, string $from, array $attributes): void
    {
        $updated = Scan::query()->whereKey($scan->id)->where('status', $from)
            ->whereNull('cancel_requested_at')->update($attributes);
        if ($updated === 0) {
            throw new ScanCancelledException(__('scanner.errors.cancelled'));
        }
        $scan->refresh();
    }

    private function publicFailureMessage(Throwable $exception): string
    {
        if ($exception instanceof ScannerExecutionException || $exception instanceof InvalidArgumentException) {
            return mb_substr($exception->getMessage(), 0, 2000);
        }

        return __('scanner.errors.job_failed');
    }
}
