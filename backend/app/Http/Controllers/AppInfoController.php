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

        $candidates = [];

        // Resolving the machine hostname works even under `php artisan serve`,
        // where Apache/FCGI-style $_SERVER keys are missing.
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
        $port = Request::server('SERVERPORT');

        return $port && ! in_array($port, ['80', '443'], true)
            ? $ip.':'.$port
            : $ip;
    }
}
