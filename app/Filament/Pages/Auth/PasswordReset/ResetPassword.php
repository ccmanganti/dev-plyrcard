<?php

namespace App\Filament\Pages\Auth\PasswordReset;

if (class_exists(\Filament\Auth\Pages\PasswordReset\ResetPassword::class)) {
    class_alias(
        \Filament\Auth\Pages\PasswordReset\ResetPassword::class,
        __NAMESPACE__ . '\\PlyrcardFilamentResetPasswordBase',
    );
} elseif (class_exists(\Filament\Pages\Auth\PasswordReset\ResetPassword::class)) {
    class_alias(
        \Filament\Pages\Auth\PasswordReset\ResetPassword::class,
        __NAMESPACE__ . '\\PlyrcardFilamentResetPasswordBase',
    );
} else {
    throw new \RuntimeException('Unable to locate the Filament reset password page class.');
}

/**
 * Keep PLYRCARD's configured class name while delegating token validation,
 * password rules, password update, and post-reset behavior to Filament.
 */
class ResetPassword extends PlyrcardFilamentResetPasswordBase
{
}