<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Registration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CheckInController extends Controller
{
    /** Scan a candidate QR token at the event door. */
    public function scan(Request $request): JsonResponse
    {
        $data = $request->validate([
            'qr_token' => ['required', 'string'],
        ]);

        $registration = Registration::with(['candidate', 'event'])
            ->where('qr_token', $data['qr_token'])
            ->first();

        if (! $registration) {
            return response()->json([
                'status' => 'not_found',
                'message' => 'QR code not recognized. This candidate may not be registered.',
            ], 404);
        }

        if ($registration->attendance_status === 'joined') {
            return response()->json([
                'status' => 'already_joined',
                'message' => 'Warning: candidate already checked in!',
                'candidate' => $registration->candidate->only('name', 'candidate_code'),
                'event_title' => $registration->event->title,
                'joined_at' => $registration->joined_at?->toDateTimeString(),
            ]);
        }

        $registration->update([
            'attendance_status' => 'joined',
            'joined_at' => now(),
        ]);

        return response()->json([
            'status' => 'joined',
            'message' => 'Check-in successful. Welcome!',
            'candidate' => $registration->candidate->only('name', 'candidate_code'),
            'event_title' => $registration->event->title,
            'joined_at' => $registration->joined_at?->toDateTimeString(),
        ]);
    }

    /** Undo a check-in (accidental scan). */
    public function undo(Request $request, Registration $registration): JsonResponse
    {
        $registration->update([
            'attendance_status' => 'registered',
            'joined_at' => null,
        ]);

        return response()->json(['message' => 'Check-in reverted']);
    }

    /** Live event stats for dashboard. */
    public function stats(Request $request, Event $event): JsonResponse
    {
        $registrations = $event->registrations();

        return response()->json([
            'total' => (clone $registrations)->count(),
            'joined' => (clone $registrations)->where('attendance_status', 'joined')->count(),
        ]);
    }
}
