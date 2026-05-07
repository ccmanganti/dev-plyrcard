<?php

namespace App\Filament\Pages\Auth\PasswordReset;

use Filament\Auth\Pages\PasswordReset\ResetPassword as BaseResetPassword;

class ResetPassword extends BaseResetPassword
{
    public function getHeading(): string
    {
        return 'Create a new password';
    }

    public function getSubheading(): ?string
    {
        return 'Choose a strong password for your account.';
    }
}