<?php

namespace Tests\Unit;

use App\Services\Recon\TargetGuard;
use App\Services\Scanner\ScanProfileService;
use App\Services\Scanner\TargetValidationService;
use InvalidArgumentException;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ScannerValidationTest extends TestCase
{
    public function test_public_ip_and_small_cidr_must_match_the_allowlist(): void
    {
        config()->set('scanner.allowlist', ['8.8.8.0/24']);
        $service = new TargetValidationService(new TargetGuard);

        $this->assertSame('ipv4', $service->validate('8.8.8.8')['target_type']);
        $cidr = $service->validate('8.8.8.19/28');
        $this->assertSame('8.8.8.16/28', $cidr['target']);
        $this->assertSame(16, $cidr['host_count']);
    }

    #[DataProvider('unsafeTargets')]
    public function test_unsafe_or_injected_targets_are_rejected(string $target): void
    {
        config()->set('scanner.allowlist', ['0.0.0.0/0']);
        $this->expectException(InvalidArgumentException::class);

        (new TargetValidationService(new TargetGuard))->validate($target);
    }

    public static function unsafeTargets(): array
    {
        return [
            ['127.0.0.1'], ['10.0.0.1'], ['169.254.1.2'], ['https://example.com/path'],
            ['example.com;whoami'], ['example.com|id'], ['8.8.8.0/16'],
            ["example.com\r\n"], ["example.com\0"],
        ];
    }

    public function test_domain_allowlist_uses_normalized_domain_and_public_resolution(): void
    {
        config()->set('scanner.allowlist', ['*.example.com']);
        $guard = Mockery::mock(TargetGuard::class);
        $guard->shouldReceive('normalize')->once()->with('app.example.com', false)->andReturn('app.example.com');
        $guard->shouldReceive('assertPublicDomain')->once()->andReturn(['8.8.8.8']);

        $result = (new TargetValidationService($guard))->validate('APP.EXAMPLE.COM');

        $this->assertSame('app.example.com', $result['target']);
        $this->assertSame('hostname', $result['target_type']);
    }

    public function test_malformed_allowlist_cidr_fails_closed(): void
    {
        config()->set('scanner.allowlist', ['8.8.8.0/not-a-prefix']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(__('scanner.errors.target_not_allowed'));

        (new TargetValidationService(new TargetGuard))->validate('8.8.8.8');
    }

    public function test_profiles_reject_raw_or_excessive_custom_ports(): void
    {
        $profiles = new ScanProfileService;
        $profile = $profiles->get('custom_safe');

        $this->assertSame('80,443,8000-8010', $profiles->normalizeOptions($profile, ['port_range' => '80,443,8000-8010'])['port_range']);

        $this->expectException(InvalidArgumentException::class);
        $profiles->normalizeOptions($profile, ['port_range' => '1-2000']);
    }

    public function test_privileged_profiles_are_disabled_by_default(): void
    {
        config()->set('scanner.allow_privileged_profiles', false);
        $this->expectException(InvalidArgumentException::class);

        (new ScanProfileService)->get('os_detection');
    }
}
