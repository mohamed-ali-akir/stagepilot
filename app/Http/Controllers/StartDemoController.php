<?php

namespace App\Http\Controllers;

use App\Services\DemoAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * "Essayer la démo" button on the home page: logs the visitor into the shared demo account, without a password.
 */
class StartDemoController extends Controller
{
    public function __invoke(Request $request, DemoAccount $demo): RedirectResponse
    {
        abort_unless(config('stagepilot.demo_enabled'), 404);

        Auth::login($demo->user());
        $request->session()->regenerate();

        return redirect()->route('dashboard');
    }
}
