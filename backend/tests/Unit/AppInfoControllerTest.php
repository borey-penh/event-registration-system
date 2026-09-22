<?php

namespace Tests\Unit;

use App\Http\Controllers\AppInfoController;
use PHPUnit\Framework\TestCase;

class AppInfoControllerTest extends TestCase
{
    public function test_windows_route_table_picks_the_adapter_that_owns_the_default_route(): void
    {
        // Real `route print 0.0.0.0` shape (Windows 11). The WSL/Hyper-V
        // adapter (172.31.96.1) holds no default route, so it can never win —
        // exactly the adapter that broke LAN registration links before.
        $output = <<<'TXT'
===========================================================================

IPv4 Route Table
===========================================================================
Active Routes:
Network Destination        Netmask          Gateway       Interface  Metric
          0.0.0.0          0.0.0.0     192.168.88.1     192.168.88.4     35
===========================================================================

Persistent Routes:
  Network Address          Netmask  Gateway Address  Metric
          0.0.0.0          0.0.0.0    192.168.200.1  Default
TXT;

        $this->assertSame('192.168.88.4', AppInfoController::parseWindowsRouteTable($output));
    }

    public function test_windows_route_table_prefers_the_lowest_metric(): void
    {
        // Multi-homed PC: virtual adapters can also carry a default route.
        // The OS ships traffic over the lowest metric, so the parser must
        // pick the same interface.
        $output = <<<'TXT'
Active Routes:
Network Destination        Netmask          Gateway       Interface  Metric
          0.0.0.0          0.0.0.0      172.31.0.1      172.31.96.1   5000
          0.0.0.0          0.0.0.0     192.168.88.1     192.168.88.4     35
          0.0.0.0        128.0.0.0     192.168.88.1     192.168.88.4     35
TXT;

        $this->assertSame('192.168.88.4', AppInfoController::parseWindowsRouteTable($output));
    }

    public function test_windows_route_table_returns_null_without_a_lan_interface(): void
    {
        // Only non-LAN addresses (e.g. CGNAT/VPN) — nothing usable for a QR.
        $output = <<<'TXT'
Active Routes:
Network Destination        Netmask          Gateway       Interface  Metric
          0.0.0.0          0.0.0.0      100.64.0.1      100.64.0.25     25
TXT;

        $this->assertNull(AppInfoController::parseWindowsRouteTable($output));
        $this->assertNull(AppInfoController::parseWindowsRouteTable('no routes here'));
    }

    public function test_linux_route_extracts_the_source_ip(): void
    {
        $output = "1.1.1.1 via 192.168.88.1 dev wlan0 src 192.168.88.4 uid 1000\n    cache";

        $this->assertSame('192.168.88.4', AppInfoController::parseLinuxRoute($output));
    }

    public function test_linux_route_returns_null_without_a_usable_source(): void
    {
        $this->assertNull(AppInfoController::parseLinuxRoute('RTNETLINK answers: Network is unreachable'));

        // A public source address is not a usable LAN host either.
        $this->assertNull(AppInfoController::parseLinuxRoute(
            '1.1.1.1 via 100.64.0.1 dev eth0 src 100.64.0.25'
        ));
    }

    public function test_lan_detection_covers_private_ranges_only(): void
    {
        $isLanIp = new \ReflectionMethod(AppInfoController::class, 'isLanIp');

        foreach (['192.168.88.4', '10.1.2.3', '172.16.0.1', '172.31.255.254'] as $ip) {
            $this->assertTrue($isLanIp->invoke(null, $ip), "expected $ip to count as LAN");
        }

        foreach (['172.15.0.1', '172.32.0.1', '8.8.8.8', '100.64.0.25', 'not-an-ip'] as $ip) {
            $this->assertFalse($isLanIp->invoke(null, $ip), "expected $ip NOT to count as LAN");
        }
    }
}
