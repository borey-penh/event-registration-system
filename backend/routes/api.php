<?php

use App\Http\Controllers\AppInfoController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CandidateAuthController;
use App\Http\Controllers\CandidateController;
use App\Http\Controllers\CheckInController;
use App\Http\Controllers\DashboardStatsController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\RegistrationController;
use App\Http\Middleware\EnsureCandidate;
use Illuminate\Support\Facades\Route;

// ---------- Public (candidate-facing) ----------
Route::prefix('register/{token}')->group(function () {
    Route::get('/', [RegistrationController::class, 'show']);
    Route::post('/', [RegistrationController::class, 'submit']);
});

Route::get('/app-info', [AppInfoController::class, 'show']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/lookup', [RegistrationController::class, 'lookup']);

// ---------- Candidate accounts (event attendees; separate from managers) ----------
Route::prefix('candidate')->group(function () {
    // "register" doubles as first-time sign-up: if the email is new the account
    // is created, otherwise it verifies the password and behaves like login.
    Route::post('/register', [CandidateAuthController::class, 'register']);
    Route::post('/login', [CandidateAuthController::class, 'login']);

    // Social login (Google / Facebook). The callback is a full-page browser
    // redirect from the provider, so it lives outside the /api JSON world and
    // returns a 302 back to the SPA.
    Route::post('/social/redirect', [CandidateAuthController::class, 'socialRedirect']);
    Route::get('/social/{provider}/callback', [CandidateAuthController::class, 'socialCallback'])->whereIn('provider', ['google', 'facebook']);

    Route::middleware(['auth:sanctum', EnsureCandidate::class])->group(function () {
        Route::post('/social/exchange', [CandidateAuthController::class, 'socialExchange']);
        Route::post('/logout', [CandidateAuthController::class, 'logout']);
        // Form definition + the candidate's previously saved answers (prefill).
        Route::get('/my/registration/{token}', [CandidateAuthController::class, 'myRegistration']);
    });
});

// ---------- Manager (token-protected) ----------
Route::middleware('auth:sanctum')->group(function () {
    // Auth
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Events
    Route::apiResource('events', EventController::class);
    Route::put('/events/{event}/questions', [EventController::class, 'saveQuestions']);
    Route::post('/events/{event}/candidates', [EventController::class, 'addCandidate']);

    // Dashboard (SQL-only counts, safe for millions of rows)
    Route::get('/dashboard-stats', DashboardStatsController::class);

    // Candidates
    Route::get('/candidates', [CandidateController::class, 'index']);
    Route::get('/candidates/{candidate}', [CandidateController::class, 'show']);

    // Check-in
    Route::post('/check-in', [CheckInController::class, 'scan']);
    Route::post('/check-in/{registration}/undo', [CheckInController::class, 'undo']);
    Route::get('/events/{event}/stats', [CheckInController::class, 'stats']);
});
