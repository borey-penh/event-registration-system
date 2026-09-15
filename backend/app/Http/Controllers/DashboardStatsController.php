<?php

namespace App\Http\Controllers;

use App\Models\Candidate;
use App\Models\Registration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardStatsController extends Controller
{
    /**
     * All counts computed in SQL — fast even with millions of rows
     * (never loads models into memory).
     */
    public function __invoke(Request $request): JsonResponse
    {
        $candidateCount = Candidate::query()->count();

        $regBase = Registration::query();

        return response()->json([
            'events' => \App\Models\Event::query()->count(),
            'candidates' => $candidateCount,
            'registrations' => (clone $regBase)->count(),
            'joined' => (clone $regBase)->where('attendance_status', 'joined')->count(),
            'recent_events' => \App\Models\Event::query()
                ->withCount('registrations')
                ->latest()
                ->limit(5)
                ->get(['id', 'event_code', 'title', 'status']),
        ]);
    }
}
