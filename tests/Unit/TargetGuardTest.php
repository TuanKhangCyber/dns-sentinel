<?php

namespace Tests\Unit;

use App\Services\Recon\DnsResolver;
use App\Services\Recon\TargetGuard;
use InvalidArgumentException;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TargetGuardTest extends TestCase
{
    #[DataProvider('blockedIps')]
    public function test_it_rejects_blocked_ip_ranges(string $target): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(__('ui.unsafe_target'));

        (new TargetGuard)->assertSafeDnsTarget($target);
    }

    public static function blockedIps(): array
    {
        return [
            'IPv4 unspecified' => ['0.0.0.0'],
            'IPv4 loopback' => ['127.0.0.1'],
            'IPv4 private' => ['10.12.0.5'],
            'IPv4 link-local' => ['169.254.10.20'],
            'cloud metadata' => ['169.254.169.254'],
            'IPv4 reserved' => ['203.0.113.10'],
            'IPv4 multicast' => ['224.0.0.1'],
            'IPv6 unspecified' => ['::'],
            'IPv6 loopback' => ['::1'],
            'IPv6 local' => ['fd00::1'],
            'IPv6 link-local' => ['fe80::1'],
            'IPv6 reserved' => ['2001:db8::1'],
            'IPv6 multicast' => ['ff02::1'],
            'mapped IPv4 loopback' => ['::ffff:127.0.0.1'],
            'mapped IPv4 private' => ['::ffff:10.0.0.1'],
        ];
    }

    #[DataProvider('injectedTargets')]
    public function test_it_rejects_url_and_control_character_injection(string $target): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(__('ui.invalid_target'));

        (new TargetGuard)->normalize($target);
    }

    public static function injectedTargets(): array
    {
        return [
            'scheme' => ['https://example.com'],
            'userinfo' => ['user@example.com'],
            'port' => ['example.com:443'],
            'path' => ['example.com/path'],
            'query' => ['example.com?next=1'],
            'fragment' => ['example.com#part'],
            'CRLF' => ["example.com\r\nX-Test: injected"],
            'trailing CRLF' => ["example.com\r\n"],
            'control character' => ["example.com\x1F"],
            'null byte' => ["example.com\0.test"],
            'unicode IDN' => ['bücher.example'],
        ];
    }

    public function test_it_normalizes_mixed_case_trailing_dot_and_accepts_ascii_punycode(): void
    {
        $guard = new TargetGuard;

        $this->assertSame('example.com', $guard->normalize('ExAmPlE.CoM.'));
        $this->assertSame('xn--bcher-kva.example', $guard->normalize('XN--BCHER-KVA.Example.'));
    }

    public function test_it_accepts_public_ip_addresses(): void
    {
        $guard = new TargetGuard;

        $this->assertSame(['8.8.8.8'], $guard->assertSafeDnsTarget('8.8.8.8'));
        $this->assertSame(['2606:4700:4700::1111'], $guard->assertSafeDnsTarget('2606:4700:4700::1111'));
    }

    public function test_hostname_resolution_fails_closed_when_dns_errors_or_has_no_records(): void
    {
        foreach ([null, []] as $answer) {
            $resolver = Mockery::mock(DnsResolver::class);
            $resolver->shouldReceive('records')->andReturn($answer);

            try {
                (new TargetGuard($resolver))->assertPublicDomain('example.test');
                $this->fail('Unresolved hostname was accepted.');
            } catch (InvalidArgumentException $exception) {
                $this->assertSame(__('ui.unresolved_target'), $exception->getMessage());
            }
        }
    }

    public function test_hostname_resolving_to_private_address_is_rejected(): void
    {
        $guard = new TargetGuard($this->resolver([
            'private.example|'.DNS_A => [['type' => 'A', 'ip' => '10.0.0.8']],
        ]));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(__('ui.unsafe_target'));
        $guard->assertPublicDomain('private.example');
    }

    public function test_any_blocked_address_in_mixed_a_and_aaaa_answers_rejects_the_hostname(): void
    {
        $guard = new TargetGuard($this->resolver([
            'mixed.example|'.DNS_A => [
                ['type' => 'A', 'ip' => '8.8.8.8'],
                ['type' => 'A', 'ip' => '192.168.1.20'],
            ],
            'mixed.example|'.DNS_AAAA => [['type' => 'AAAA', 'ipv6' => '2606:4700:4700::1111']],
        ]));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(__('ui.unsafe_target'));
        $guard->assertPublicDomain('mixed.example');
    }

    public function test_cname_chain_ending_at_private_address_is_rejected(): void
    {
        $guard = new TargetGuard($this->resolver([
            'public.example|'.DNS_CNAME => [['type' => 'CNAME', 'target' => 'private.internal.']],
            'private.internal|'.DNS_A => [['type' => 'A', 'ip' => '172.16.0.9']],
        ]));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(__('ui.unsafe_target'));
        $guard->assertPublicDomain('public.example');
    }

    public function test_dns_rebinding_cannot_replace_the_ip_pinned_for_https(): void
    {
        $phase = 1;
        $resolver = Mockery::mock(DnsResolver::class);
        $resolver->shouldReceive('records')->andReturnUsing(function (string $hostname, int $type) use (&$phase): array {
            if ($type !== DNS_A) {
                return [];
            }

            return [['type' => 'A', 'ip' => $phase === 1 ? '8.8.8.8' : '127.0.0.1']];
        });
        $guard = new TargetGuard($resolver);

        $validatedIps = $guard->assertPublicDomain('rebind.example');
        $phase = 2;
        $options = $guard->pinnedHttpsOptions('rebind.example', $validatedIps);

        $this->assertFalse($options['allow_redirects']);
        $this->assertSame(['rebind.example:443:8.8.8.8'], $options['curl'][CURLOPT_RESOLVE]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(__('ui.unsafe_target'));
        $guard->assertPublicDomain('rebind.example');
    }

    public function test_https_options_pin_the_validated_ip_and_disable_redirects(): void
    {
        $options = (new TargetGuard)->pinnedHttpsOptions('example.com', ['8.8.8.8']);

        $this->assertFalse($options['allow_redirects']);
        $this->assertSame(['example.com:443:8.8.8.8'], $options['curl'][CURLOPT_RESOLVE]);
    }

    private function resolver(array $answers): DnsResolver
    {
        $resolver = Mockery::mock(DnsResolver::class);
        $resolver->shouldReceive('records')->andReturnUsing(
            fn (string $hostname, int $type): ?array => $answers[$hostname.'|'.$type] ?? []
        );

        return $resolver;
    }
}
