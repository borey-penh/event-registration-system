<?php

namespace App\Http\Controllers;

use App\Models\Candidate;
use App\Models\Event;
use App\Models\FormQuestion;
use App\Models\Registration;
use App\Models\RegistrationAnswer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RegistrationController extends Controller
{
    /** Public: form definition for a registration link. */
    public function show(string $token): JsonResponse
    {
        $event = Event::where('registration_token', $token)
            ->where('status', 'open')
            ->firstOrFail();

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
        $event = Event::where('registration_token', $token)
            ->where('status', 'open')
            ->firstOrFail();

        $questions = $event->questions;

        $rules = [];

        foreach ($questions as $q) {
            $rule = $q->required ? ['required'] : ['nullable'];

            $rule = match ($q->type) {
                'radio', 'select' => [...$rule, new \Illuminate\Validation\Rules\In($q->options ?? [])],
                'checkbox' => [...$rule, 'array'],
                'date' => [...$rule, 'date'],
                default => $rule,
            };

            if ($q->type === 'checkbox') {
                $rules["answers.{$q->id}"] = $rule;
                $rules["answers.{$q->id}.*"] = [new \Illuminate\Validation\Rules\In($q->options ?? [])];
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

        $registration = DB::transaction(function () use ($event, $data, $questions, $answerFor, $name) {
            $candidate = Candidate::create([
                'candidate_code' => 'C-'.strtoupper(Str::random(8)),
                'name' => $name,
                'email' => $answerFor(['អ៊ីម៉ែល', 'email', 'imeil']),
                'phone' => $answerFor(['លេខទូរស័ព្ទ', 'phone']),
                'telegram_username' => $answerFor(['telegram', 'តេលីក្រាម']),
                'institution' => $answerFor(['ស្ថាប័ន', 'institution', 'school', 'university']),
                'role' => $answerFor(['តួនាទី', 'role']),
            ]);

            $registration = Registration::create([
                'event_id' => $event->id,
                'candidate_id' => $candidate->id,
                'qr_token' => 'REG-'.now()->format('Y').'-'.strtoupper(Str::random(8)),
                'registered_at' => now(),
                'attendance_status' => 'registered',
            ]);

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

        return response()->json([
            'message' => 'Registration successful',
            'registration_id' => $registration->id,
            'candidate' => [
                'candidate_code' => $registration->candidate->candidate_code,
                'name' => $registration->candidate->name,
            ],
            'qr_token' => $registration->qr_token,
        ], 201);
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
