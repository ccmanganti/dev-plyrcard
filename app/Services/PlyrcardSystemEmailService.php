<?php

namespace App\Services;

use App\Models\User;
use App\Models\Website;
use App\Support\PlyrcardMailSender;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PlyrcardSystemEmailService
{
    public function __construct(
        protected GoHighLevelService $ghl,
    ) {}

    public function sendRegistrationVerification(User $user, string $verificationUrl): array
    {
        $recipient = $this->recipientFor($user);

        if (! $recipient) {
            return ['success' => false, 'error' => 'The player does not have a valid email address.'];
        }

        $user->loadMissing('activeWebsite');

        $html = view('emails.plyrcard-registration-verification', [
            'user' => $user,
            'verificationUrl' => $verificationUrl,
        ])->render();

        return $this->sendHtml(
            user: $user,
            recipient: $recipient,
            subject: 'Confirm your PLYRCARD account',
            html: $html,
            purpose: 'registration_verification',
        );
    }

    public function sendPlayerActivity(
        User $player,
        Website $website,
        string $activityType,
        string $platform = 'website',
        ?string $viewerEmail = null,
    ): array {
        $recipient = $this->recipientFor($player);

        if (! $recipient) {
            return ['success' => false, 'error' => 'The player does not have a valid email address.'];
        }

        $subject = $activityType === 'profile_view'
            ? 'Someone viewed your PLYRCARD'
            : 'Someone clicked your ' . ucfirst($platform) . ' link';

        $html = view('emails.plyrcard-player-activity', [
            'player' => $player,
            'website' => $website,
            'activityType' => $activityType,
            'platform' => $platform,
            'viewerEmail' => $viewerEmail,
        ])->render();

        return $this->sendHtml(
            user: $player,
            recipient: $recipient,
            subject: $subject,
            html: $html,
            purpose: $activityType === 'profile_view' ? 'profile_view_notification' : 'social_click_notification',
        );
    }

    public function sendTest(User $user): array
    {
        $recipient = $this->recipientFor($user);

        if (! $recipient) {
            return ['success' => false, 'error' => 'The player does not have a valid email address.'];
        }

        $html = '<div style="font-family:Arial,sans-serif;background:#0C0E11;color:#F2F0ED;padding:32px">'
            . '<h2 style="margin:0 0 12px">PLYRCARD email test</h2>'
            . '<p style="margin:0;color:#aab0b7">If you received this, the PLYRCARD GHL system-email delivery path is working.</p>'
            . '</div>';

        return $this->sendHtml(
            user: $user,
            recipient: $recipient,
            subject: 'PLYRCARD email test',
            html: $html,
            purpose: 'system_email_test',
        );
    }

    protected function sendHtml(User $user, string $recipient, string $subject, string $html, string $purpose): array
    {
        $locationId = filled($user->ghl_location_id)
            ? trim((string) $user->ghl_location_id)
            : trim((string) config('ghl.location_id'));

        $tokenOverride = filled($user->ghl_api_key)
            ? trim((string) $user->ghl_api_key)
            : null;

        $token = $this->ghl->tokenForLocation($locationId ?: null, $tokenOverride);

        if ($locationId === '' || ! $token) {
            $result = ['success' => false, 'error' => 'Missing GHL location or access token.'];
            $this->logFailure($user, $recipient, $subject, $purpose, $result);
            return $result;
        }

        $contactId = trim((string) ($user->ghl_contact_id ?? ''));

        if ($contactId === '') {
            try {
                $contactId = (string) ($this->ghl->upsertContact([
                    'firstName' => $user->first_name,
                    'lastName' => $user->last_name,
                    'name' => trim((string) $user->first_name . ' ' . (string) $user->last_name),
                    'email' => $recipient,
                    'phone' => $user->phone,
                    'tags' => ['plyrcard-system-email'],
                ], $locationId, $tokenOverride) ?? '');
            } catch (\Throwable $exception) {
                $result = ['success' => false, 'error' => 'Could not create or resolve the GHL contact: ' . $exception->getMessage()];
                $this->logFailure($user, $recipient, $subject, $purpose, $result);
                return $result;
            }

            if ($contactId !== '') {
                $user->forceFill([
                    'ghl_contact_id' => $contactId,
                    'ghl_location_id' => $locationId,
                ])->saveQuietly();
            }
        }

        if ($contactId === '') {
            $result = ['success' => false, 'error' => 'GHL contact could not be resolved for the recipient.'];
            $this->logFailure($user, $recipient, $subject, $purpose, $result);
            return $result;
        }

        $fromEmail = PlyrcardMailSender::email();
        $fromName = 'PLYRCARD Support';
        $plainText = trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
        $baseUrl = rtrim((string) config('ghl.base_url', 'https://services.leadconnectorhq.com'), '/');

        $official = [
            'type' => 'Email',
            'contactId' => $contactId,
            'subject' => $subject,
            'html' => $html,
            'message' => $plainText,
            'emailTo' => $recipient,
            'emailFrom' => $fromEmail,
            'fromName' => $fromName,
            'status' => 'delivered',
        ];

        $legacy = [
            'locationId' => $locationId,
            'contactId' => $contactId,
            'subject' => $subject,
            'html' => $html,
            'body' => $html,
            'message' => $html,
            'text' => $plainText,
            'emailTo' => $recipient,
            'fromEmail' => $fromEmail,
            'emailFrom' => $fromEmail,
            'fromName' => $fromName,
            'senderName' => $fromName,
            'type' => 'Email',
        ];

        $versions = array_values(array_unique(array_filter([
            'v3',
            trim((string) config('ghl.conversations_send_version')),
            '2021-04-15',
            '2023-02-21',
        ])));

        $endpoints = array_values(array_unique(array_filter([
            config('ghl.conversations_send_endpoint'),
            '/conversations/messages',
        ])));

        $lastStatus = null;
        $lastError = null;
        $lastData = null;

        foreach ($versions as $version) {
            foreach ($endpoints as $endpoint) {
                foreach ([$official, $legacy] as $payload) {
                    try {
                        $response = Http::withHeaders(['Version' => $version])
                            ->connectTimeout((int) config('ghl.connect_timeout', 5))
                            ->timeout((int) config('ghl.timeout', 20))
                            ->withToken($token)
                            ->acceptJson()
                            ->asJson()
                            ->post($baseUrl . $endpoint, $payload);
                    } catch (\Throwable $exception) {
                        $lastError = $exception->getMessage();
                        continue;
                    }

                    $data = $response->json();
                    $data = is_array($data) ? $data : ['raw' => $response->body()];

                    if ($response->successful()) {
                        Log::info('PLYRCARD system email sent through GHL.', [
                            'purpose' => $purpose,
                            'user_id' => $user->getKey(),
                            'contact_id' => $contactId,
                            'recipient' => $recipient,
                            'from_email' => $fromEmail,
                            'subject' => $subject,
                            'endpoint' => $endpoint,
                            'version' => $version,
                            'message_id' => $data['messageId'] ?? $data['id'] ?? data_get($data, 'message.id'),
                        ]);

                        return [
                            'success' => true,
                            'contact_id' => $contactId,
                            'status' => $response->status(),
                            'message_id' => $data['messageId'] ?? $data['id'] ?? data_get($data, 'message.id'),
                            'raw' => $data,
                        ];
                    }

                    $lastStatus = $response->status();
                    $lastData = $data;
                    $lastError = $data['message'] ?? $data['error'] ?? $data['msg'] ?? $response->body();
                }
            }
        }

        $result = [
            'success' => false,
            'status' => $lastStatus,
            'error' => is_string($lastError) && trim($lastError) !== ''
                ? Str::limit(strip_tags($lastError), 300)
                : 'GHL did not accept the email send request.',
            'raw' => $lastData,
        ];

        $this->logFailure($user, $recipient, $subject, $purpose, $result);

        return $result;
    }

    protected function recipientFor(User $user): ?string
    {
        foreach ([$user->email, $user->personal_email] as $candidate) {
            $candidate = strtolower(trim((string) $candidate));
            if ($candidate !== '' && filter_var($candidate, FILTER_VALIDATE_EMAIL)) {
                return $candidate;
            }
        }

        return null;
    }

    protected function logFailure(User $user, string $recipient, string $subject, string $purpose, array $result): void
    {
        Log::error('PLYRCARD system email send failed.', [
            'purpose' => $purpose,
            'user_id' => $user->getKey(),
            'ghl_contact_id' => $user->ghl_contact_id,
            'ghl_location_id' => $user->ghl_location_id ?: config('ghl.location_id'),
            'recipient' => $recipient,
            'from_email' => PlyrcardMailSender::email(),
            'subject' => $subject,
            'status' => $result['status'] ?? null,
            'error' => $result['error'] ?? null,
            'raw' => $result['raw'] ?? null,
        ]);
    }
}
