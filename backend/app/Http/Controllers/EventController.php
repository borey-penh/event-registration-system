<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\FormQuestion;
use App\Models\Registration;
use App\Models\RegistrationAnswer;
use App\Support\DefaultForm;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class EventController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $events = Event::withCount('registrations')
            ->latest()
            ->get();

        return response()->json($events);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'location' => ['nullable', 'string', 'max:255'],
            'start_date' => ['nullable', 'date'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'status' => ['nullable', 'in:draft,open,closed'],
        ]);

        $event = Event::create([
            ...$data,
            'status' => $data['status'] ?? 'draft',
            'event_code' => Event::nextEventCode(),
            'registration_token' => strtoupper(Str::random(10)),
            'created_by' => $request->user()->id,
        ]);

        // Every event starts with the standard form; manager can edit/delete freely afterwards.
        foreach (DefaultForm::questions() as $index => $q) {
            $event->questions()->create([...$q, 'order' => $index + 1]);
        }

        return response()->json($event, 201);
    }

    public function show(Request $request, Event $event): JsonResponse
    {
        // Server-side pagination: an event with 1,000,000+ registrations stays fast.
        $perPage = min((int) $request->query('per_page', '20'), 100);

        $page = $event->registrations()
            ->with('candidate', 'answers')
            ->when(
                $request->filled('search'),
                fn ($q) => $q->whereHas('candidate', fn ($c) => $c->where(function ($w) use ($request) {
                    $s = '%'.trim((string) $request->query('search')).'%';
                    $w->where('name', 'like', $s)
                        ->orWhere('candidate_code', 'like', $s)
                        ->orWhere('phone', 'like', $s)
                        ->orWhere('email', 'like', $s)
                        ->orWhere('institution', 'like', $s);
                }))
            )
            ->orderByDesc('registrations.id')
            ->paginate($perPage);

        $candidates = collect($page->items())->map(fn (Registration $reg) => [
            'registration_id' => $reg->id,
            'candidate_id' => $reg->candidate_id,
            'candidate_code' => $reg->candidate->candidate_code,
            'name' => $reg->candidate->name,
            'email' => $reg->candidate->email,
            'phone' => $reg->candidate->phone,
            'telegram_username' => $reg->candidate->telegram_username,
            'institution' => $reg->candidate->institution,
            'role' => $reg->candidate->role,
            'answers' => $reg->answers->mapWithKeys(
                fn (RegistrationAnswer $a) => [$a->question_id => $a->answer]
            ),
            'qr_token' => $reg->qr_token,
            'attendance_status' => $reg->attendance_status,
            'registered_at' => $reg->registered_at?->toDateTimeString(),
            'joined_at' => $reg->joined_at?->toDateTimeString(),
        ])->values();

        $event->load('questions');

        return response()->json([
            'event' => $event,
            'registration_url' => '/register/'.$event->registration_token,
            'candidates' => $candidates,
            'pagination' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    public function update(Request $request, Event $event): JsonResponse
    {
        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'location' => ['nullable', 'string', 'max:255'],
            'start_date' => ['nullable', 'date'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'status' => ['sometimes', 'in:draft,open,closed'],
        ]);

        $event->update($data);

        return response()->json($event);
    }

    public function destroy(Event $event): JsonResponse
    {
        $event->delete();

        return response()->json(['message' => 'Event deleted']);
    }

    /**
     * Replace the full set of form questions for an event.
     */
    public function saveQuestions(Request $request, Event $event): JsonResponse
    {
        $data = $request->validate([
            'questions' => ['present', 'array'],
            'questions.*.id' => ['nullable', 'integer', 'exists:form_questions,id'],
            'questions.*.question' => ['required_with:questions', 'string', 'max:1000'],
            'questions.*.type' => ['required_with:questions', 'in:text,textarea,radio,checkbox,select,date'],
            'questions.*.required' => ['boolean'],
            'questions.*.options' => ['nullable', 'array'],
            'questions.*.options.*' => ['string', 'max:255'],
            'questions.*.order' => ['integer'],
        ]);

        $keepIds = [];

        foreach ($data['questions'] ?? [] as $index => $q) {
            $payload = [
                'question' => $q['question'],
                'type' => $q['type'],
                'required' => (bool) ($q['required'] ?? false),
                'options' => in_array($q['type'], ['radio', 'checkbox', 'select']) ? array_values($q['options'] ?? []) : null,
                'order' => $q['order'] ?? $index,
            ];

            if (! empty($q['id'])) {
                $question = FormQuestion::where('event_id', $event->id)->find($q['id']);
                if ($question) {
                    $question->update($payload);
                    $keepIds[] = $question->id;
                }
            } else {
                $question = $event->questions()->create($payload);
                $keepIds[] = $question->id;
            }
        }

        // Delete removed questions
        $event->questions()->whereNotIn('id', $keepIds)->delete();

        return response()->json($event->questions()->get());
    }
}
