<?php

namespace Tests\Unit;

use App\Exceptions\Scanner\ScannerExecutionException;
use App\Models\Scan;
use App\Models\User;
use App\Services\Scanner\NessusApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NessusApiServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('scanner.nessus', [
            'enabled' => true, 'url' => 'https://cloud.tenable.com',
            'access_key' => 'access-test', 'secret_key' => 'secret-test',
            'template_uuid' => 'template-test', 'compliance_template_uuid' => null, 'scanner_id' => null, 'folder_id' => null,
            'timeout_seconds' => 5, 'poll_interval_seconds' => 0,
            'response_max_bytes' => 1024 * 1024, 'verify_tls' => true,
        ]);
    }

    public function test_it_creates_launches_polls_and_reads_scan_details(): void
    {
        Http::fake(function (Request $request) {
            return match ([$request->method(), $request->url()]) {
                ['POST', 'https://cloud.tenable.com/scans'] => Http::response(['scan' => ['id' => 321]], 200),
                ['POST', 'https://cloud.tenable.com/scans/321/launch'] => Http::response(['scan_uuid' => 'uuid'], 200),
                ['GET', 'https://cloud.tenable.com/scans/321/latest-status'] => Http::response(['status' => 'completed'], 200),
                ['GET', 'https://cloud.tenable.com/scans/321'] => Http::response(['hosts' => [], 'vulnerabilities' => []], 200),
                default => Http::response([], 404),
            };
        });

        $result = app(NessusApiService::class)->run($this->scan());

        $this->assertSame([], $result['hosts']);
        Http::assertSent(fn (Request $request) => $request->url() === 'https://cloud.tenable.com/scans'
            && $request->hasHeader('X-ApiKeys', 'accessKey=access-test; secretKey=secret-test')
            && $request['settings']['text_targets'] === '8.8.8.8');
    }

    public function test_it_rejects_redirect_responses(): void
    {
        Http::fake(['https://cloud.tenable.com/scans' => Http::response('', 302, ['Location' => 'http://127.0.0.1'])]);

        $this->expectException(ScannerExecutionException::class);
        $this->expectExceptionMessage(__('scanner.errors.nessus_redirect'));
        app(NessusApiService::class)->run($this->scan());
    }

    private function scan(): Scan
    {
        return Scan::create([
            'user_id' => User::factory()->create()->id, 'target' => '8.8.8.8', 'target_type' => 'ipv4',
            'profile' => 'vulnerability_assessment', 'scanner_type' => 'vulnerability',
            'status' => 'queued', 'stage' => 'queued', 'authorization_confirmed_at' => now(),
            'options' => ['timeout' => 30],
        ]);
    }
}
