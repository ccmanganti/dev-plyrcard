<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\PlyrcardSystemEmailService;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;

class RegistrationEmailVerificationController extends Controller
{
    public function verify(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->hasValidSignature(), 403);
        abort_unless(hash_equals((string) $request->route('hash'), sha1($user->getEmailForVerification())), 403);

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            event(new Verified($user));
        }

        Auth::login($user, true);
        $request->session()->regenerate();

        return redirect('/admin/my-profile?email_verified=1');
    }

    public function resend(Request $request, PlyrcardSystemEmailService $systemEmail): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user, 401);

        if ($user->hasVerifiedEmail()) {
            return back()->with('status', 'email-already-verified');
        }

        $url = URL::temporarySignedRoute('registration.verify-email', now()->addHours(72), [
            'user' => $user->getKey(),
            'hash' => sha1($user->getEmailForVerification()),
        ]);

        $result = $systemEmail->sendRegistrationVerification($user, $url);

        if ($result['success'] ?? false) {
            if (Schema::hasColumn('users', 'email_verification_sent_at')) {
                $user->forceFill(['email_verification_sent_at' => now()])->saveQuietly();
            }

            return back()->with('status', 'verification-link-sent');
        }

        return back()
            ->with('status', 'verification-link-failed')
            ->withErrors([
                'email' => 'We could not send the verification email right now. Please try again.',
            ]);
    }
}