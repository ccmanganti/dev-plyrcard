<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\PlyrcardSystemEmailService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Legacy verification endpoints are retained only so old links/routes do not
 * break. PLYRCARD no longer requires email verification.
 */
class RegistrationEmailVerificationController extends Controller
{
    public function verify(Request $request, User $user): RedirectResponse
    {
        Auth::login($user, true);
        $request->session()->regenerate();

        return redirect('/admin');
    }

    public function resend(Request $request, PlyrcardSystemEmailService $systemEmail): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user, 401);

        $planKey = $user->billingInformation?->plan_key;
        $result = $systemEmail->sendRegistrationWelcome($user, url('/admin'), $planKey);

        return ($result['success'] ?? false)
            ? back()->with('status', 'welcome-email-sent')
            : back()->with('status', 'welcome-email-failed')->withErrors([
                'email' => 'We could not send the welcome email right now. Please try again.',
            ]);
    }
}