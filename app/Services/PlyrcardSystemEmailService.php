<?php

namespace App\Services;

use App\Models\User;
use App\Models\Website;
use App\Support\PlyrcardMailSender;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;

class PlyrcardSystemEmailService
{
    public function sendRegistrationWelcome(
        User $user,
        ?string $dashboardUrl = null,
        ?string $planKey = null,
    ): array {
        $recipient = $this->recipientFor($user);

        if (! $recipient) {
            return ['success' => false, 'error' => 'The player does not have a valid email address.'];
        }

        $user->loadMissing('activeWebsite');

        $dashboardUrl = trim((string) $dashboardUrl) !== ''
            ? trim((string) $dashboardUrl)
            : $this->dashboardUrl();

        $html = $this->renderRegistrationEmail($user, $dashboardUrl, $planKey);

        return $this->sendHtml(
            user: $user,
            recipient: $recipient,
            subject: 'Welcome to PLYRCARD',
            html: $html,
            purpose: 'registration_welcome',
        );
    }

    /**
     * Backwards-compatible alias for older callers. Registration email
     * verification is no longer part of the signup experience; even if an old
     * caller supplies a verification URL, the email points to /admin instead.
     */
    public function sendRegistrationVerification(User $user, string $ignoredVerificationUrl = ''): array
    {
        return $this->sendRegistrationWelcome($user);
    }

    public function sendPasswordReset(User $user, string $token): array
    {
        $recipient = $this->recipientFor($user);

        if (! $recipient) {
            return ['success' => false, 'error' => 'The player does not have a valid email address.'];
        }

        $resetUrl = $this->filamentPasswordResetUrl($user, $token);

        if ($resetUrl === '') {
            return ['success' => false, 'error' => 'PLYRCARD could not generate the password reset URL.'];
        }

        $expiresInMinutes = max(1, (int) config('auth.passwords.users.expire', 60));
        $html = $this->renderPasswordResetEmail($user, $resetUrl, $expiresInMinutes);

        return $this->sendHtml(
            user: $user,
            recipient: $recipient,
            subject: 'Reset your PLYRCARD password',
            html: $html,
            purpose: 'password_reset',
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
        // Activity alerts go to the athlete AND any valid parent/guardian emails
        // stored on the player. Registration/welcome mail remains player-only.
        $recipients = $this->activityRecipientsFor($player);

        if ($recipients === []) {
            return ['success' => false, 'error' => 'The player does not have a valid player or parent email address.'];
        }

        $coachLastName = $this->coachLastName($viewerName);
        $coachLabel = $coachLastName !== '' ? 'Coach ' . $coachLastName : 'A coach';
        $schoolLabel = trim((string) $viewerSchool);
        $coachSourceLabel = $coachLabel . ($schoolLabel !== '' ? ' from ' . $schoolLabel : '');

        $subject = match ($activityType) {
            'profile_view' => $coachSourceLabel . ' viewed your PLYRCARD',
            'highlight_click' => $coachSourceLabel . ' clicked your PLYRCARD highlight',
            default => $coachSourceLabel . ' clicked your ' . ucfirst($platform) . ' link',
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

        $purpose = match ($activityType) {
            'profile_view' => 'profile_view_notification',
            'highlight_click' => 'highlight_click_notification',
            default => 'social_click_notification',
        };

        $results = [];
        $sentRecipients = [];
        $failedRecipients = [];

        foreach ($recipients as $role => $recipient) {
            $result = $this->sendHtml(
                user: $player,
                recipient: $recipient,
                subject: $subject,
                html: $html,
                purpose: $purpose . ':' . $role,
            );

            $results[$role] = $result;

            if ($result['success'] ?? false) {
                $sentRecipients[$role] = $recipient;
            } else {
                $failedRecipients[$role] = [
                    'email' => $recipient,
                    'error' => $result['error'] ?? 'Unknown email error.',
                ];
            }
        }

        return [
            'success' => $sentRecipients !== [],
            'all_sent' => $failedRecipients === [],
            'sent_recipients' => $sentRecipients,
            'failed_recipients' => $failedRecipients,
            'results' => $results,
            'error' => $sentRecipients === []
                ? 'The activity notification could not be sent to the player or parent email addresses.'
                : null,
        ];
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

    protected function renderPasswordResetEmail(User $user, string $resetUrl, int $expiresInMinutes): string
    {
        $viewName = 'emails.plyrcard-password-reset';

        if (View::exists($viewName)) {
            return view($viewName, [
                'user' => $user,
                'resetUrl' => $resetUrl,
                'expiresInMinutes' => $expiresInMinutes,
            ])->render();
        }

        Log::warning('PLYRCARD password reset Blade view is missing; using built-in fallback template.', [
            'view' => $viewName,
            'expected_path' => resource_path('views/emails/plyrcard-password-reset.blade.php'),
            'user_id' => $user->getKey(),
        ]);

        $firstName = $this->escape((string) ($user->first_name ?: 'Player'));
        $url = $this->escape($resetUrl);

        return '<!doctype html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Reset your PLYRCARD password</title></head>'
            . '<body style="margin:0;padding:0;background:#0C0E11;color:#F2F0ED;font-family:Arial,Helvetica,sans-serif">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" bgcolor="#0C0E11"><tr><td align="center" style="padding:32px 12px">'
            . '<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:600px;max-width:600px">'
            . '<tr><td style="padding:0 34px 22px;font-size:19px;font-weight:800">PLYR<span style="color:#FF5A3C">CARD</span></td></tr>'
            . '<tr><td bgcolor="#131619" style="background:#131619;border:1px solid #1E242A;border-radius:14px"><table role="presentation" width="100%">'
            . '<tr><td style="padding:38px 34px 0"><div style="font-family:Courier New,monospace;font-size:11px;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:#FF5A3C">Password reset</div>'
            . '<h1 style="margin:12px 0 0;font-size:32px;line-height:1.1;color:#F2F0ED">Reset your PLYRCARD password</h1>'
            . '<p style="margin:14px 0 0;font-size:15px;line-height:1.6;color:#868E99">Hi ' . $firstName . ', we received a request to reset your PLYRCARD password.</p></td></tr>'
            . '<tr><td style="padding:26px 34px 0"><a href="' . $url . '" style="display:inline-block;padding:14px 28px;background:#FF5A3C;border-radius:10px;color:#0C0E11;text-decoration:none;font-weight:700">Reset Password</a></td></tr>'
            . '<tr><td style="padding:24px 34px 34px;font-size:13px;line-height:1.6;color:#868E99">This link expires in ' . $expiresInMinutes . ' minutes. If you did not request it, you can ignore this email.</td></tr>'
            . '</table></td></tr><tr><td align="center" style="padding:24px 34px 0;font-size:11.5px;color:#5E6670">PLYRCARD account security<br>&copy; 2026 PLYRCARD.</td></tr>'
            . '</table></td></tr></table></body></html>';
    }

    protected function renderRegistrationEmail(
        User $user,
        string $dashboardUrl,
        ?string $planKey = null,
    ): string {
        $viewName = 'emails.plyrcard-registration-welcome';
        $isMyJourney = strtolower(trim((string) $planKey)) === 'my-journey';

        if (View::exists($viewName)) {
            return view($viewName, [
                'user' => $user,
                'dashboardUrl' => $dashboardUrl,
                'planKey' => $planKey,
                'isMyJourney' => $isMyJourney,
            ])->render();
        }

        Log::warning('PLYRCARD registration welcome email Blade view is missing; using built-in fallback template.', [
            'view' => $viewName,
            'expected_path' => resource_path('views/emails/plyrcard-registration-welcome.blade.php'),
            'user_id' => $user->getKey(),
        ]);

        $firstName = $this->escape((string) ($user->first_name ?: 'Player'));
        $profileUrl = $this->playerUrl($user->activeWebsite);
        $profileUrlEscaped = $this->escape($profileUrl);
        $dashboardUrlEscaped = $this->escape($dashboardUrl);
        $domainLabel = $isMyJourney ? 'Requested PLYRSITE domain' : 'Your PLYRSITE';
        $domainReview = $isMyJourney
            ? '<div style="margin-top:12px;padding:11px 13px;border:1px solid #3A3324;border-radius:9px;background:#1B1812;color:#D7B56D;font-size:12.5px;line-height:1.5"><strong style="color:#F2F0ED">Pending team review.</strong> Your domain will not be publicly visible until the PLYRCARD team reviews and approves it.</div>'
            : '';

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
            . '<p style="margin:14px 0 0;font-size:15px;line-height:1.6;color:#868E99">Your account is ready. Head to your dashboard to continue building your player profile.</p></td></tr>'
            . ($profileUrl !== ''
                ? '<tr><td style="padding:28px 34px 0"><table role="presentation" width="100%" bgcolor="#1A1E23" style="background:#1A1E23;border:1px solid #262C33;border-radius:12px"><tr><td style="padding:22px"><div style="font-family:Courier New,monospace;font-size:10px;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:#868E99">' . $this->escape($domainLabel) . '</div><div style="margin-top:11px;font-size:20px;font-weight:800;color:#F2F0ED;word-break:break-all">' . $profileUrlEscaped . '</div>' . $domainReview . '</td></tr></table></td></tr>'
                : '')
            . '<tr><td style="padding:26px 34px 0"><a href="' . $dashboardUrlEscaped . '" style="display:inline-block;padding:14px 28px;background:#FF5A3C;border-radius:10px;color:#0C0E11;text-decoration:none;font-weight:700">Go to Dashboard</a></td></tr>'
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
                'coachLastName' => $this->coachLastName($viewerName),
                'dashboardUrl' => $this->dashboardUrl(),
            ])->render();
        }

        Log::warning('PLYRCARD activity email Blade view is missing; using built-in fallback template.', [
            'view' => $viewName,
            'expected_path' => resource_path('views/emails/plyrcard-player-activity.blade.php'),
            'user_id' => $player->getKey(),
        ]);

        $isProfile = $activityType === 'profile_view';
        $isHighlight = $activityType === 'highlight_click';
        $coachLastName = $this->coachLastName($viewerName);
        $coachLabel = $coachLastName !== '' ? 'Coach ' . $coachLastName : 'A coach';
        $schoolLabel = trim((string) $viewerSchool);
        $coachSourceLabel = $coachLabel . ($schoolLabel !== '' ? ' from ' . $schoolLabel : '');
        $label = $isProfile ? 'PROFILE VIEW' : ($isHighlight ? 'HIGHLIGHT CLICK' : strtoupper($platform) . ' CLICK');
        $title = $isProfile
            ? $coachSourceLabel . ' viewed your PLYRCARD'
            : ($isHighlight ? $coachSourceLabel . ' clicked your PLYRCARD highlight' : $coachSourceLabel . ' clicked your ' . ucfirst($platform) . ' link');
        $description = $isProfile
            ? 'A verified coach opened your player profile.'
            : ($isHighlight ? 'A verified coach clicked through to your YouTube highlight.' : 'A verified coach moved from your PLYRCARD to your ' . $platform . ' destination.');
        $dashboardUrl = $this->dashboardUrl();
        $coachName = $viewerName ?: 'Identified coach';
        $coachSchool = $viewerSchool ?: 'Not available';
        $coachEmail = $viewerEmail ?: 'Not available';

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
            . '<tr><td style="padding:26px 34px 0"><table role="presentation" width="100%" bgcolor="#1A1E23" style="background:#1A1E23;border:1px solid #262C33;border-radius:12px"><tr><td style="padding:22px"><div style="font-family:Courier New,monospace;font-size:10px;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:#868E99">Coach details</div>'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:12px"><tr><td style="padding:9px 0;color:#868E99;font-size:13px;width:110px">Coach Name</td><td style="padding:9px 0;color:#F2F0ED;font-size:14px;font-weight:700">' . $this->escape($coachName) . '</td></tr>'
            . '<tr><td style="padding:9px 0;border-top:1px solid #262C33;color:#868E99;font-size:13px">School</td><td style="padding:9px 0;border-top:1px solid #262C33;color:#F2F0ED;font-size:14px">' . $this->escape($coachSchool) . '</td></tr>'
            . '<tr><td style="padding:9px 0;border-top:1px solid #262C33;color:#868E99;font-size:13px">Email</td><td style="padding:9px 0;border-top:1px solid #262C33;color:#F2F0ED;font-size:14px;word-break:break-all">' . $this->escape($coachEmail) . '</td></tr></table>'
            . '<div style="margin-top:12px;font-size:12px;color:#5E6670">' . $this->escape(now()->format('M j, Y g:i A T')) . '</div></td></tr></table></td></tr>'
            . '<tr><td style="padding:26px 34px 0"><a href="' . $this->escape($dashboardUrl) . '" style="display:inline-block;padding:15px 34px;background:#FF5A3C;border-radius:10px;color:#0C0E11;text-decoration:none;font-weight:700">Go to Dashboard</a></td></tr>'
            . '<tr><td style="padding:28px 34px 34px;font-size:14px;line-height:1.6;color:#868E99">Keep your film, stats, schedule, and contact details current so every visit lands on your strongest profile.</td></tr>'
            . '</table></td></tr>'
            . '<tr><td align="center" style="padding:24px 34px 0;font-size:11.5px;line-height:1.7;color:#5E6670">PLYRCARD profile activity notification<br>&copy; 2026 PLYRCARD.</td></tr>'
            . '</table></td></tr></table></body></html>';
    }

    protected function filamentPasswordResetUrl(User $user, string $token): string
    {
        try {
            $panel = \Filament\Facades\Filament::getPanel('admin');

            if (! $panel) {
                return '';
            }

            $authBaseUrl = $this->authBaseUrl();
            $urlGenerator = app('url');

            if ($authBaseUrl !== '') {
                $urlGenerator->forceRootUrl($authBaseUrl);
            }

            try {
                // Filament generates the signed reset URL expected by its reset page.
                return (string) $panel->getResetPasswordUrl($token, $user);
            } finally {
                if ($authBaseUrl !== '') {
                    // Return URL generation to the current request host after the email URL is built.
                    $urlGenerator->forceRootUrl(null);
                }
            }
        } catch (\Throwable $exception) {
            Log::error('PLYRCARD could not generate Filament password reset URL.', [
                'user_id' => $user->getKey(),
                'error' => $exception->getMessage(),
            ]);

            return '';
        }
    }

    protected function authBaseUrl(): string
    {
        $configured = rtrim((string) config('app.url', ''), '/');

        try {
            if (app()->bound('request')) {
                $request = request();
                $host = strtolower(trim((string) $request->getHost()));
                $host = preg_replace('/:\\d+$/', '', $host) ?: $host;
                $host = preg_replace('/^www\\./', '', $host) ?: $host;

                // Main PLYRCARD hosts can safely keep the exact request scheme/port.
                if ($host === 'localhost'
                    || $host === '127.0.0.1'
                    || $host === '::1'
                    || $host === 'dev.plyrcard.com'
                    || str_ends_with($host, '.dev.plyrcard.com')
                    || $host === 'plyrcard.com') {
                    return rtrim($request->getSchemeAndHttpHost(), '/');
                }
            }
        } catch (\Throwable) {
            // Use APP_URL for CLI jobs and player-owned custom domains.
        }

        return $configured !== '' ? $configured : 'http://localhost';
    }

    protected function dashboardUrl(): string
    {
        try {
            if (app()->bound('request')) {
                $request = request();
                $host = trim((string) $request->getHost());

                if ($host !== '') {
                    return rtrim($request->getSchemeAndHttpHost(), '/') . '/admin';
                }
            }
        } catch (\Throwable) {
            // Fall back to APP_URL when no HTTP request is available (for example CLI jobs).
        }

        $baseUrl = rtrim((string) config('app.url', 'http://localhost'), '/');

        if ($baseUrl === '') {
            $baseUrl = 'http://localhost';
        }

        return $baseUrl . '/admin';
    }

    protected function coachLastName(?string $name): string
    {
        $name = trim((string) $name);

        if ($name === '') {
            return '';
        }

        $name = preg_replace('/^coach\s+/i', '', $name) ?: $name;
        $parts = preg_split('/\s+/', $name, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if ($parts === []) {
            return '';
        }

        $suffixes = ['jr', 'jr.', 'sr', 'sr.', 'ii', 'iii', 'iv', 'v'];
        $last = end($parts);

        if (count($parts) > 1 && in_array(strtolower((string) $last), $suffixes, true)) {
            $last = $parts[count($parts) - 2];
        }

        return trim((string) $last, " \t\n\r\0\x0B,.;:");
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

    /**
     * Valid recipients for coach-activity alerts. Keep each address only once
     * so a parent using the player's email does not receive duplicates.
     */
    protected function activityRecipientsFor(User $user): array
    {
        $candidates = [
            'player' => $user->email ?: $user->personal_email,
            'primary_parent' => $user->parent_email
                ?? $user->primary_parent_email
                ?? $user->guardian_email
                ?? null,
            'secondary_parent' => $user->sec_parent_email
                ?? $user->secondary_parent_email
                ?? $user->guardian_email_2
                ?? null,
        ];

        $recipients = [];
        $seen = [];

        foreach ($candidates as $role => $candidate) {
            $email = $this->sanitizeEmail((string) $candidate);

            if ($email === null || isset($seen[$email])) {
                continue;
            }

            $seen[$email] = true;
            $recipients[$role] = $email;
        }

        return $recipients;
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