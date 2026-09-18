<?php

namespace App\Http\Controllers;

use App\Models\Candidate;
use App\Models\CandidateAccount;
use App\Models\Event;
use App\Models\FormQuestion;
use App\Models\Registration;
use App\Models\RegistrationAnswer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\In;
use Illuminate\Validation\ValidationException;

class RegistrationController extends Controller
{
    /** Public: form definition for a registration link. */
    public function show(string $token): JsonResponse
    {
        $event = Event::where('registration_token', $token)->firstOrFail();

        if (! $event->canCandidateEdit()) {
            abort(403, 'This event is closed, so registrations can no longer be changed.');
        }

        return response()->json([
            'event' => [
                'event_code' => $event->event_code,
                'title' => $event->title,
                'description' => $event->description,
                'location' => $event->location,
                'start_date' => $event->start_date?->toDateString(),
                'start_time' => $event->start_time?->format('H:i'),
                'end_date' => $event->end_date?->toDateString(),
            ],
            'questions' => $event->questions->map(fn (FormQuestion $q) => [
                'id' => $q->id,
                'question' => $q->question,
                'type' => $q->type,
                'required' => $q->required,
                'options' => $q->options,
                'order' => $q->order,
            ]),
        ]);
    }

    /** Public: submit registration, create candidate + registration + answers, return candidate QR token. */
    public function submit(Request $request, string $token): JsonResponse
    {
        $event = Event::where('registration_token', $token)->firstOrFail();

        if (! $event->canCandidateEdit()) {
            abort(403, 'This event is closed, so registrations can no longer be changed.');
        }

        // Optional candidate auth: when the SPA sends a candidate Bearer token,
        // the submission is linked to that account so it can be edited later.
        // The sanctum guard resolves the token to its owner model (User OR
        // CandidateAccount), so we keep it only if it is a CandidateAccount.
        $account = $request->user('sanctum');
        $account = $account instanceof CandidateAccount ? $account : null;

        $questions = $event->questions;

        $rules = [];

        foreach ($questions as $q) {
            $rule = $q->required ? ['required'] : ['nullable'];

            $rule = match ($q->type) {
                'radio', 'select' => [...$rule, new In($q->options ?? [])],
                'checkbox' => [...$rule, 'array'],
                'date' => [...$rule, 'date'],
                default => $rule,
            };

            if ($q->type === 'checkbox') {
                $rules["answers.{$q->id}"] = $rule;
                $rules["answers.{$q->id}.*"] = [new In($q->options ?? [])];
            } else {
                $rules["answers.{$q->id}"] = $rule;
            }
        }

        $data = $request->validate($rules);

        // Candidate identity comes from the form answers, matched by question text.
        $answerFor = function (array $patterns) use ($data, $questions) {
            foreach ($questions as $q) {
                foreach ($patterns as $pattern) {
                    if (mb_stripos($q->question, $pattern) !== false) {
                        $answer = $data['answers'][$q->id] ?? null;
                        if (is_array($answer)) {
                            $answer = implode(', ', $answer);
                        }
                        $answer = trim((string) $answer);

                        return $answer !== '' ? $answer : null;
                    }
                }
            }

            return null;
        };

        $name = $answerFor(['ឈ្មោះអ្នកចូលរួម', 'ឈ្មោះពេញ', 'full name', 'name']);

        if ($name === null) {
            throw ValidationException::withMessages([
                'answers' => ['This form must include a full name question.'],
            ]);
        }

        $registration = DB::transaction(function () use ($event, $data, $questions, $answerFor, $name, $account) {
            // Re-submission: reuse this event's candidate with the same email
            // (case/whitespace-insensitive) instead of creating a duplicate row
            // for every edit. Keeps the candidate list clean.
            $email = $answerFor(['អ៊ីម៉ែល', 'email', 'imeil']);
            $candidate = null;
            if ($email !== null) {
                $candidate = Candidate::whereRaw('LOWER(TRIM(email)) = ?', [mb_strtolower(trim($email))])
                    ->whereExists(function ($q) use ($event) {
                        $q->selectRaw(1)
                            ->from('registrations')
                            ->whereColumn('registrations.candidate_id', 'candidates.id')
                            ->where('registrations.event_id', $event->id);
                    })
                    ->orderByDesc('id')
                    ->first();
            }

            $candidate ??= Candidate::create([
                'candidate_code' => Candidate::nextCandidateCode(),
                'name' => $name,
                'email' => $email,
                'phone' => $answerFor(['លេខទូរស័ព្ទ', 'phone']),
                'telegram_username' => $answerFor(['telegram', 'តេលីក្រាម']),
                'institution' => $answerFor(['ស្ថាប័ន', 'institution', 'school', 'university']),
                'role' => $answerFor(['តួនាទី', 'role']),
            ]);

            // Edit flow: re-submitting updates the existing registration (and
            // its answers) instead of duplicating it — matched by candidate
            // account when logged in, otherwise by the candidate row resolved
            // above so anonymous re-submissions don't stack up either.
            $registration = null;

            if ($account) {
                $registration = Registration::where('candidate_account_id', $account->id)
                    ->where('event_id', $event->id)
                    ->orderByDesc('id')
                    ->first();
            }

            if (! $registration && ! $candidate->wasRecentlyCreated) {
                $registration = Registration::where('candidate_id', $candidate->id)
                    ->where('event_id', $event->id)
                    ->orderByDesc('id')
                    ->first();
            }

            if (! $registration) {
                $registration = new Registration;
                $registration->qr_token = 'REG-'.now()->format('Y').'-'.strtoupper(Str::random(8));
                $registration->registered_at = now();
            }

            $registration->event_id = $event->id;
            $registration->candidate_id = $candidate->id;
            // Keep an existing account link when this submission is anonymous.
            $registration->candidate_account_id = $account?->id ?? $registration->candidate_account_id;
            $registration->attendance_status = $registration->attendance_status ?? 'registered';
            $registration->save();

            // Replace the saved answers with the latest submission.
            RegistrationAnswer::where('registration_id', $registration->id)->delete();

            foreach ($questions as $q) {
                $answer = $data['answers'][$q->id] ?? null;

                if (is_array($answer)) {
                    $answer = implode(', ', $answer);
                }

                RegistrationAnswer::create([
                    'registration_id' => $registration->id,
                    'question_id' => $q->id,
                    'answer' => $answer !== null ? (string) $answer : null,
                ]);
            }

            return $registration;
        });

        // True when the transaction updated an existing registration rather
        // than creating one (Eloquent tracks creation per model instance).
        $isUpdate = ! $registration->wasRecentlyCreated;

        return response()->json([
            'message' => $isUpdate ? 'Registration updated' : 'Registration successful',
            'registration_id' => $registration->id,
            'updated' => $isUpdate,
            'candidate' => [
                'candidate_code' => $registration->candidate->candidate_code,
                'name' => $registration->candidate->name,
            ],
            'qr_token' => $registration->qr_token,
        ], $isUpdate ? 200 : 201);
    }

    /** Public: lookup a registration by qr_token (used to render candidate QR payload / re-show). */
    public function lookup(Request $request): JsonResponse
    {
        $data = $request->validate([
            'qr_token' => ['required', 'string'],
        ]);

        $registration = Registration::with('candidate', 'event')
            ->where('qr_token', $data['qr_token'])
            ->firstOrFail();

        return response()->json([
            'registration_id' => $registration->id,
            'qr_token' => $registration->qr_token,
            'candidate' => $registration->candidate->only('name', 'candidate_code'),
            'event_title' => $registration->event->title,
            'attendance_status' => $registration->attendance_status,
            'registered_at' => $registration->registered_at?->toDateTimeString(),
        ]);
    }
}
