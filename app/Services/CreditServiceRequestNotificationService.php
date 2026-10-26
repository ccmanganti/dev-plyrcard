<?php

namespace App\Services;

use App\Filament\Resources\CreditServiceRequests\CreditServiceRequestResource;
use App\Models\CreditServiceRequest;
use App\Models\User;
use App\Support\PlyrcardMailSender;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CreditServiceRequestNotificationService
{
    /**
     * Notify the player and every Credit Requests admin after any status change.
     * The previous status is intentionally retained so reverse/backward changes
     * are just as explicit as normal forward progress.
     */
    public function notifyStatusChanged(CreditServiceRequest $request, ?string $previousStatus = null): array
    {
        $request->loadMissing(['user', 'managedBy']);

        if (! $request->user) {
            return [
                'success' => false,
                'player' => ['success' => false, 'error' => 'Request has no player.'],
                'admins' => [],
            ];
        }

        $previousStatus = trim((string) $previousStatus);

        // Existing player-facing PLYRCARD email. This works for forward and
        // backward transitions. When status is moved back to Submitted, its
        // generic "request updated" copy still includes both old and new status.
        $playerResult = app(PlyrcardSystemEmailService::class)
            ->sendServiceRequestStatusUpdated(
                $request,
                $previousStatus !== '' ? $previousStatus : null,
            );

        $adminResults = $this->sendAdminTransitionEmails(
            request: $request,
            previousStatus: $previousStatus !== '' ? $previousStatus : null,
            initialSubmission: false,
        );

        return [
            'success' => ($playerResult['success'] ?? false)
                && $adminResults !== []
                && collect($adminResults)->every(fn (array $result): bool => (bool) ($result['success'] ?? false)),
            'player' => $playerResult,
            'admins' => $adminResults,
        ];
    }

    /**
     * A new submission already sends the normal player receipt elsewhere in the
     * current PLYR Points flow. This method sends only the missing admin email so
     * the player never gets two identical submission confirmations.
     */
    public function notifyAdminsOfNewSubmission(CreditServiceRequest $request): array
    {
        $request->loadMissing(['user', 'managedBy']);

        return $this->sendAdminTransitionEmails(
            request: $request,
            previousStatus: null,
            initialSubmission: true,
        );
    }

    protected function sendAdminTransitionEmails(
        CreditServiceRequest $request,
        ?string $previousStatus,
        bool $initialSubmission,
    ): array {
        $recipients = $this->adminRecipients($request->user);

        if ($recipients === []) {
            Log::warning('PLYRCARD credit-request notification has no admin recipients.', [
                'request_id' => $request->getKey(),
                'status' => $request->status,
            ]);

            return [];
        }

        $oldLabel = $previousStatus
            ? $this->statusLabel($previousStatus)
            : null;
        $newLabel = $request->statusLabel();

        $subject = $initialSubmission
            ? '[PLYRCARD] New credit request #' . $request->getKey() . ' — ' . $request->item_name
            : '[PLYRCARD] Credit request #' . $request->getKey() . ': '
                . ($oldLabel ?: 'Unknown') . ' → ' . $newLabel;

        $html = $this->adminEmailHtml(
            request: $request,
            previousStatus: $previousStatus,
            initialSubmission: $initialSubmission,
        );

        $results = [];

        foreach ($recipients as $adminId => $recipient) {
            $results[(string) $adminId] = $this->sendHtmlToAdmin(
                request: $request,
                recipient: $recipient,
                subject: $subject,
                html: $html,
                purpose: $initialSubmission
                    ? 'credit_request_admin_submitted'
                    : 'credit_request_admin_status_' . $request->status,
            );
        }

        return $results;
    }

    /**
     * Match the same roles that are currently allowed to manage the Filament
     * Credit Requests resource. This keeps admin email recipients aligned with
     * actual administrative access.
     */
    protected function adminRecipients(?User $player = null): array
    {
        $admins = User::query()
            ->whereHas('roles', fn ($query) => $query->whereIn('name', [
                'Superadmin',
                'superadmin',
                'Super Admin',
            ]))
            ->get();

        $recipients = [];
        $seen = [];

        foreach ($admins as $admin) {
            $recipient = $this->firstValidEmail([
                $admin->email,
                $admin->personal_email,
            ]);

            // Do not exclude the player address here. A Superadmin can also own a
            // test/player request (as in dev), and the requirement is that both the
            // player notification and the admin notification are sent. In that case
            // the same mailbox intentionally receives the two role-specific emails.
            if ($recipient === null || isset($seen[$recipient])) {
                continue;
            }

            $seen[$recipient] = true;
            $recipients[(string) $admin->getKey()] = $recipient;
        }

        return $recipients;
    }

    protected function adminEmailHtml(
        CreditServiceRequest $request,
        ?string $previousStatus,
        bool $initialSubmission,
    ): string {
        $player = $request->user;
        $playerName = trim(collect([
            $player?->first_name,
            $player?->last_name,
        ])->filter()->implode(' '));

        if ($playerName === '') {
            $playerName = 'Player #' . $request->user_id;
        }

        $playerEmail = $this->firstValidEmail([
            $player?->email,
            $player?->personal_email,
        ]) ?? 'No valid player email';

        $manager = $request->managedBy;
        $managerName = trim(collect([
            $manager?->first_name,
            $manager?->last_name,
        ])->filter()->implode(' '));

        if ($managerName === '') {
            $managerName = trim((string) ($manager?->email ?? '')) ?: 'System';
        } elseif (filled($manager?->email)) {
            $managerName .= ' (' . $manager->email . ')';
        }

        $oldLabel = $previousStatus ? $this->statusLabel($previousStatus) : null;
        $newLabel = $request->statusLabel();

        $headline = $initialSubmission
            ? 'New PLYR Points request submitted'
            : 'Credit request status changed';

        $message = $initialSubmission
            ? $playerName . ' submitted a new PLYR Points service request.'
            : $playerName . "'s request changed from " . ($oldLabel ?: 'Unknown') . ' to ' . $newLabel . '.';

        $details = [
            'Request' => '#' . $request->getKey(),
            'Player' => $playerName,
            'Player email' => $playerEmail,
            'Service' => (string) $request->item_name,
            'Quantity' => number_format((int) $request->quantity),
            'Points used' => number_format((int) $request->points_spent),
        ];

        if ($oldLabel !== null) {
            $details['Previous status'] = $oldLabel;
        }

        $details['New status'] = $newLabel;

        if (! $initialSubmission) {
            $details['Changed by'] = $managerName;
        }

        if ($request->modifier === 'rush') {
            $details['Rush'] = 'Yes';
        }

        $rows = '';
        foreach ($details as $label => $value) {
            $rows .= '<tr>'
                . '<td style="padding:8px 0;color:#868E99;font-size:13px;width:150px;vertical-align:top">'
                . $this->escape($label)
                . '</td>'
                . '<td style="padding:8px 0;color:#F2F0ED;font-size:14px;font-weight:700">'
                . $this->escape((string) $value)
                . '</td>'
                . '</tr>';
        }

        $adminUrl = $this->adminRequestUrl($request);

        return '<!doctype html><html><body style="margin:0;background:#0C0E11;color:#F2F0ED;font-family:Arial,Helvetica,sans-serif">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" bgcolor="#0C0E11">'
            . '<tr><td align="center" style="padding:32px 12px">'
            . '<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:600px;max-width:600px">'
            . '<tr><td style="padding:0 34px 22px;font-size:19px;font-weight:800">PLYR<span style="color:#FF5A3C">CARD</span> <span style="color:#868E99;font-size:12px">ADMIN</span></td></tr>'
            . '<tr><td bgcolor="#131619" style="background:#131619;border:1px solid #1E242A;border-radius:14px">'
            . '<table role="presentation" width="100%">'
            . '<tr><td style="padding:38px 34px 0">'
            . '<div style="font-family:Courier New,monospace;font-size:11px;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:#FF5A3C">Credit request update</div>'
            . '<h1 style="margin:12px 0 0;font-size:29px;line-height:1.12;color:#F2F0ED">' . $this->escape($headline) . '</h1>'
            . '<p style="margin:14px 0 0;font-size:15px;line-height:1.65;color:#AAB0B7">' . $this->escape($message) . '</p>'
            . '</td></tr>'
            . '<tr><td style="padding:24px 34px 0"><table role="presentation" width="100%" cellpadding="0" cellspacing="0">' . $rows . '</table></td></tr>'
            . '<tr><td style="padding:26px 34px 34px"><a href="' . $this->escape($adminUrl) . '" style="display:inline-block;padding:14px 24px;background:#FF5A3C;border-radius:10px;color:#fff;text-decoration:none;font-weight:800">Open Credit Request</a></td></tr>'
            . '</table></td></tr>'
            . '<tr><td align="center" style="padding:22px 34px 0;font-size:11.5px;color:#5E6670">PLYRCARD admin notification<br>&copy; ' . now()->year . ' PLYRCARD.</td></tr>'
            . '</table></td></tr></table></body></html>';
    }

    protected function adminRequestUrl(CreditServiceRequest $request): string
    {
        try {
            return CreditServiceRequestResource::getUrl('edit', [
                'record' => $request,
            ]);
        } catch (\Throwable) {
            return url('/admin/credit-service-requests/' . $request->getKey() . '/edit');
        }
    }

    protected function statusLabel(string $status): string
    {
        return CreditServiceRequest::statusOptions()[$status] ?? Str::headline($status);
    }

    protected function sendHtmlToAdmin(
        CreditServiceRequest $request,
        string $recipient,
        string $subject,
        string $html,
        string $purpose,
    ): array {
        if (! function_exists('mail') || ! is_callable('mail')) {
            $result = ['success' => false, 'error' => 'PHP mail() is not available on this server.'];
            $this->logAdminFailure($request, $recipient, $subject, $purpose, $result);

            return $result;
        }

        $recipient = $this->sanitizeEmail($recipient);
        $fromEmail = $this->sanitizeEmail(PlyrcardMailSender::email());
        $fromName = trim(str_replace(["\r", "\n"], ' ', PlyrcardMailSender::name()));
        $subject = trim(str_replace(["\r", "\n"], ' ', $subject));

        if ($recipient === null || $fromEmail === null || $subject === '') {
            $result = ['success' => false, 'error' => 'The recipient, sender, or subject is invalid.'];
            $this->logAdminFailure($request, $recipient ?? '', $subject, $purpose, $result);

            return $result;
        }

        $plainText = trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
        $boundary = 'plyrcard_credit_' . bin2hex(random_bytes(16));

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
            Log::info('PLYRCARD credit-request admin email handed to native PHP mail().', [
                'purpose' => $purpose,
                'request_id' => $request->getKey(),
                'user_id' => $request->user_id,
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
                'used_envelope_sender' => $sentWithEnvelope,
            ];
        }

        $result = [
            'success' => false,
            'transport' => 'php_mail',
            'error' => $this->mailErrorMessage($firstError, $fallbackError),
        ];

        $this->logAdminFailure($request, $recipient, $subject, $purpose, $result);

        return $result;
    }

    protected function firstValidEmail(array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            $email = $this->sanitizeEmail((string) $candidate);
            if ($email !== null) {
                return $email;
            }
        }

        return null;
    }

    protected function sanitizeEmail(string $email): ?string
    {
        $email = strtolower(trim(str_replace(["\r", "\n"], '', $email)));

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }

    protected function mailErrorMessage(?array $firstError, ?array $fallbackError): string
    {
        $messages = collect([
            $firstError['message'] ?? null,
            $fallbackError['message'] ?? null,
        ])->filter()->unique()->values();

        return $messages->isNotEmpty()
            ? $messages->implode(' | ')
            : 'PHP mail() returned false.';
    }

    protected function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    protected function logAdminFailure(
        CreditServiceRequest $request,
        string $recipient,
        string $subject,
        string $purpose,
        array $result,
    ): void {
        Log::error('PLYRCARD credit-request admin email send failed.', [
            'purpose' => $purpose,
            'request_id' => $request->getKey(),
            'user_id' => $request->user_id,
            'recipient' => $recipient,
            'from_email' => PlyrcardMailSender::email(),
            'subject' => $subject,
            'host' => PlyrcardMailSender::currentHost(),
            'error' => $result['error'] ?? null,
        ]);
    }
}