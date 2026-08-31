<?php

namespace App\Filament\Pages;

use App\Models\SystemSetting;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use UnitEnum;

class SystemSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static ?string $navigationLabel = 'System Settings';
    protected static string|UnitEnum|null $navigationGroup = 'Administration';
    protected static ?string $title = 'System Settings';
    protected static ?string $slug = 'system-settings';
    protected static ?int $navigationSort = 99;
    protected string $view = 'filament.pages.system-settings';

    public string $adminAlertEmails = '';

    public function mount(): void
    {
        abort_unless(static::isAdminUser(), 403);

        $emails = SystemSetting::value(
            'admin_alert_emails',
            (array) config('plyrcard-support.admin_emails', []),
        );

        $this->adminAlertEmails = implode("\n", $emails);
    }

    public function save(): void
    {
        abort_unless(static::isAdminUser(), 403);

        $emails = collect(preg_split('/[\s,;]+/', trim($this->adminAlertEmails)) ?: [])
            ->map(fn ($email) => strtolower(trim((string) $email)))
            ->filter()
            ->unique()
            ->values();

        $invalid = $emails->filter(fn ($email) => ! filter_var($email, FILTER_VALIDATE_EMAIL));

        if ($emails->isEmpty()) {
            $this->addError('adminAlertEmails', 'Add at least one admin alert email address.');
            return;
        }

        if ($invalid->isNotEmpty()) {
            $this->addError('adminAlertEmails', 'These email addresses are invalid: ' . $invalid->implode(', '));
            return;
        }

        SystemSetting::putValue('admin_alert_emails', $emails->all());
        $this->adminAlertEmails = $emails->implode("\n");

        Notification::make()
            ->title('Admin alert recipients updated')
            ->body('Support ticket and downgrade alerts will use this global list.')
            ->success()
            ->send();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::isAdminUser();
    }

    public static function canAccess(): bool
    {
        return static::isAdminUser();
    }

    protected static function isAdminUser(): bool
    {
        $user = auth()->user();

        if (! $user || ! method_exists($user, 'hasRole')) {
            return false;
        }

        foreach (['Superadmin', 'superadmin', 'Super Admin', 'Administrator', 'Admin'] as $role) {
            if ($user->hasRole($role)) {
                return true;
            }
        }

        return false;
    }
}
