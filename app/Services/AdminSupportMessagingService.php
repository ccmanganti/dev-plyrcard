<?php

namespace App\Services;

use App\Models\AdminSupportMessage;
use App\Models\BillingInformation;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;

class AdminSupportMessagingService
{
    public function __construct(
        protected GoHighLevelService $ghl,
    ) {
    }

    public function isAdmin(?User $user): bool
    {
        if (! $user || ! method_exists($user, 'hasRole')) {
            return false;
        }

        foreach ((array) config('plyrcard-admin-support.admin_roles', []) as $role) {
            if ($role !== '' && $user->hasRole($role)) {
                return true;
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

    public function variablesFor(User $target, User $admin): array
    {
        try {
            $target->loadMissing(['roles', 'school']);
        } catch (\Throwable) {
            // Keep the messenger usable even if an optional relation differs by install.
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
        $paymentStatus = $this->paymentStatus($billing);
        $renewalDate = $this->renewalDate($billing);

        return [
            'first_name' => $firstName,
            'full_name' => $fullName,
            'email' => trim((string) ($target->email ?: $target->personal_email ?? '')),
            'phone' => trim((string) ($target->phone ?? '')),
            'sport' => trim((string) ($target->sport ?? '')) ?: 'your sport',
            'school' => trim((string) (data_get($target, 'school.name') ?: $target->school_name ?? '')) ?: 'your school',
            'plan' => $this->planLabel($target),
            'profile_completion' => (string) max(0, min(100, $profileCompletion)),
            'payment_status' => $paymentStatus,
            'renewal_date' => $renewalDate,
            'login_link' => url('/admin/login'),
            'profile_link' => $this->profileUrl(),
            'photos_link' => url('/admin/coach-database/photos'),
            'outreach_link' => url('/admin/coach-database'),
            'billing_link' => url('/admin/billing'),
            'locker_room_link' => url('/locker-room'),
            'support_link' => url('/admin/coach-database/support'),
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
        string $channel,
        string $subjectTemplate,
        string $messageTemplate,
    ): array {
        if (! $this->isAdmin($admin)) {
            throw new RuntimeException('You are not authorized to send admin support messages.');
        }

        if (! in_array($channel, ['email', 'sms', 'email_sms'], true)) {
            throw new RuntimeException('Choose Email, SMS, or Email + SMS.');
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

        $recipientEmail = trim((string) ($target->email ?: $target->personal_email ?? ''));
        $recipientPhone = trim((string) ($target->phone ?? ''));
        $sendEmail = in_array($channel, ['email', 'email_sms'], true);
        $sendSms = in_array($channel, ['sms', 'email_sms'], true);

        if ($sendEmail && $recipientEmail === '') {
            throw new RuntimeException('This user does not have an email address on file.');
        }

        if ($sendSms && $recipientPhone === '' && ! $sendEmail) {
            throw new RuntimeException('This user does not have a phone number on file.');
        }

        if ($sendSms && mb_strlen($body) > 1600) {
            throw new RuntimeException('The SMS message is too long. Keep it under 1,600 characters.');
        }

        $record = $this->startLogRecord(
            admin: $admin,
            target: $target,
            concern: $concern,
            channel: $channel,
            subject: $subject,
            subjectTemplate: $subjectTemplate,
            messageTemplate: $messageTemplate,
            body: $body,
            variables: $variables,
            email: $recipientEmail,
            phone: $recipientPhone,
        );

        $emailResult = ['requested' => $sendEmail, 'success' => false, 'status' => $sendEmail ? 'pending' : 'not_requested'];
        $smsResult = ['requested' => $sendSms, 'success' => false, 'status' => $sendSms ? 'pending' : 'not_requested'];
        $errors = [];
        $providerContactId = null;
        $providerIds = [];

        if ($sendEmail) {
            $emailResult = $this->sendEmail($target, $recipientEmail, $subject, $body);
            if (! ($emailResult['success'] ?? false)) {
                $errors[] = 'Email: ' . ($emailResult['error'] ?? 'delivery failed');
            }
        }

        if ($sendSms) {
            if ($recipientPhone === '') {
                $smsResult = [
                    'requested' => true,
                    'success' => false,
                    'status' => 'skipped_no_phone',
                    'error' => 'No phone number on file.',
                ];
                $errors[] = 'SMS: no phone number on file';
            } else {
                $smsResult = $this->sendSms($target, $body);
                $providerContactId = $smsResult['contact_id'] ?? null;
                if (filled($smsResult['message_id'] ?? null)) {
                    $providerIds['sms'] = $smsResult['message_id'];
                }
                if (! ($smsResult['success'] ?? false)) {
                    $errors[] = 'SMS: ' . ($smsResult['error'] ?? 'delivery failed');
                }
            }
        }

        $anySuccess = (bool) ($emailResult['success'] ?? false) || (bool) ($smsResult['success'] ?? false);
        $allRequestedSucceeded = (! $sendEmail || ($emailResult['success'] ?? false))
            && (! $sendSms || ($smsResult['success'] ?? false));

        if ($record) {
            $record->forceFill([
                'email_status' => (string) ($emailResult['status'] ?? ($sendEmail ? 'failed' : 'not_requested')),
                'sms_status' => (string) ($smsResult['status'] ?? ($sendSms ? 'failed' : 'not_requested')),
                'provider_contact_id' => $providerContactId,
                'provider_message_ids' => $providerIds ?: null,
                'error_message' => $errors ? implode("\n", $errors) : null,
                'sent_at' => $anySuccess ? now() : null,
            ])->save();
        }

        return [
            'success' => $allRequestedSucceeded,
            'partial' => $anySuccess && ! $allRequestedSucceeded,
            'email' => $emailResult,
            'sms' => $smsResult,
            'errors' => $errors,
            'message' => $allRequestedSucceeded
                ? 'Message sent successfully.'
                : ($anySuccess ? 'Message partially sent.' : 'Message could not be sent.'),
        ];
    }

    protected function sendEmail(User $target, string $recipient, string $subject, string $body): array
    {
        try {
            $html = $this->emailHtml($target, $body);
            Mail::html($html, function ($message) use ($recipient, $subject): void {
                $message->to($recipient)
                    ->subject($subject !== '' ? $subject : 'A note from PLYRCARD');

                $fromAddress = trim((string) config('mail.from.address'));
                $fromName = trim((string) config('mail.from.name', 'PLYRCARD'));
                if ($fromAddress !== '') {
                    $message->from($fromAddress, $fromName !== '' ? $fromName : 'PLYRCARD');
                }
            });

            return ['requested' => true, 'success' => true, 'status' => 'sent'];
        } catch (\Throwable $exception) {
            Log::warning('Admin support email failed.', [
                'user_id' => $target->getKey(),
                'recipient' => $recipient,
                'error' => $exception->getMessage(),
            ]);

            return [
                'requested' => true,
                'success' => false,
                'status' => 'failed',
                'error' => $exception->getMessage(),
            ];
        }
    }

    protected function sendSms(User $target, string $body): array
    {
        $locationId = trim((string) config('ghl.location_id'));
        $token = trim((string) config('ghl.token'));

        if ($locationId === '' || $token === '') {
            return [
                'requested' => true,
                'success' => false,
                'status' => 'failed',
                'error' => 'PLYRCARD messaging credentials are not configured.',
            ];
        }

        try {
            // Always resolve the athlete/user contact from their own contact details.
            // Do not use ghl_subscriber_contact_id here; that can represent a parent/payer.
            $contactId = $this->ghl->upsertContact(array_filter([
                'firstName' => trim((string) ($target->first_name ?? '')),
                'lastName' => trim((string) ($target->last_name ?? '')),
                'name' => trim((string) (($target->first_name ?? '') . ' ' . ($target->last_name ?? ''))),
                'email' => $target->email ?: $target->personal_email ?? null,
                'phone' => $target->phone ?? null,
                'source' => 'PLYRCARD Admin Support',
                'tags' => ['plyrcard-user', 'admin-support'],
            ], static fn ($value): bool => $value !== null && $value !== ''), $locationId, $token);

            if (! $contactId) {
                return [
                    'requested' => true,
                    'success' => false,
                    'status' => 'failed',
                    'error' => 'Unable to resolve the user in PLYRCARD messaging.',
                ];
            }

            $payloads = [
                [
                    'type' => 'SMS',
                    'messageType' => 'TYPE_SMS',
                    'locationId' => $locationId,
                    'contactId' => $contactId,
                    'message' => $body,
                ],
                [
                    'type' => 'TYPE_SMS',
                    'locationId' => $locationId,
                    'contactId' => $contactId,
                    'message' => $body,
                ],
            ];

            $lastError = 'SMS delivery failed.';
            foreach ($payloads as $payload) {
                $response = Http::withHeaders([
                        'Version' => config('ghl.conversations_send_version', '2021-04-15'),
                    ])
                    ->withToken($token)
                    ->acceptJson()
                    ->asJson()
                    ->timeout((int) config('ghl.timeout', 20))
                    ->post('https://services.leadconnectorhq.com/conversations/messages', $payload);

                $data = $response->json() ?? [];
                if ($response->successful()) {
                    $messageId = data_get($data, 'messageId')
                        ?: data_get($data, 'message.id')
                        ?: data_get($data, 'id');

                    try {
                        $this->ghl->addContactNote(
                            (string) $contactId,
                            'Admin support SMS sent to PLYRCARD user #' . $target->getKey() . ".\n\n" . $body,
                            $locationId,
                            $token,
                        );
                    } catch (\Throwable) {
                        // The outbound message is authoritative; a note is best-effort only.
                    }

                    return [
                        'requested' => true,
                        'success' => true,
                        'status' => 'sent',
                        'contact_id' => (string) $contactId,
                        'message_id' => $messageId ? (string) $messageId : null,
                    ];
                }

                $lastError = trim((string) (data_get($data, 'message') ?: data_get($data, 'error') ?: $response->body()))
                    ?: 'SMS delivery failed.';
            }

            return [
                'requested' => true,
                'success' => false,
                'status' => 'failed',
                'contact_id' => (string) $contactId,
                'error' => Str::limit($lastError, 700),
            ];
        } catch (\Throwable $exception) {
            Log::warning('Admin support SMS failed.', [
                'user_id' => $target->getKey(),
                'error' => $exception->getMessage(),
            ]);

            return [
                'requested' => true,
                'success' => false,
                'status' => 'failed',
                'error' => $exception->getMessage(),
            ];
        }
    }

    protected function startLogRecord(
        User $admin,
        User $target,
        string $concern,
        string $channel,
        string $subject,
        string $subjectTemplate,
        string $messageTemplate,
        string $body,
        array $variables,
        string $email,
        string $phone,
    ): ?AdminSupportMessage {
        if (! Schema::hasTable('admin_support_messages')) {
            return null;
        }

        try {
            return AdminSupportMessage::query()->create([
                'admin_user_id' => $admin->getKey(),
                'user_id' => $target->getKey(),
                'concern' => $concern,
                'channel' => $channel,
                'subject' => $subject,
                'template_subject' => $subjectTemplate,
                'template_message' => $messageTemplate,
                'rendered_message' => $body,
                'variables' => $variables,
                'recipient_email' => $email !== '' ? $email : null,
                'recipient_phone' => $phone !== '' ? $phone : null,
                'email_status' => in_array($channel, ['email', 'email_sms'], true) ? 'pending' : 'not_requested',
                'sms_status' => in_array($channel, ['sms', 'email_sms'], true) ? 'pending' : 'not_requested',
            ]);
        } catch (\Throwable $exception) {
            Log::warning('Admin support message audit log could not be created.', [
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
        if ($status === '') {
            return 'not available';
        }

        return Str::of($status)->replace(['_', '-'], ' ')->lower()->headline()->toString();
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

    protected function profileUrl(): string
    {
        try {
            if (class_exists(\App\Filament\Resources\Profiles\ProfileResource::class)) {
                return \App\Filament\Resources\Profiles\ProfileResource::getUrl('index');
            }
        } catch (\Throwable) {
            // Fall through to the stable route fallback.
        }

        return url('/admin/my-profile');
    }

    protected function emailHtml(User $target, string $body): string
    {
        $escaped = e($body);
        $escaped = preg_replace_callback(
            '~https?://[^\s<]+~i',
            static function (array $match): string {
                $url = $match[0];
                return '<a href="' . e($url) . '" style="color:#ff6338;text-decoration:none;font-weight:600">' . e($url) . '</a>';
            },
            $escaped,
        ) ?: $escaped;

        $content = nl2br($escaped);
        $name = e(trim((string) (($target->first_name ?? '') . ' ' . ($target->last_name ?? ''))) ?: 'PLYRCARD athlete');

        return <<<HTML
<!doctype html>
<html>
<body style="margin:0;padding:0;background:#0d1117;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;color:#f8fafc">
    <div style="max-width:640px;margin:0 auto;padding:34px 20px">
        <div style="border:1px solid #252b36;border-radius:20px;background:#12171e;overflow:hidden">
            <div style="padding:20px 24px;border-bottom:1px solid #252b36">
                <div style="font-size:13px;letter-spacing:.13em;text-transform:uppercase;color:#ff6338;font-weight:800">PLYRCARD SUPPORT</div>
                <div style="margin-top:6px;font-size:13px;color:#8b95a7">Message for {$name}</div>
            </div>
            <div style="padding:26px 24px;font-size:16px;line-height:1.65;color:#e5e7eb">{$content}</div>
        </div>
        <div style="padding:18px 8px 0;text-align:center;font-size:12px;color:#64748b">PLYRCARD · Own Your Journey</div>
    </div>
</body>
</html>
HTML;
    }
}
