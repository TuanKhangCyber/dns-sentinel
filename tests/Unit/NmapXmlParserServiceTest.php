<?php

namespace Tests\Unit;

use App\Services\Scanner\NmapXmlParserService;
use Tests\TestCase;

class NmapXmlParserServiceTest extends TestCase
{
    public function test_it_normalizes_hosts_ports_services_os_and_duration(): void
    {
        $result = (new NmapXmlParserService)->parse(<<<'XML'
<?xml version="1.0"?>
<nmaprun scanner="nmap" version="7.95"><host>
<status state="up" reason="syn-ack"/><address addr="8.8.8.8" addrtype="ipv4"/>
<address addr="AA:BB:CC:DD:EE:FF" addrtype="mac" vendor="Example Vendor"/>
<hostnames><hostname name="dns.example" type="user"/></hostnames><times srtt="12000"/>
<ports><port protocol="tcp" portid="443"><state state="open"/><service name="https" product="nginx" version="1.25"><cpe>cpe:/a:nginx:nginx:1.25</cpe></service></port></ports>
<os><osmatch name="Linux 5.x" accuracy="96"/></os></host>
<runstats><finished elapsed="4.2"/></runstats></nmaprun>
XML);

        $this->assertSame('8.8.8.8', $result['hosts'][0]['ip_address']);
        $this->assertSame('Linux 5.x', $result['hosts'][0]['operating_system']);
        $this->assertSame(12, $result['hosts'][0]['response_time']);
        $this->assertSame('nginx', $result['hosts'][0]['ports'][0]['product']);
        $this->assertSame('cpe:/a:nginx:nginx:1.25', $result['hosts'][0]['ports'][0]['cpe']);
        $this->assertSame(5, $result['duration_seconds']);
    }
}
