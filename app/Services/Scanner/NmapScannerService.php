<?php

namespace App\Services\Scanner;

use App\Exceptions\Scanner\ScanCancelledException;
use App\Exceptions\Scanner\ScannerExecutionException;
use App\Models\Scan;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

class NmapScannerService
{
    public function __construct(
        private readonly ScanProfileService $profiles,
        private readonly TargetValidationService $targets,
        private readonly NmapXmlParserService $parser,
    ) {}

    public function run(Scan $scan): array
    {
        if (PHP_OS_FAMILY !== 'Windows' && function_exists('posix_geteuid') && posix_geteuid() === 0) {
            throw new ScannerExecutionException(__('scanner.errors.root_forbidden'));
        }

        $profile = $this->profiles->get($scan->profile);
        if ($profile['scanner_type'] !== 'nmap') {
            throw new ScannerExecutionException(__('scanner.errors.wrong_scanner'));
        }
        $validated = $this->targets->validate($scan->target);
        $options = $this->profiles->normalizeOptions($profile, $scan->options ?? []);
        $disk = Storage::disk(config('scanner.temporary_disk'));
        $directory = 'scanner/'.$scan->id;
        $relativePath = $directory.'/nmap.xml';
        $disk->makeDirectory($directory);
        $absolutePath = $disk->path($relativePath);
        $command = $this->buildCommand($profile, $validated, $options, $absolutePath);

        try {
            $process = Process::timeout($options['timeout'] + 15)->start($command);
            while ($process->running()) {
                $process->ensureNotTimedOut();
                $scan->refresh();
                if ($scan->cancel_requested_at !== null) {
                    $process->stop(3);
                    throw new ScanCancelledException(__('scanner.errors.cancelled'));
                }
                if (is_file($absolutePath) && filesize($absolutePath) > config('scanner.output_max_bytes')) {
                    $process->stop(3);
                    throw new ScannerExecutionException(__('scanner.errors.output_too_large'));
                }
                usleep(250000);
            }
            $result = $process->wait();
            if ($result->failed()) {
                throw new ScannerExecutionException(__('scanner.errors.process_failed'));
            }
            if (! is_file($absolutePath)) {
                throw new ScannerExecutionException(__('scanner.errors.output_missing'));
            }
            if (filesize($absolutePath) > config('scanner.output_max_bytes')) {
                throw new ScannerExecutionException(__('scanner.errors.output_too_large'));
            }

            return $this->parser->parseFile($absolutePath);
        } catch (ScanCancelledException|ScannerExecutionException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            throw new ScannerExecutionException(__('scanner.errors.nmap_unavailable'), previous: $exception);
        } finally {
            $disk->deleteDirectory($directory);
        }
    }

    public function buildCommand(array $profile, array $validated, array $options, string $outputPath): array
    {
        $speed = config('scanner.speeds.'.$options['speed']);
        $targets = str_ends_with($validated['target_type'], '_cidr')
            ? [$validated['target']]
            : ($validated['target_type'] === 'hostname' ? $validated['resolved_ips'] : [$validated['target']]);
        $command = [config('scanner.nmap_binary'), ...$profile['arguments']];
        if ($profile['key'] === 'custom_safe') {
            $command = [...$command, $options['port_range'] ? '-p' : '--top-ports', $options['port_range'] ?: '100'];
        }

        return [
            ...$command, '-'.$speed['timing'], '--max-rate', (string) $speed['max_rate'],
            '--max-retries', '2', '--host-timeout', $options['timeout'].'s', '-oX', $outputPath, ...$targets,
        ];
    }
}
