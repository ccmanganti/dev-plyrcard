<?php

namespace App\Services;

use App\Support\PlyrcardMailSender;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class PlyrcardMailTransport
{
    public function sendHtml(
        string $recipient,
        string $subject,
        string $html,
        ?string $replyTo = null,
        array $context = [],
    ): array {
        $recipient = $this->sanitizeEmail($recipient);
        $sender = $this->sanitizeEmail(PlyrcardMailSender::email());
        $replyTo = $this->sanitizeEmail((string) $replyTo) ?: $sender;
        $subject = $this->sanitizeHeader($subject);

        if (! $recipient || ! $sender || $subject === '') {
            return [
                'success' => false,
                'recipient' => $recipient,
                'from_email' => $sender,
                'error' => 'The recipient, sender, or subject is invalid.',
            ];
        }

        $attemptErrors = [];
        $attempted = [];
        $mailers = [];

        $defaultMailer = strtolower(trim((string) config('mail.default', '')));
        if ($defaultMailer !== '' && ! in_array($defaultMailer, ['log', 'array'], true)) {
            $mailers[] = $defaultMailer;
        }

        // Dev/test environments sometimes leave MAIL_MAILER=log even though
        // working SMTP credentials are present. Try the configured SMTP mailer
        // explicitly whenever it looks usable.
        if ($this->smtpLooksConfigured()) {
            $mailers[] = 'smtp';
        }

        // Shared hosting commonly provides sendmail even when PHP mail() is
        // disabled or restricted.
        $mailers[] = 'sendmail';

        foreach (array_values(array_unique($mailers)) as $mailer) {
            try {
                Mail::mailer($mailer)->html($html, function ($message) use ($recipient, $sender, $replyTo, $subject): void {
                    $message->to($recipient)
                        ->from($sender, PlyrcardMailSender::name())
                        ->replyTo($replyTo ?: $sender, PlyrcardMailSender::name())
                        ->subject($subject);
                });

                Log::info('PLYRCARD email sent.', array_merge($context, [
                    'recipient' => $recipient,
                    'from_email' => $sender,
                    'reply_to' => $replyTo,
                    'subject' => $subject,
                    'transport' => 'laravel_' . $mailer,
                    'host' => PlyrcardMailSender::currentHost(),
                ]));

                return [
                    'success' => true,
                    'transport' => 'laravel_' . $mailer,
                    'recipient' => $recipient,
                    'from_email' => $sender,
                ];
            } catch (\Throwable $exception) {
                $attempted[] = 'laravel_' . $mailer;
                $attemptErrors[] = $mailer . ': ' . $exception->getMessage();

                Log::warning('PLYRCARD email transport attempt failed.', array_merge($context, [
                    'recipient' => $recipient,
                    'from_email' => $sender,
                    'subject' => $subject,
                    'transport' => 'laravel_' . $mailer,
                    'error' => $exception->getMessage(),
                    'host' => PlyrcardMailSender::currentHost(),
                ]));
            }
        }

        if (function_exists('mail') && is_callable('mail')) {
            $plainText = $this->plainText($html);
            $boundary = 'plyrcard_' . bin2hex(random_bytes(16));
            $headers = [
                'MIME-Version: 1.0',
                'From: ' . PlyrcardMailSender::name() . ' <' . $sender . '>',
                'Reply-To: ' . ($replyTo ?: $sender),
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
            $ok = false;
            $nativeErrors = [];

            try {
                $ok = @mail($recipient, $subject, $message, $headerString, '-f' . $sender);
                if (! $ok) {
                    $error = error_get_last();
                    if (! empty($error['message'])) $nativeErrors[] = $error['message'];
                }
            } catch (\Throwable $exception) {
                $nativeErrors[] = $exception->getMessage();
            }

            if (! $ok) {
                try {
                    $ok = @mail($recipient, $subject, $message, $headerString);
                    if (! $ok) {
                        $error = error_get_last();
                        if (! empty($error['message'])) $nativeErrors[] = $error['message'];
                    }
                } catch (\Throwable $exception) {
                    $nativeErrors[] = $exception->getMessage();
                }
            }

            if ($ok) {
                Log::info('PLYRCARD email sent.', array_merge($context, [
                    'recipient' => $recipient,
                    'from_email' => $sender,
                    'reply_to' => $replyTo,
                    'subject' => $subject,
                    'transport' => 'php_mail',
                    'host' => PlyrcardMailSender::currentHost(),
                ]));

                return [
                    'success' => true,
                    'transport' => 'php_mail',
                    'recipient' => $recipient,
                    'from_email' => $sender,
                ];
            }

            $attempted[] = 'php_mail';
            $attemptErrors = array_merge($attemptErrors, $nativeErrors ?: ['PHP mail() returned false.']);
        }

        $error = $attemptErrors !== []
            ? implode(' | ', array_values(array_unique($attemptErrors)))
            : 'No deliverable mail transport is configured on this environment.';

        Log::error('PLYRCARD email delivery failed.', array_merge($context, [
            'recipient' => $recipient,
            'from_email' => $sender,
            'subject' => $subject,
            'attempted_transports' => array_values(array_unique($attempted)),
            'error' => $error,
            'host' => PlyrcardMailSender::currentHost(),
        ]));

        return [
            'success' => false,
            'recipient' => $recipient,
            'from_email' => $sender,
            'attempted_transports' => array_values(array_unique($attempted)),
            'error' => $error,
        ];
    }

    protected function smtpLooksConfigured(): bool
    {
        $url = trim((string) config('mail.mailers.smtp.url'));
        $host = strtolower(trim((string) config('mail.mailers.smtp.host')));
        $username = trim((string) config('mail.mailers.smtp.username'));

        if ($url !== '' || $username !== '') {
            return true;
        }

        return $host !== ''
            && ! in_array($host, ['127.0.0.1', 'localhost'], true);
    }

    protected function sanitizeEmail(string $email): ?string
    {
        $email = strtolower(trim(str_replace(["\r", "\n"], '', $email)));

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }

    protected function sanitizeHeader(string $value): string
    {
        return trim(str_replace(["\r", "\n"], ' ', $value));
    }

    protected function plainText(string $html): string
    {
        $text = html_entity_decode(
            strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $html)),
            ENT_QUOTES | ENT_HTML5,
            'UTF-8',
        );
        $text = preg_replace('/[ \t]+/', ' ', $text) ?? $text;
        $text = preg_replace('/\n\s*\n\s*\n+/', "\n\n", $text) ?? $text;

        return trim($text);
    }
}
