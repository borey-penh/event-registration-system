<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Request;

/**
 * Public helper for the SPA. Returns two things:
 *
 *  - public_origin: a URL that works from ANY network (mobile data, another
 *    Wi-Fi, another city). Sourced from APP_PUBLIC_ORIGIN in backend/.env or
 *    from a cloudflared quick-tunnel URL that scripts/start-public.bat
 *    captures into storage/app/public_origin.txt.
 *
 *  - public_host: the LAN address for phones on the SAME Wi-Fi only.
 *
 * The manager's browser often shows "localhost", which is useless inside a QR
 * code — a phone would try to connect to itself — so the SPA swaps in one of
 * the above when building the registration link/QR.
 */
class AppInfoController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json([
            'public_origin' => static::publicOrigin(),
            'public_host' => static::publicHost(),
            // Per-provider availability so the SPA can hide the Google/Facebook
            // buttons when no OAuth credentials are configured on this server.
            // Auth0-brokered login makes both available with one config.
            'social_login' => [
                'google' => (bool) config('services.google.client_id') || \App\Http\Controllers\CandidateAuthController::auth0Configured(),
                'facebook' => (bool) config('services.facebook.client_id') || \App\Http\Controllers\CandidateAuthController::auth0Configured(),
            ],
        ]);
    }

    /**
     * Any-network origin, e.g. "https://demo-event.trycloudflare.com",
     * or null when neither an env override nor a tunnel URL exists.
     */
    public static function publicOrigin(): ?string
    {
        // 1. Explicit operator override: APP_PUBLIC_ORIGIN in backend/.env
        $env = config('app.public_origin') ?: getenv('APP_PUBLIC_ORIGIN');
        if (is_string($env) && preg_match('#^https?://#', $env)) {
            return rtrim($env, '/');
        }

        // 2. URL captured from cloudflared by scripts/start-public.bat
        $file = storage_path('app/public_origin.txt');
        if (is_file($file)) {
            $url = trim((string) file_get_contents($file));
            if (preg_match('#^https?://#', $url)) {
                return rtrim($url, '/');
            }
        }

        return null;
    }

    /**
     * The LAN host of this server, e.g. "192.168.1.20:8000".
     * Returns null when only loopback/unknown addresses are available.
     */
    public static function publicHost(): ?string
    {
        // APP_URL is the explicit public address supplied by the operator.
        // Prefer it to hostname resolution: on Windows, the hostname can
        // resolve to a WSL/Hyper-V adapter (for example 172.31.x.x), which
        // phones on the Wi-Fi cannot reach.
        $appUrl = config('app.url');
        if (is_string($appUrl)) {
            $host = parse_url($appUrl, PHP_URL_HOST);
            $port = parse_url($appUrl, PHP_URL_PORT);

            if (is_string($host) && $host !== '' && ! in_array($host, ['localhost', '127.0.0.1', '0.0.0.0'], true)) {
                return $port ? $host.':'.$port : $host;
            }
        }

        // The interface the OS itself uses to reach other networks — by
        // definition the adapter other devices on the LAN can talk to. On
        // Windows the machine hostname often resolves to a virtual
        // WSL/Hyper-V adapter (172.x.x.x with no gateway), which is exactly
        // what must NOT end up in the registration QR.
        if ($routeIp = static::defaultRouteIp()) {
            return static::withPort($routeIp);
        }

        $candidates = [];

        // Resolving the machine hostname works even under `php artisan serve`,
        // where Apache/FCGI-style $_SERVER keys are missing. Kept only as a
        // fallback: gethostbyname() can return a virtual-adapter address, so
        // the default-route lookup above wins whenever it succeeds.
        $hostname = gethostname();
        if ($hostname) {
            $candidates[] = gethostbyname($hostname);
        }

        $candidates[] = Request::server('LOCAL_ADDR');
        $candidates[] = Request::server('SERVER_ADDR');

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && static::isLanIp($candidate)) {
                return static::withPort($candidate);
            }
        }

        return null;
    }

    /** True for private-range IPv4 addresses (10.x, 172.16-31.x, 192.168.x). */
    private static function isLanIp(string $value): bool
    {
        if (filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
            return false;
        }

        if (str_starts_with($value, '192.168.') || str_starts_with($value, '10.')) {
            return true;
        }

        return (bool) preg_match('/^172\.(1[6-9]|2\d|3[01])\./', $value);
    }

    /** Append the API port unless it is a default one (80/443). */
    private static function withPort(string $ip): string
    {
        // PHP's $_SERVER key is SERVER_PORT (not "SERVERPORT" — that key never
        // exists, which silently dropped the :8000 from registration links).
        $port = Request::server('SERVER_PORT');

        return $port && ! in_array((string) $port, ['80', '443'], true)
            ? $ip.':'.$port
            : $ip;
    }

    /**
     * The IPv4 address of the network interface that owns the default route,
     * i.e. the adapter actually plugged into the LAN/Wi-Fi. Virtual adapters
     * (WSL, Hyper-V, Wi-Fi Direct) never own it. Returns null when it cannot
     * be determined (unknown OS, no route, command unavailable).
     */
    private static function defaultRouteIp(): ?string
    {
        $output = null;

        if (PHP_OS_FAMILY === 'Windows') {
            // `route print` output is locale-independent (IPs and numbers
            // only), unlike ipconfig. Virtual adapters carry no default
            // gateway, so the 0.0.0.0 route always points at the real NIC.
            $output = @shell_exec('route print 0.0.0.0');
        } elseif (PHP_OS_FAMILY === 'Linux') {
            // Pure routing-table lookup — no packet is sent anywhere.
            $output = @shell_exec('ip route get 1.1.1.1 2>/dev/null');
        }

        if (! is_string($output) || trim($output) === '') {
            return null;
        }

        return PHP_OS_FAMILY === 'Windows'
            ? static::parseWindowsRouteTable($output)
            : static::parseLinuxRoute($output);
    }

    /**
     * Extract the interface IP of the lowest-metric 0.0.0.0 route from
     * `route print` output. Public for testability.
     */
    public static function parseWindowsRouteTable(string $output): ?string
    {
        // Rows: Destination  Netmask  Gateway  Interface  Metric
        preg_match_all(
            '/^\s*0\.0\.0\.0\s+0\.0\.0\.0\s+(\d{1,3}(?:\.\d{1,3}){3})\s+(\d{1,3}(?:\.\d{1,3}){3})\s+(\d+)\s*$/m',
            $output,
            $matches,
            PREG_SET_ORDER
        );

        // Multi-homed PCs can have several default routes: trust the lowest
        // metric, the one the OS itself would actually use.
        $best = null;
        foreach ($matches as $row) {
            if (! static::isLanIp($row[2])) {
                continue;
            }
            if ($best === null || (int) $row[3] < (int) $best[3]) {
                $best = $row;
            }
        }

        return $best[2] ?? null;
    }

    /**
     * Extract the source IP from `ip route get` output, e.g.
     * "1.1.1.1 via 192.168.88.1 dev wlan0 src 192.168.88.4". Public for
     * testability.
     */
    public static function parseLinuxRoute(string $output): ?string
    {
        if (preg_match('/\bsrc\s+(\d{1,3}(?:\.\d{1,3}){3})/', $output, $m)
            && static::isLanIp($m[1])) {
            return $m[1];
        }

        return null;
    }
}
