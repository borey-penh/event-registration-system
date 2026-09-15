<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CandidateController;
use App\Http\Controllers\CheckInController;
use App\Http\Controllers\DashboardStatsController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\RegistrationController;
use Illuminate\Support\Facades\Route;

// ---------- Public (candidate-facing) ----------
Route::prefix('register/{token}')->group(function () {
    Route::get('/', [RegistrationController::class, 'show']);
    Route::post('/', [RegistrationController::class, 'submit']);
});

Route::post('/login', [AuthController::class, 'login']);
Route::post('/lookup', [RegistrationController::class, 'lookup']);

// ---------- Manager (token-protected) ----------
Route::middleware('auth:sanctum')->group(function () {
    // Auth
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Events
    Route::apiResource('events', EventController::class);
    Route::put('/events/{event}/questions', [EventController::class, 'saveQuestions']);

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
