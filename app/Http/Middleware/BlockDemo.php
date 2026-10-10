<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pages the demo account must not use: account settings (email, password, deletion, two-factor, passkeys).
 * Every visitor shares the same demo account, so nobody may lock the others out.
 */
class BlockDemo
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->is_demo) {
            return redirect()->route('dashboard')
                ->with('demo_blocked', 'Cette page est désactivée dans la démo.');
        }

        return $next($request);
    }
}
