<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| SPA serving (embedded frontend) — assets in public/app/
|--------------------------------------------------------------------------
|
| The built frontend is copied to backend/public/app/ by
| scripts/build-embedded-frontend.sh. Any GET that is not /api/* and not a
| real file inside public/app gets index.html, so the candidate flow
| (/register/{token}, /register-success) and the manager panel (/login, ...)
| work from ONE public URL through the cloudflared tunnel — no CORS needed.
|
| CRITICAL: only *SPA routes* may fall back to index.html. Static assets
| (/app/assets/*.js|css|fonts|images) must 404 when missing — serving HTML
| for a module request yields "200 text/html" for a <script type=module>,
| the browser fails to parse it, nothing mounts, and the candidate sees a
| silent blank page (e.g. a phone with a cached index.html that references
| hashed filenames from before the last rebuild).
|
*/

// A closure variable (not a global function): route files are evaluated more
// than once per process in tests, and redeclaring a global function there is
// a fatal error.
$looksLikeAsset = function (string $path): bool {
    // True when the request path targets a static asset rather than an SPA
    // route. Kept conservative (extension-based) so real routes like
    // /register/REG-123 or /events/7 always fall through to index.html.
    if ($path === '' || ! str_contains($path, '.')) {
        return false;
    }

    // Anything under the built app's assets directory is an asset.
    if (str_starts_with($path, 'app/assets/') || str_starts_with($path, 'assets/')) {
        return true;
    }

    $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

    return in_array($extension, [
        'js', 'mjs', 'css', 'map',
        'svg', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'avif', 'ico',
        'woff', 'woff2', 'ttf', 'otf', 'eot',
        'json', 'txt', 'webmanifest',
    ], true);
};

Route::get('/{any}', function (?string $any = null) use ($looksLikeAsset) {
    // Serve real files from public/app (js/css/svg assets) when the web server
    // does not handle static files itself; path traversal is blocked via realpath.
    $base = realpath(public_path('app'));
    $file = realpath(public_path('app/'.($any ?? '')));
    if ($file && is_file($file) && str_starts_with($file, $base.DIRECTORY_SEPARATOR)) {
        // Vite content-hashes these filenames, so they may be cached forever.
        $response = response()->file($file);
        $response->headers->set('Cache-Control', 'public, max-age=31536000, immutable');

        return $response;
    }

    // Never answer an asset-looking request with HTML: that is what turns a
    // stale-cache reload into a blank page. 404 instead — the SPA's chunk
    // error recovery (vite:preloadError) then reloads and picks up the
    // fresh index.html with the new hashes.
    if ($looksLikeAsset($any ?? '')) {
        return response('', 404)->header('Content-Type', 'text/plain');
    }

    $response = response()->file(public_path('app/index.html'));
    $response->headers->set('Cache-Control', 'no-cache');

    return $response;
})->where('any', '^(?!api/).*$')->name('spa');
