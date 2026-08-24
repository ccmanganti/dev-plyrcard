<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\PlyrcardSystemEmailService;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;

class LockerRoomPasswordResetController extends Controller
{
    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email:rfc', 'max:255'],
        ]);

        $email = strtolower(trim((string) $validated['email']));
        $mailFailure = null;

        try {
            Password::broker()->sendResetLink(
                ['email' => $email],
                function (CanResetPassword $resettable, string $token) use (&$mailFailure): void {
                    if (! $resettable instanceof User) {
                        $mailFailure = 'The password reset account type is not supported.';

                        return;
                    }

                    $result = app(PlyrcardSystemEmailService::class)->sendPasswordReset($resettable, $token);

                    if (! ($result['success'] ?? false)) {
                        $mailFailure = (string) ($result['error'] ?? 'Unable to send the password reset email.');
                    }
                },
            );
        } catch (\Throwable $exception) {
            Log::error('PLYRCARD Locker Room password reset request failed.', [
                'email' => $email,
                'error' => $exception->getMessage(),
            ]);

            $mailFailure = 'Unable to send the password reset email right now.';
        }

        if ($mailFailure !== null) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $mailFailure,
                ], 500);
            }

            return back()->withErrors(['email' => $mailFailure])->onlyInput('email');
        }

        // Never expose whether an email address exists in PLYRCARD.
        $message = 'If a PLYRCARD account exists for that email, a password reset link has been sent.';

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => $message,
            ]);
        }

        return back()->with('status', $message);
    }
}
