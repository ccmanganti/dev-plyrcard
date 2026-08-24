<?php

namespace App\Filament\Pages\Auth\PasswordReset;

use App\Models\User;
use App\Services\PlyrcardSystemEmailService;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;

// Filament moved auth pages under Filament\Auth\Pages in newer releases.
// Alias whichever base exists so this hotfix remains compatible with the
// namespace used by the currently installed PLYRCARD Filament version.
if (class_exists(\Filament\Auth\Pages\PasswordReset\RequestPasswordReset::class)) {
    class_alias(
        \Filament\Auth\Pages\PasswordReset\RequestPasswordReset::class,
        __NAMESPACE__ . '\\PlyrcardFilamentRequestPasswordResetBase',
    );
} elseif (class_exists(\Filament\Pages\Auth\PasswordReset\RequestPasswordReset::class)) {
    class_alias(
        \Filament\Pages\Auth\PasswordReset\RequestPasswordReset::class,
        __NAMESPACE__ . '\\PlyrcardFilamentRequestPasswordResetBase',
    );
} else {
    throw new \RuntimeException('Unable to locate the Filament password reset request page class.');
}

class RequestPasswordReset extends PlyrcardFilamentRequestPasswordResetBase
{
    public function request(): void
    {
        try {
            $this->rateLimit(2);
        } catch (TooManyRequestsException $exception) {
            $this->getRateLimitedNotification($exception)?->send();

            return;
        }

        $data = $this->form->getState();
        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $mailFailure = null;

        try {
            Password::broker(Filament::getAuthPasswordBroker())->sendResetLink(
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
            Log::error('PLYRCARD Filament password reset request failed.', [
                'email' => $email,
                'error' => $exception->getMessage(),
            ]);

            $mailFailure = 'Unable to send the password reset email right now.';
        }

        if ($mailFailure !== null) {
            Notification::make()
                ->title('Unable to send reset link')
                ->body($mailFailure)
                ->danger()
                ->send();

            return;
        }

        // Use the same generic response for existing and unknown addresses.
        Notification::make()
            ->title('Check your email')
            ->body('If a PLYRCARD account exists for that email, a password reset link has been sent.')
            ->success()
            ->send();

        // Clear the form after a successful/generic response, matching Filament's UX.
        $this->form->fill();
    }
}