<?php

namespace App\Services;

use App\Models\AdminSupportMessage;
use App\Models\BillingInformation;
use App\Models\User;
use App\Models\Website;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;

class AdminSupportMessagingService
{
    public function __construct(
        protected AdminSupportEmailService $emailService,
    ) {
    }

    public function adminRoles(): array
    {
        $roles = array_values(array_filter(array_map(
            static fn ($role): string => trim((string) $role),
            (array) config('plyrcard-admin-support.admin_roles', []),
        )));

        return $roles !== []
            ? $roles
            : ['Superadmin', 'superadmin', 'Super Admin', 'Administrator', 'Admin'];
    }

    public function isAdmin(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if (method_exists($user, 'isSuperadminOrImpersonating')) {
            try {
                if ($user->isSuperadminOrImpersonating()) {
                    return true;
                }
            } catch (\Throwable) {
                // Continue with role aliases below.
            }
        }

        if (! method_exists($user, 'hasRole')) {
            return false;
        }

        foreach ($this->adminRoles() as $role) {
            try {
                if ($user->hasRole($role)) {
                    return true;
                }
            } catch (\Throwable) {
                // Continue checking the remaining aliases.
            }
        }

        return false;
    }

    public function concerns(): array
    {
        return (array) config('plyrcard-admin-support.concerns', []);
    }

    public function variableDefinitions(): array
    {
        return (array) config('plyrcard-admin-support.variables', []);
    }

    public function template(string $concern): array
    {
        $concerns = $this->concerns();
        $template = $concerns[$concern] ?? $concerns['custom'] ?? [];

        return [
            'label' => (string) ($template['label'] ?? Str::headline($concern)),
            'hint' => (string) ($template['hint'] ?? ''),
            'subject' => (string) ($template['subject'] ?? 'A note from PLYRCARD'),
            'message' => (string) ($template['message'] ?? ''),
        ];
    }

    /**
     * Proactive Admin Support email is PERSONAL EMAIL ONLY.
     * users.email is never used as a fallback by this feature.
     */
    public function personalEmailFor(User $user): ?string
    {
        $email = strtolower(trim(str_replace(["\r", "\n"], '', (string) ($user->personal_email ?? ''))));
        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }

    public function variablesFor(User $target, User $admin): array
    {
        try {
            $target->loadMissing(['roles', 'school', 'activeWebsite']);
        } catch (\Throwable) {
            // Optional relations differ across some historical installs.
        }

        $firstName = trim((string) ($target->first_name ?? ''));
        $lastName = trim((string) ($target->last_name ?? ''));
        $fullName = trim($firstName . ' ' . $lastName);
        $adminName = trim((string) (($admin->first_name ?? '') . ' ' . ($admin->last_name ?? '')));

        if ($firstName === '') {
            $firstName = $fullName !== '' ? Str::before($fullName, ' ') : 'there';
        }
        if ($fullName === '') {
            $fullName = $firstName;
        }
        if ($adminName === '') {
            $adminName = trim((string) ($admin->name ?? '')) ?: 'PLYRCARD Support';
        }

        $profileCompletion = 0;
        try {
            $profileCompletion = (int) app(ProfileCompletionService::class)->calculate($target);
        } catch (\Throwable $exception) {
            Log::debug('Admin support messenger could not calculate profile completion.', [
                'user_id' => $target->getKey(),
                'error' => $exception->getMessage(),
            ]);
        }

        $billing = $this->latestBilling($target);
        $website = $this->websiteFor($target);
        $websiteLink = $this->websiteUrl($website);
        $personalEmail = $this->personalEmailFor($target) ?? '';

        return [
            'first_name' => $firstName,
            'full_name' => $fullName,
            'personal_email' => $personalEmail,
            // Backwards-compatible alias; still resolves to personal_email only.
            'email' => $personalEmail,
            'phone' => trim((string) ($target->phone ?? '')),
            'sport' => trim((string) ($target->sport ?? '')) ?: 'your sport',
            'school' => trim((string) (data_get($target, 'school.name') ?: ($target->school_name ?? ''))) ?: 'your school',
            'plan' => $this->planLabel($target),
            'profile_completion' => (string) max(0, min(100, $profileCompletion)),
            'payment_status' => $this->paymentStatus($billing),
            'renewal_date' => $this->renewalDate($billing),
            'login_link' => $this->appUrl('/admin/login'),
            'admin_link' => $this->appUrl('/admin'),
            'profile_link' => $this->appUrl('/admin/my-profile'),
            'photos_link' => $this->appUrl('/admin/coach-database/photos'),
            'outreach_link' => $this->appUrl('/admin/coach-database'),
            'billing_link' => $this->appUrl('/admin/billing'),
            'locker_room_link' => $websiteLink !== '' ? $websiteLink : $this->appUrl('/'),
            'website_link' => $websiteLink,
            'support_link' => $this->appUrl('/support/tickets'),
            'admin_name' => $adminName,
        ];
    }

    public function renderTemplate(string $template, array $variables): string
    {
        $replace = [];
        foreach ($variables as $key => $value) {
            $replace['{{' . $key . '}}'] = (string) $value;
        }

        return trim(strtr($template, $replace));
    }

    public function send(
        User $admin,
        User $target,
        string $concern,
        string $subjectTemplate,
        string $messageTemplate,
        string $audienceMode = 'individual',
        ?string $batchId = null,
    ): array {
        if (! $this->isAdmin($admin)) {
            throw new RuntimeException('You are not authorized to send admin support emails.');
        }

        if ($this->isAdmin($target)) {
            throw new RuntimeException('Admin accounts are excluded from proactive user support sends.');
        }

        if ((int) $target->getKey() === (int) $admin->getKey()) {
            throw new RuntimeException('You cannot send an Admin Support email to your own admin account.');
        }

        if (! in_array($audienceMode, ['all', 'custom', 'individual'], true)) {
            throw new RuntimeException('Choose All Users, Custom List, or Individual User.');
        }

        if (! Schema::hasTable('admin_support_messages')) {
            throw new RuntimeException('Admin Support is not installed yet. Run php artisan migrate first.');
        }

        $variables = $this->variablesFor($target, $admin);
        $subject = $this->renderTemplate($subjectTemplate, $variables);
        $body = $this->renderTemplate($messageTemplate, $variables);

        if ($body === '') {
            throw new RuntimeException('The message cannot be empty.');
        }

        $recipientEmail = $this->personalEmailFor($target);
        $record = $this->startLogRecord(
            admin: $admin,
            target: $target,
            concern: $concern,
            audienceMode: $audienceMode,
            batchId: $batchId,
            subject: $subject,
            subjectTemplate: $subjectTemplate,
            messageTemplate: $messageTemplate,
            body: $body,
            variables: $variables,
            email: $recipientEmail ?? '',
        );

        if (! $recipientEmail) {
            $emailResult = [
                'requested' => true,
                'success' => false,
                'status' => 'skipped_no_personal_email',
                'error' => 'No valid personal_email is on file.',
            ];
        } else {
            $emailResult = $this->emailService->send(
                user: $target,
                recipient: $recipientEmail,
                subject: $subject,
                body: $body,
                variables: $variables,
                concern: $concern,
            );
        }

        if ($record) {
            $record->forceFill([
                'email_status' => (string) ($emailResult['status'] ?? 'failed'),
                'sms_status' => 'not_requested',
                'provider_contact_id' => null,
                'provider_message_ids' => null,
                'error_message' => ($emailResult['success'] ?? false) ? null : ($emailResult['error'] ?? 'Email delivery failed.'),
                'sent_at' => ($emailResult['success'] ?? false) ? now() : null,
            ])->save();
        }

        return [
            'success' => (bool) ($emailResult['success'] ?? false),
            'email' => $emailResult,
            'message' => ($emailResult['success'] ?? false)
                ? 'Email sent successfully.'
                : ($emailResult['error'] ?? 'Email could not be sent.'),
        ];
    }

    protected function startLogRecord(
        User $admin,
        User $target,
        string $concern,
        string $audienceMode,
        ?string $batchId,
        string $subject,
        string $subjectTemplate,
        string $messageTemplate,
        string $body,
        array $variables,
        string $email,
    ): ?AdminSupportMessage {
        if (! Schema::hasTable('admin_support_messages')) {
            return null;
        }

        try {
            $attributes = [
                'admin_user_id' => $admin->getKey(),
                'user_id' => $target->getKey(),
                'concern' => $concern,
                'channel' => 'email',
                'subject' => $subject,
                'template_subject' => $subjectTemplate,
                'template_message' => $messageTemplate,
                'rendered_message' => $body,
                'variables' => $variables,
                'recipient_email' => $email !== '' ? $email : null,
                'recipient_phone' => null,
                'email_status' => 'pending',
                'sms_status' => 'not_requested',
            ];

            if (Schema::hasColumn('admin_support_messages', 'audience_mode')) {
                $attributes['audience_mode'] = $audienceMode;
            }
            if (Schema::hasColumn('admin_support_messages', 'batch_id')) {
                $attributes['batch_id'] = $batchId;
            }

            return AdminSupportMessage::query()->create($attributes);
        } catch (\Throwable $exception) {
            Log::warning('Admin support email audit log could not be created.', [
                'admin_user_id' => $admin->getKey(),
                'user_id' => $target->getKey(),
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    protected function latestBilling(User $target): ?BillingInformation
    {
        try {
            if (! Schema::hasTable('billing_information')) {
                return null;
            }

            return BillingInformation::query()
                ->where('user_id', $target->getKey())
                ->latest('updated_at')
                ->first();
        } catch (\Throwable) {
            return null;
        }
    }

    protected function paymentStatus(?BillingInformation $billing): string
    {
        if (! $billing) {
            return 'not available';
        }

        $status = trim((string) ($billing->payment_status ?: $billing->subscription_status ?: ''));
        return $status !== ''
            ? Str::of($status)->replace(['_', '-'], ' ')->lower()->headline()->toString()
            : 'not available';
    }

    protected function renewalDate(?BillingInformation $billing): string
    {
        if (! $billing) {
            return 'soon';
        }

        foreach ([
            'subscription_current_period_end',
            'current_period_end',
            'next_billing_at',
            'next_payment_at',
            'renewal_at',
            'subscription_renews_at',
            'subscription_end_at',
        ] as $field) {
            $value = data_get($billing, $field);
            if (! filled($value)) {
                continue;
            }

            try {
                return 'on ' . Carbon::parse($value)->format('M j, Y');
            } catch (\Throwable) {
                return 'around ' . trim((string) $value);
            }
        }

        return 'soon';
    }

    protected function planLabel(User $target): string
    {
        $roles = collect();
        try {
            $roles = method_exists($target, 'getRoleNames')
                ? $target->getRoleNames()
                : collect($target->roles ?? [])->pluck('name');
        } catch (\Throwable) {
            $roles = collect();
        }

        $has = static fn (string $name): bool => $roles->contains(
            fn ($role): bool => strcasecmp(trim((string) $role), $name) === 0
        );

        if ($has('Amplify')) {
            return $has('My Journey') ? 'My Journey + Amplify' : 'Amplify';
        }
        if ($has('Jumpstart')) {
            return $has('My Journey') ? 'My Journey + Jumpstart' : 'Jumpstart';
        }
        if ($has('My Journey')) {
            return 'My Journey';
        }
        if ($has('Free')) {
            return 'Free';
        }

        return trim((string) ($roles->first() ?? 'Free')) ?: 'Free';
    }

    protected function websiteFor(User $target): ?Website
    {
        try {
            if ($target->relationLoaded('activeWebsite') && $target->activeWebsite) {
                return $target->activeWebsite;
            }

            $active = $target->activeWebsite()->first();
            if ($active) {
                return $active;
            }

            return $target->websites()
                ->orderByDesc('is_published')
                ->orderByDesc('id')
                ->first();
        } catch (\Throwable) {
            return null;
        }
    }

    protected function websiteUrl(?Website $website): string
    {
        if (! $website) {
            return '';
        }

        if (filled($website->domain)) {
            $domain = preg_replace('#^https?://#i', '', trim((string) $website->domain));
            return $domain ? 'https://' . trim((string) $domain, '/') : '';
        }

        if (filled($website->slug)) {
            return $this->appUrl('/' . ltrim((string) $website->slug, '/'));
        }

        return '';
    }

    protected function appUrl(string $path): string
    {
        $base = rtrim((string) config('app.url', 'https://plyrcard.com'), '/');
        if ($base === '') {
            $base = 'https://plyrcard.com';
        }

        return $base . '/' . ltrim($path, '/');
    }
}