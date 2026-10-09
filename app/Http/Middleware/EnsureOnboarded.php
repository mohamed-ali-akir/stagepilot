<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sends the student to the questionnaire until it is completed.
 */
class EnsureOnboarded
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && ! $request->user()->hasCompletedOnboarding()) {
            return redirect()->route('onboarding');
        }

        return $next($request);
    }
}
