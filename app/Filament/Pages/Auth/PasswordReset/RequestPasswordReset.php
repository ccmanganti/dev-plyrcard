<?php

namespace App\Filament\Pages\Auth\PasswordReset;

use Filament\Auth\Pages\PasswordReset\RequestPasswordReset as BaseRequestPasswordReset;

class RequestPasswordReset extends BaseRequestPasswordReset
{
    public function getHeading(): string
    {
        return 'Forgot your password?';
    }

    public function getSubheading(): ?string
    {
        return 'Enter your email and we will send you a reset link.';
    }
}