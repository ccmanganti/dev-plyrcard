<?php

namespace App\Http\Controllers;

use App\Mail\RegistrationVerificationMail;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
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

    public function resend(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user, 401);

        if (! $user->hasVerifiedEmail()) {
            $url = URL::temporarySignedRoute('registration.verify-email', now()->addHours(72), [
                'user' => $user->getKey(),
                'hash' => sha1($user->getEmailForVerification()),
            ]);
            Mail::to($user->email)->send(new RegistrationVerificationMail($user, $url));
            if (Schema::hasColumn('users', 'email_verification_sent_at')) {
                $user->forceFill(['email_verification_sent_at' => now()])->saveQuietly();
            }
        }

        return back()->with('status', 'verification-link-sent');
    }
}
