<?php

namespace App\Http\Controllers;

use App\Models\Candidate;
use App\Models\CandidateAccount;
use App\Models\Registration;
use App\Models\RegistrationAnswer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CandidateController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        // Server-side pagination keeps memory + payloads small even with 1,000,000+ candidates.
        $perPage = min((int) $request->query('per_page', '20'), 100);

        $query = Candidate::query();

        if ($search = trim((string) $request->query('search'))) {
            $matchingIds = Candidate::query()->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('candidate_code', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('institution', 'like', "%{$search}%")
                    ->orWhere('role', 'like', "%{$search}%")
                    // Match event titles (and codes) of the candidate's registrations.
                    ->orWhereHas('registrations.event', function ($e) use ($search) {
                        $e->where('title', 'like', "%{$search}%")
                            ->orWhere('event_code', 'like', "%{$search}%");
                    });
            })->pluck('id');

            // A person matches when ANY of their duplicate rows matches, so
            // also pull in the sibling rows sharing a matching row's email.
            $emails = Candidate::query()
                ->whereIn('id', $matchingIds)
                ->get(['email'])
                ->map(fn ($c) => $c->email !== null ? mb_strtolower(trim($c->email)) : null)
                ->filter()
                ->unique()
                ->values();

            $siblingIds = $emails->isNotEmpty()
                ? Candidate::query()->whereIn(DB::raw('LOWER(TRIM(email))'), $emails->all())->pluck('id')
                : collect();

            $query->whereIn('candidates.id', $matchingIds->merge($siblingIds)->unique()->values());
        }

        // One row per person: a person is one candidate ROW per event, so rows
        // sharing an email are duplicates. Keep only the newest row per email
        // (its merged event list across all sibling rows is attached below).
        // Rows without a real email can't be matched, so each stays its own row.
        $query->whereNotExists(function ($q) {
            $q->selectRaw(1)
                ->from('candidates as newer')
                ->whereRaw("LOWER(TRIM(newer.email)) = LOWER(TRIM(candidates.email)) AND TRIM(candidates.email) <> ''")
                ->whereColumn('newer.id', '>', 'candidates.id');
        });

        $page = $query->orderByDesc('id')->paginate($perPage);

        // Attach EVERY event the person has registered for. A person is one
        // candidate ROW per event, so a list row only carries its own event;
        // merge duplicate rows (same email / same login account) exactly like
        // the detail view does, otherwise the Events column under-reports.
        $people = $page->getCollection();

        $emails = [];
        foreach ($people as $c) {
            $email = $c->email !== null ? mb_strtolower(trim($c->email)) : null;
            if ($email !== null && $email !== '') {
                $emails[$email] = true;
            }
        }

        // Login accounts whose email matches a candidate email on this page.
        $accounts = collect();
        if ($emails !== []) {
            $accounts = CandidateAccount::query()
                ->whereIn(DB::raw('LOWER(email)'), array_keys($emails))
                ->get(['id', 'email']);
        }

        // Every candidate row sharing a listed email (across the whole table).
        $relatedCandidates = $emails !== []
            ? Candidate::query()
                ->whereIn(DB::raw('LOWER(TRIM(email))'), array_keys($emails))
                ->get(['id', 'email'])
            : collect();

        $candidateIdsByEmail = [];
        foreach ($relatedCandidates as $rc) {
            $key = $rc->email !== null ? mb_strtolower(trim($rc->email)) : null;
            if ($key !== null && $key !== '') {
                $candidateIdsByEmail[$key][] = $rc->id;
            }
        }

        $accountIdsByEmail = [];
        foreach ($accounts as $acc) {
            $key = $acc->email !== null ? mb_strtolower(trim($acc->email)) : null;
            if ($key !== null && $key !== '') {
                $accountIdsByEmail[$key][] = $acc->id;
            }
        }

        $allAccountIds = $accounts->pluck('id')->unique()->values();
        // ALWAYS include the listed rows themselves — most have no email, and
        // without their own ids their registrations would never be fetched.
        $relatedCandidateIds = $people->pluck('id')
            ->merge($relatedCandidates->pluck('id'))
            ->unique()
            ->values();

        $regs = Registration::query()
            ->select(['registrations.id', 'registrations.candidate_id', 'registrations.candidate_account_id', 'registrations.event_id', 'registrations.attendance_status'])
            ->with('event:id,title')
            ->where(function ($q) use ($relatedCandidateIds, $allAccountIds) {
                if ($relatedCandidateIds->isNotEmpty()) {
                    $q->orWhereIn('registrations.candidate_id', $relatedCandidateIds);
                }
                if ($allAccountIds->isNotEmpty()) {
                    $q->orWhereIn('registrations.candidate_account_id', $allAccountIds);
                }
            })
            ->orderByDesc('registrations.id')
            ->get();

        $regsByCandidate = $regs->groupBy('candidate_id');
        $regsByAccount = $regs->groupBy('candidate_account_id');

        foreach ($people as $c) {
            $email = $c->email !== null ? mb_strtolower(trim($c->email)) : null;

            $cids = collect([$c->id]);
            if ($email !== null && isset($candidateIdsByEmail[$email])) {
                $cids = $cids->merge($candidateIdsByEmail[$email]);
            }

            $aids = $email !== null && isset($accountIdsByEmail[$email])
                ? collect($accountIdsByEmail[$email])
                : collect();

            $personRegs = collect();
            foreach ($cids->unique() as $cid) {
                $personRegs = $personRegs->merge($regsByCandidate->get($cid, collect()));
            }
            foreach ($aids->unique() as $aid) {
                $personRegs = $personRegs->merge($regsByAccount->get($aid, collect()));
            }

            $c->all_registrations = $personRegs
                ->sortByDesc('id')
                // Re-submissions can leave several registrations for the SAME
                // event — show the person one entry per event, the latest one.
                ->unique(fn ($r) => $r->event_id)
                ->values()
                ->map(fn ($r) => [
                    'id' => $r->id,
                    'event_id' => $r->event_id,
                    'event' => $r->event ? ['id' => $r->event->id, 'title' => $r->event->title] : null,
                    'attendance_status' => $r->attendance_status,
                ])
                ->values();
        }

        return response()->json($page->toArray());
    }

    public function show(Candidate $candidate): JsonResponse
    {
        // A person appears as ONE candidate row per event (rows are created
        // per event on submission), so loading just this row's registrations
        // would show a single event forever. Treat every candidate row that
        // shares the login account or the same (case-insensitive) email as
        // the same person and collect ALL of their registrations.
        $email = $candidate->email !== null ? mb_strtolower(trim($candidate->email)) : null;

        $accountIds = CandidateAccount::query()
            ->when($email !== null, fn ($q) => $q->whereRaw('LOWER(email) = ?', [$email]))
            ->pluck('id');

        $registrations = Registration::query()
            ->with([
                'event:id,title,event_code,start_date,status',
                // The event's form definition, so EVERY question can be shown
                // (unanswered ones render as "—" in the SPA) — not just the
                // rows that happen to have a stored answer.
                'event.questions:id,event_id,question,order',
                'answers:id,registration_id,question_id,answer',
            ])
            ->where(function ($q) use ($candidate, $email, $accountIds) {
                $q->whereHas('candidate', function ($c) use ($candidate, $email) {
                    // No email on this row: fall back to just its own registrations.
                    $email !== null
                        ? $c->whereRaw('LOWER(TRIM(email)) = ?', [$email])
                        : $c->whereKey($candidate->id);
                });

                if ($accountIds->isNotEmpty()) {
                    $q->orWhereIn('candidate_account_id', $accountIds);
                }
            })
            ->orderByDesc('id')
            ->get()
            // Re-submissions before the edit-flow dedup (or via unmatched
            // duplicate rows) can leave several registrations for the SAME
            // event. Show the person one entry per event — the latest one.
            ->unique(fn (Registration $reg) => $reg->event_id)
            ->values();

        return response()->json([
            'id' => $candidate->id,
            'candidate_code' => $candidate->candidate_code,
            'name' => $candidate->name,
            'email' => $candidate->email,
            'phone' => $candidate->phone,
            'telegram_username' => $candidate->telegram_username,
            'institution' => $candidate->institution,
            'role' => $candidate->role,
            'registrations' => $registrations->map(fn (Registration $reg) => [
                'id' => $reg->id,
                'event' => $reg->event,
                'attendance_status' => $reg->attendance_status,
                'registered_at' => $reg->registered_at?->toDateTimeString(),
                'joined_at' => $reg->joined_at?->toDateTimeString(),
                'qr_token' => $reg->qr_token,
                // One entry per form question, in form order; null answer =
                // the candidate left it blank (or registered via walk-in).
                'answers' => ($reg->event?->questions ?? collect())
                    ->map(function ($q) use ($reg) {
                        $stored = $reg->answers->firstWhere('question_id', $q->id);

                        return [
                            'question_id' => $q->id,
                            'question' => ['id' => $q->id, 'question' => $q->question],
                            'answer' => $stored->answer ?? null,
                        ];
                    })
                    ->values(),
            ])->values(),
        ]);
    }
}
