<?php

namespace App\Filament\Pages\Auth;

use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Schemas\Schema;

class Login extends BaseLogin
{
    public function getHeading(): string
    {
        return 'Welcome Back';
    }

    public function getSubheading(): ?string
    {
        return 'Sign in to manage your dashboard.';
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getEmailFormComponent()
                    ->label('Email address')
                    ->placeholder('you@example.com'),

                $this->getPasswordFormComponent()
                    ->label('Password')
                    ->placeholder('Enter your password'),

                $this->getRememberFormComponent(),
            ]);
    }
}