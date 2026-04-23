<?php

namespace App\Filament\Pages;

use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class ForcePasswordChange extends Page
{
    protected string $view = 'filament.pages.force-password-change';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'force-password-change';

    public string $password = '';
    public string $password_confirmation = '';

    public function mount(): void
    {
        abort_unless(auth()->check(), 403);

        if (\STS\FilamentImpersonate\Facades\Impersonation::isImpersonating()) {
            $this->redirect(filament()->getUrl(), navigate: false);
            return;
        }
    }

    protected function shouldBypassPasswordChange($user): bool
    {
        if ($user->hasRole('superadmin')) {
            return true;
        }

        if (app()->bound('impersonate')) {
            $manager = app('impersonate');

            if (method_exists($manager, 'isImpersonating') && $manager->isImpersonating()) {
                $impersonatorId = method_exists($manager, 'getImpersonatorId')
                    ? $manager->getImpersonatorId()
                    : null;

                if ($impersonatorId) {
                    $impersonator = \App\Models\User::find($impersonatorId);

                    return $impersonator?->hasRole('superadmin') ?? false;
                }
            }
        }

        $sessionKey = config('laravel-impersonate.session_key', 'impersonated_by');
        $impersonatorId = session($sessionKey);

        if ($impersonatorId) {
            $impersonator = \App\Models\User::find($impersonatorId);

            return $impersonator?->hasRole('superadmin') ?? false;
        }

        return false;
    }

    public function save(): void
    {
        $this->validate([
            'password' => ['required', Password::defaults()],
            'password_confirmation' => ['required', 'same:password'],
        ]);

        $user = auth()->user();

        $user->password = Hash::make($this->password);
        $user->must_change_password = false;
        $user->save();

        session()->put(
            'password_hash_' . Auth::getDefaultDriver(),
            $user->getAuthPassword()
        );

        Notification::make()
            ->title('Password updated')
            ->body('Your account is now secured.')
            ->success()
            ->send();

        // Force a real page reload so onboarding hook runs reliably.
        $this->redirect(filament()->getUrl(), navigate: false);
    }

    public function getTitle(): string | Htmlable
    {
        return 'Security';
    }
}