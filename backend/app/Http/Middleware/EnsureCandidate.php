<?php

namespace App\Http\Middleware;

use App\Models\CandidateAccount;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the candidate-authenticated endpoints (/api/my/*).
 * Managers' tokens from the users table are rejected here —
 * only CandidateAccount tokens pass.
 */
class EnsureCandidate
{
    public function handle(Request $request, Closure $next): Response
    {
        $account = $request->user();

        if (! $account instanceof CandidateAccount) {
            return response()->json(['message' => 'Candidate authentication required.'], 401);
        }

        return $next($request);
    }
}
