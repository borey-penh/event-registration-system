<?php

namespace App\Http\Controllers;

use App\Models\Candidate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CandidateController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        // Server-side pagination keeps memory + payloads small even with 1,000,000+ candidates.
        $perPage = min((int) $request->query('per_page', '20'), 100);

        $query = Candidate::query()
            ->with('registrations.event:id,title')
            ->withCount('registrations');

        if ($search = trim((string) $request->query('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('candidate_code', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('institution', 'like', "%{$search}%");
            });
        }

        $page = $query->orderBy('id', 'desc')->paginate($perPage);

        return response()->json($page->toArray());
    }

    public function show(Candidate $candidate): JsonResponse
    {
        return response()->json($candidate->load('registrations.event', 'registrations.answers.question'));
    }
}
