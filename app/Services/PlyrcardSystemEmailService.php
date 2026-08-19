<?php

namespace App\Services;

use App\Models\User;
use App\Models\Website;
use App\Support\PlyrcardMailSender;
use Illuminate\Support\Facades\Log;

class PlyrcardSystemEmailService
{
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
            purpose: $activityType === 'profile_view'
                ? 'profile_view_notification'
                : 'social_click_notification',
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