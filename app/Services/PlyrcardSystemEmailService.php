<?php

namespace App\Services;

use App\Models\User;
use App\Models\Website;
use App\Support\PlyrcardMailSender;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;

class PlyrcardSystemEmailService
{
    public function sendRegistrationVerification(User $user, string $verificationUrl): array
    {
        $recipient = $this->recipientFor($user);

        if (! $recipient) {
            return ['success' => false, 'error' => 'The player does not have a valid email address.'];
        }

        $user->loadMissing('activeWebsite');

        $html = $this->renderRegistrationEmail($user, $verificationUrl);

        return $this->sendHtml(
            user: $user,
            recipient: $recipient,
            subject: 'Welcome to PLYRCARD',
            html: $html,
            purpose: 'registration_welcome',
        );
    }

    public function sendPlayerActivity(
        User $player,
        Website $website,
        string $activityType,
        string $platform = 'website',
        ?string $viewerEmail = null,
        ?string $viewerName = null,
        ?string $viewerSchool = null,
    ): array {
        $recipient = $this->recipientFor($player);

        if (! $recipient) {
            return ['success' => false, 'error' => 'The player does not have a valid email address.'];
        }

        $subject = match ($activityType) {
            'profile_view' => 'A coach viewed your PLYRCARD',
            'highlight_click' => 'A coach clicked your highlight',
            default => 'A coach clicked your ' . ucfirst($platform) . ' link',
        };

        $html = $this->renderActivityEmail(
            player: $player,
            website: $website,
            activityType: $activityType,
            platform: $platform,
            viewerEmail: $viewerEmail,
            viewerName: $viewerName,
            viewerSchool: $viewerSchool,
        );

        return $this->sendHtml(
            user: $player,
            recipient: $recipient,
            subject: $subject,
            html: $html,
            purpose: match ($activityType) {
                'profile_view' => 'profile_view_notification',
                'highlight_click' => 'highlight_click_notification',
                default => 'social_click_notification',
            },
        );
    }

    public function sendTest(User $user): array
    {
        $recipient = $this->recipientFor($user);

        if (! $recipient) {
            return ['success' => false, 'error' => 'The player does not have a valid email address.'];
        }

        $html = '<!doctype html><html><body style="margin:0;background:#0C0E11;color:#F2F0ED;font-family:Arial,sans-serif">'
            . '<div style="max-width:600px;margin:0 auto;padding:36px">'
            . '<div style="font-size:20px;font-weight:800">PLYR<span style="color:#FF5A3C">CARD</span></div>'
            . '<h2 style="margin:28px 0 10px">Native mail test</h2>'
            . '<p style="margin:0;color:#AAB0B7;line-height:1.6">If you received this message, the hosting server accepted a PLYRCARD email through PHP mail().</p>'
            . '</div></body></html>';

        return $this->sendHtml(
            user: $user,
            recipient: $recipient,
            subject: 'PLYRCARD native mail test',
            html: $html,
            purpose: 'native_mail_test',
        );
    }

    protected function renderRegistrationEmail(User $user, string $verificationUrl): string
    {
        $viewName = 'emails.plyrcard-registration-verification';

        if (View::exists($viewName)) {
            return view($viewName, [
                'user' => $user,
                'verificationUrl' => $verificationUrl,
            ])->render();
        }

        Log::warning('PLYRCARD registration email Blade view is missing; using built-in fallback template.', [
            'view' => $viewName,
            'expected_path' => resource_path('views/emails/plyrcard-registration-verification.blade.php'),
            'user_id' => $user->getKey(),
        ]);

        $firstName = $this->escape((string) ($user->first_name ?: 'Player'));
        $profileUrl = $this->playerUrl($user->activeWebsite);
        $profileUrlEscaped = $this->escape($profileUrl);
        $verificationUrlEscaped = $this->escape($verificationUrl);

        return '<!doctype html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<title>Welcome to PLYRCARD</title></head>'
            . '<body style="margin:0;padding:0;background:#0C0E11;color:#F2F0ED;font-family:Arial,Helvetica,sans-serif">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" bgcolor="#0C0E11"><tr><td align="center" style="padding:32px 12px">'
            . '<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:600px;max-width:600px">'
            . '<tr><td style="padding:0 34px 22px;font-size:19px;font-weight:800">PLYR<span style="color:#FF5A3C">CARD</span></td></tr>'
            . '<tr><td bgcolor="#131619" style="background:#131619;border:1px solid #1E242A;border-radius:14px">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0">'
            . '<tr><td style="padding:38px 34px 0"><div style="font-family:Courier New,monospace;font-size:11px;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:#FF5A3C">Account created</div>'
            . '<h1 style="margin:12px 0 0;font-size:32px;line-height:1.1;color:#F2F0ED">Welcome to PLYRCARD, ' . $firstName . '.</h1>'
            . '<p style="margin:14px 0 0;font-size:15px;line-height:1.6;color:#868E99">Your account is ready. You can start building and sharing your player profile now.</p></td></tr>'
            . ($profileUrl !== ''
                ? '<tr><td style="padding:28px 34px 0"><table role="presentation" width="100%" bgcolor="#1A1E23" style="background:#1A1E23;border:1px solid #262C33;border-radius:12px"><tr><td style="padding:22px"><div style="font-family:Courier New,monospace;font-size:10px;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:#868E99">Your PLYRSITE</div><div style="margin-top:11px;font-size:20px;font-weight:800;color:#F2F0ED;word-break:break-all">' . $profileUrlEscaped . '</div></td></tr></table></td></tr>'
                : '')
            . ($verificationUrl !== ''
                ? '<tr><td style="padding:26px 34px 0"><a href="' . $verificationUrlEscaped . '" style="display:inline-block;padding:14px 28px;background:#FF5A3C;border-radius:10px;color:#0C0E11;text-decoration:none;font-weight:700">Verify email (optional)</a><p style="margin:10px 0 0;font-size:12px;line-height:1.5;color:#5E6670">Verification is optional and does not block access to your account.</p></td></tr>'
                : '')
            . '<tr><td style="padding:28px 34px 34px;font-size:14px;line-height:1.6;color:#868E99">Questions? Reply to this email.<div style="margin-top:16px;font-weight:600;color:#F2F0ED">This is your journey. It has to come from you.<br><span style="color:#FF5A3C">Authenticity is Key.</span></div></td></tr>'
            . '</table></td></tr>'
            . '<tr><td align="center" style="padding:24px 34px 0;font-size:11.5px;line-height:1.7;color:#5E6670">You are receiving this because a PLYRCARD account was created with this address.<br>&copy; 2026 PLYRCARD.</td></tr>'
            . '</table></td></tr></table></body></html>';
    }

    protected function renderActivityEmail(
        User $player,
        Website $website,
        string $activityType,
        string $platform,
        ?string $viewerEmail,
        ?string $viewerName,
        ?string $viewerSchool,
    ): string {
        $viewName = 'emails.plyrcard-player-activity';

        if (View::exists($viewName)) {
            return view($viewName, [
                'player' => $player,
                'website' => $website,
                'activityType' => $activityType,
                'platform' => $platform,
                'viewerEmail' => $viewerEmail,
                'viewerName' => $viewerName,
                'viewerSchool' => $viewerSchool,
            ])->render();
        }

        Log::warning('PLYRCARD activity email Blade view is missing; using built-in fallback template.', [
            'view' => $viewName,
            'expected_path' => resource_path('views/emails/plyrcard-player-activity.blade.php'),
            'user_id' => $player->getKey(),
        ]);

        $isProfile = $activityType === 'profile_view';
        $isHighlight = $activityType === 'highlight_click';
        $label = $isProfile ? 'PROFILE VIEW' : ($isHighlight ? 'HIGHLIGHT CLICK' : strtoupper($platform) . ' CLICK');
        $title = $isProfile
            ? 'A coach viewed your PLYRCARD.'
            : ($isHighlight ? 'A coach clicked your highlight.' : 'A coach clicked your ' . ucfirst($platform) . ' link.');
        $description = $isProfile
            ? 'A coach opened your player profile.'
            : ($isHighlight ? 'A coach clicked through to your YouTube highlight destination.' : 'A coach moved from your PLYRCARD to your ' . $platform . ' destination.');
        $coachBits = array_values(array_filter([$viewerName, $viewerSchool, $viewerEmail]));
        $viewer = 'Coach: ' . (empty($coachBits) ? 'Identified coach' : implode(' · ', $coachBits));
        $profileUrl = $this->playerUrl($website);

        return '<!doctype html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<title>PLYRCARD activity</title></head>'
            . '<body style="margin:0;padding:0;background:#0C0E11;color:#F2F0ED;font-family:Arial,Helvetica,sans-serif">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" bgcolor="#0C0E11"><tr><td align="center" style="padding:32px 12px">'
            . '<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:600px;max-width:600px">'
            . '<tr><td style="padding:0 34px 22px;font-size:19px;font-weight:800">PLYR<span style="color:#FF5A3C">CARD</span></td></tr>'
            . '<tr><td bgcolor="#131619" style="background:#131619;border:1px solid #1E242A;border-radius:14px"><table role="presentation" width="100%">'
            . '<tr><td style="padding:38px 34px 0"><div style="font-family:Courier New,monospace;font-size:11px;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:#FF5A3C">' . $this->escape($label) . '</div>'
            . '<h1 style="margin:12px 0 0;font-size:32px;line-height:1.1;color:#F2F0ED">' . $this->escape($title) . '</h1>'
            . '<p style="margin:14px 0 0;font-size:15px;line-height:1.6;color:#868E99">' . $this->escape($description) . '</p></td></tr>'
            . '<tr><td style="padding:26px 34px 0"><table role="presentation" width="100%" bgcolor="#1A1E23" style="background:#1A1E23;border:1px solid #262C33;border-radius:12px"><tr><td style="padding:22px"><div style="font-family:Courier New,monospace;font-size:10px;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:#868E99">Activity</div><div style="margin-top:10px;font-size:15px;color:#F2F0ED">' . $this->escape($viewer) . '</div><div style="margin-top:8px;font-size:13px;color:#868E99">' . $this->escape(now()->format('M j, Y g:i A T')) . '</div></td></tr></table></td></tr>'
            . ($profileUrl !== ''
                ? '<tr><td style="padding:26px 34px 0"><a href="' . $this->escape($profileUrl) . '" style="display:inline-block;padding:15px 34px;background:#FF5A3C;border-radius:10px;color:#0C0E11;text-decoration:none;font-weight:700">View my PLYRCARD</a></td></tr>'
                : '')
            . '<tr><td style="padding:28px 34px 34px;font-size:14px;line-height:1.6;color:#868E99">Keep your film, stats, schedule, and contact details current so every visit lands on your strongest profile.</td></tr>'
            . '</table></td></tr>'
            . '<tr><td align="center" style="padding:24px 34px 0;font-size:11.5px;line-height:1.7;color:#5E6670">PLYRCARD profile activity notification<br>&copy; 2026 PLYRCARD.</td></tr>'
            . '</table></td></tr></table></body></html>';
    }

    protected function sendHtml(User $user, string $recipient, string $subject, string $html, string $purpose): array
    {
        if (! function_exists('mail') || ! is_callable('mail')) {
            $result = [
                'success' => false,
                'error' => 'PHP mail() is not available on this server.',
            ];

            $this->logFailure($user, $recipient, $subject, $purpose, $result);

            return $result;
        }

        $recipient = $this->sanitizeEmail($recipient);
        $fromEmail = $this->sanitizeEmail(PlyrcardMailSender::email());
        $fromName = PlyrcardMailSender::name();
        $subject = $this->sanitizeHeaderValue($subject);

        if ($recipient === null || $fromEmail === null || $subject === '') {
            $result = [
                'success' => false,
                'error' => 'The recipient, sender, or subject is invalid.',
            ];

            $this->logFailure($user, $recipient ?? '', $subject, $purpose, $result);

            return $result;
        }

        $plainText = $this->plainTextFromHtml($html);
        $boundary = 'plyrcard_' . bin2hex(random_bytes(16));

        $headers = [
            'MIME-Version: 1.0',
            'From: ' . $fromName . ' <' . $fromEmail . '>',
            'Reply-To: ' . $fromEmail,
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
            'X-Mailer: PLYRCARD PHP/' . PHP_VERSION,
        ];

        $message = '--' . $boundary . "\r\n"
            . "Content-Type: text/plain; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: 8bit\r\n\r\n"
            . $plainText . "\r\n"
            . '--' . $boundary . "\r\n"
            . "Content-Type: text/html; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: 8bit\r\n\r\n"
            . $html . "\r\n"
            . '--' . $boundary . '--';

        $headerString = implode("\r\n", $headers);
        $envelopeParameter = '-f' . $fromEmail;

        $sentWithEnvelope = false;
        $sentWithoutEnvelope = false;
        $firstError = null;
        $fallbackError = null;

        try {
            $sentWithEnvelope = @mail(
                $recipient,
                $subject,
                $message,
                $headerString,
                $envelopeParameter,
            );

            $firstError = error_get_last();
        } catch (\Throwable $exception) {
            $firstError = ['message' => $exception->getMessage()];
        }

        if (! $sentWithEnvelope) {
            try {
                $sentWithoutEnvelope = @mail(
                    $recipient,
                    $subject,
                    $message,
                    $headerString,
                );

                $fallbackError = error_get_last();
            } catch (\Throwable $exception) {
                $fallbackError = ['message' => $exception->getMessage()];
            }
        }

        $sent = $sentWithEnvelope || $sentWithoutEnvelope;

        if ($sent) {
            Log::info('PLYRCARD system email handed to native PHP mail().', [
                'purpose' => $purpose,
                'user_id' => $user->getKey(),
                'recipient' => $recipient,
                'from_email' => $fromEmail,
                'subject' => $subject,
                'host' => PlyrcardMailSender::currentHost(),
                'used_envelope_sender' => $sentWithEnvelope,
            ]);

            return [
                'success' => true,
                'transport' => 'php_mail',
                'recipient' => $recipient,
                'from_email' => $fromEmail,
                'used_envelope_sender' => $sentWithEnvelope,
            ];
        }

        $result = [
            'success' => false,
            'transport' => 'php_mail',
            'error' => $this->mailErrorMessage($firstError, $fallbackError),
        ];

        $this->logFailure($user, $recipient, $subject, $purpose, $result);

        return $result;
    }

    protected function recipientFor(User $user): ?string
    {
        foreach ([$user->email, $user->personal_email] as $candidate) {
            $candidate = $this->sanitizeEmail((string) $candidate);

            if ($candidate !== null) {
                return $candidate;
            }
        }

        return null;
    }

    protected function sanitizeEmail(string $email): ?string
    {
        $email = strtolower(trim(str_replace(["\r", "\n"], '', $email)));

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }

    protected function sanitizeHeaderValue(string $value): string
    {
        return trim(str_replace(["\r", "\n"], ' ', $value));
    }

    protected function plainTextFromHtml(string $html): string
    {
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[ \t]+/', ' ', $text) ?? $text;
        $text = preg_replace('/\n\s*\n\s*\n+/', "\n\n", $text) ?? $text;

        return trim($text);
    }

    protected function mailErrorMessage(?array $firstError, ?array $fallbackError): string
    {
        foreach ([$fallbackError, $firstError] as $error) {
            $message = trim((string) ($error['message'] ?? ''));

            if ($message !== '') {
                return $message;
            }
        }

        return 'PHP mail() returned false. The hosting server did not accept the message.';
    }

    protected function playerUrl(?Website $website): string
    {
        if (! $website) {
            return '';
        }

        if (filled($website->domain)) {
            $domain = preg_replace('/^https?:\/\//i', '', trim((string) $website->domain));

            return $domain ? 'https://' . ltrim($domain, '/') : '';
        }

        if (filled($website->slug)) {
            return url('/' . ltrim((string) $website->slug, '/'));
        }

        return '';
    }

    protected function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    protected function logFailure(User $user, string $recipient, string $subject, string $purpose, array $result): void
    {
        Log::error('PLYRCARD native system email send failed.', [
            'purpose' => $purpose,
            'user_id' => $user->getKey(),
            'recipient' => $recipient,
            'from_email' => PlyrcardMailSender::email(),
            'subject' => $subject,
            'host' => PlyrcardMailSender::currentHost(),
            'error' => $result['error'] ?? null,
        ]);
    }
}