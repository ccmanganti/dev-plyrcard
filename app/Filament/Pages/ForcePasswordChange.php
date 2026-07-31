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

        $user = auth()->user();

        if ($this->shouldBypassPasswordChange($user) || ! (bool) $user->must_change_password) {
            $this->redirect(filament()->getUrl(), navigate: false);
            return;
        }
    }

    protected function shouldBypassPasswordChange($user): bool
    {
        if (method_exists($user, 'isSuperadminOrImpersonating')) {
            return $user->isSuperadminOrImpersonating();
        }

        return method_exists($user, 'hasRole') && $user->hasRole('superadmin');
    }

    public function save(): void
    {
        $validated = $this->validate([
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()],
        ], [
            'password.confirmed' => 'The password confirmation does not match.',
        ]);

        $user = auth()->user();
        abort_if($this->shouldBypassPasswordChange($user), 403);

        $user->forceFill([
            'password' => Hash::make($validated['password']),
            'must_change_password' => false,
        ])->save();

        session()->put('password_hash_' . Auth::getDefaultDriver(), $user->getAuthPassword());
        $this->reset('password', 'password_confirmation');

        Notification::make()->title('Password updated')->body('Your account is now secured. Welcome to PLYRCARD.')->success()->send();

        $this->redirect(filament()->getUrl(), navigate: false);
    }

    public function getTitle(): string | Htmlable
    {
        return 'Set your password';
    }
}