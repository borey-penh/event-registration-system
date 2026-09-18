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
*/

Route::get('/{any}', function (?string $any = null) {
    // Serve real files from public/app (js/css/svg assets) when the web server
    // does not handle static files itself; path traversal is blocked via realpath.
    $base = realpath(public_path('app'));
    $file = realpath(public_path('app/'.($any ?? '')));
    if ($file && is_file($file) && str_starts_with($file, $base.DIRECTORY_SEPARATOR)) {
        return response()->file($file);
    }

    $response = response()->file(public_path('app/index.html'));
    $response->headers->set('Cache-Control', 'no-cache');

    return $response;
})->where('any', '^(?!api/).*$')->name('spa');
