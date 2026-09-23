<?php

namespace App\Http\Controllers;

use App\Models\Candidate;
use App\Models\Event;
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
        $weekAgo = now()->subWeek();

        $recentEvents = Event::query()
            ->withCount('registrations')
            ->latest()
            ->limit(10)
            ->get(['id', 'event_code', 'title', 'description', 'status']);

        return response()->json([
            'events' => Event::query()->count(),
            'active_events' => Event::query()->where('status', 'open')->count(),
            'candidates' => $candidateCount,
            'registrations' => (clone $regBase)->count(),
            'joined' => (clone $regBase)->where('attendance_status', 'joined')->count(),
            'registrations_this_week' => (clone $regBase)
                ->where('registered_at', '>=', $weekAgo)
                ->count(),
            'candidates_this_week' => Candidate::query()
                ->where('created_at', '>=', $weekAgo)
                ->count(),
            'recent_events' => $recentEvents,
            'recent_activity' => $this->recentActivity(),
        ]);
    }

    /**
     * Merged activity feed: latest registrations + events that changed
     * status recently, newest first. Bounded to two small queries so the
     * dashboard stays cheap.
     *
     * @return list<array{type: string, title: string, detail: string, at: string}>
     */
    private function recentActivity(): array
    {
        $items = [];

        $registrations = Registration::query()
            ->with(['candidate:id,candidate_code,name', 'event:id,event_code,title'])
            ->latest('registered_at')
            ->limit(5)
            ->get(['id', 'event_id', 'candidate_id', 'candidate_account_id', 'registered_at', 'attendance_status']);

        foreach ($registrations as $reg) {
            $items[] = [
                'type' => 'registration',
                'title' => 'New registration for '.($reg->event->event_code ?? 'event'),
                // Registrations submitted from a candidate account are online
                // sign-ups; anything else was entered by a manager.
                'detail' => ($reg->candidate->name ?? 'Candidate')
                    .' • '.($reg->candidate_account_id ? 'Online Form' : 'Added by Manager'),
                'at' => optional($reg->registered_at)->toISOString() ?? '',
            ];
        }

        $closedEvents = Event::query()
            ->where('status', 'closed')
            ->where('updated_at', '>=', now()->subDays(14))
            ->latest('updated_at')
            ->limit(3)
            ->get(['id', 'event_code', 'status', 'updated_at']);

        foreach ($closedEvents as $event) {
            $items[] = [
                'type' => 'status',
                'title' => 'Status updated: '.$event->event_code.' closed',
                'detail' => $event->title,
                'at' => optional($event->updated_at)->toISOString() ?? '',
            ];
        }

        usort($items, fn ($a, $b) => strcmp($b['at'], $a['at']));

        return array_slice($items, 0, 5);
    }
}
