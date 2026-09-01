<?php

namespace App\Filament\Resources\SupportTickets\Pages;

use App\Filament\Resources\SupportTickets\SupportTicketResource;
use App\Models\SystemSetting;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;

class SupportTicketSettings extends Page
{
    protected static string $resource = SupportTicketResource::class;
    protected string $view = 'filament.resources.support-tickets.pages.settings';
    protected static ?string $title = 'System Settings';

    public string $adminAlertEmails = '';

    public function mount(): void
    {
        abort_unless(SupportTicketResource::canAccess(), 403);
        $emails = SystemSetting::value('admin_alert_emails', (array) config('plyrcard-support.admin_emails', []));
        $this->adminAlertEmails = implode("\n", is_array($emails) ? $emails : []);
    }

    public function save(): void
    {
        abort_unless(SupportTicketResource::canAccess(), 403);

        $emails = collect(preg_split('/[\s,;]+/', trim($this->adminAlertEmails)) ?: [])
            ->map(fn ($email) => strtolower(trim((string) $email)))
            ->filter()->unique()->values();

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
            ->body('New registration, upgrade, support ticket, follow-up, and downgrade alerts will use this global list.')
            ->success()->send();
    }
}