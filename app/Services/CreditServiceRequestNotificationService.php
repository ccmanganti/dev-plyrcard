<?php

namespace App\Services;

use App\Filament\Resources\CreditServiceRequests\CreditServiceRequestResource;
use App\Models\CreditServiceRequest;
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
     * Send a status-change notification and persist an audit result on the
     * request. This is used by both the Filament EditAction and the model-event
     * fallback so the two paths share the exact same delivery logic.
     */
    public function notifyStatusChangedAndRecord(CreditServiceRequest $request, ?string $previousStatus = null): array
    {
        $requestId = (int) $request->getKey();
        $currentStatus = trim((string) $request->status);
        $previousStatus = trim((string) $previousStatus);

        try {
            $result = $this->notifyStatusChanged(
                $request,
                $previousStatus !== '' ? $previousStatus : null,
            );

            $playerResult = (array) ($result['player'] ?? []);
            $adminResults = (array) ($result['admins'] ?? []);
            $playerSent = (bool) ($playerResult['success'] ?? false);

            $adminCollection = collect($adminResults);
            $adminSentCount = $adminCollection
                ->filter(fn (array $adminResult): bool => (bool) ($adminResult['success'] ?? false))
                ->count();
            $adminFailedCount = max(0, $adminCollection->count() - $adminSentCount);

            $errors = collect();

            if (! $playerSent) {
                $errors->push('Player: ' . (string) ($playerResult['error'] ?? 'email was not accepted'));
            }

            if ($adminResults === []) {
                $errors->push('Admin: no Superadmin email recipient was found');
            } else {
                foreach ($adminResults as $adminResult) {
                    if (! ($adminResult['success'] ?? false)) {
                        $recipient = trim((string) ($adminResult['recipient'] ?? ''));
                        $prefix = $recipient !== '' ? 'Admin ' . $recipient . ': ' : 'Admin: ';
                        $errors->push($prefix . (string) ($adminResult['error'] ?? 'email was not accepted'));
                    }
                }
            }

            // Delivery is intentionally per-recipient. One failed player/admin
            // address must never make successful recipients look failed.
            $anySent = $playerSent || $adminSentCount > 0;
            $allSent = $playerSent
                && $adminResults !== []
                && $adminFailedCount === 0;

            $deliveryStatus = $allSent
                ? 'sent'
                : ($anySent ? 'partial' : 'failed');

            $errorMessage = $errors->isEmpty()
                ? null
                : Str::limit($errors->unique()->implode(' | '), 1000, '');

            CreditServiceRequest::query()
                ->whereKey($requestId)
                ->update([
                    'email_alerted_at' => now(),
                    'email_alert_status' => $deliveryStatus,
                    'email_alert_error' => $errorMessage,
                ]);

            Log::log(
                $deliveryStatus === 'sent' ? 'info' : 'warning',
                $deliveryStatus === 'sent'
                    ? 'PLYRCARD credit-request status emails sent.'
                    : 'PLYRCARD credit-request status email delivery was incomplete.',
                [
                    'request_id' => $requestId,
                    'previous_status' => $previousStatus,
                    'status' => $currentStatus,
                    'delivery_status' => $deliveryStatus,
                    'player_sent' => $playerSent,
                    'admin_sent_count' => $adminSentCount,
                    'admin_failed_count' => $adminFailedCount,
                    'admin_results' => $adminResults,
                    'errors' => $errors->values()->all(),
                ],
            );

            return [
                ...$result,
                'success' => $anySent,
                'all_success' => $allSent,
                'delivery_status' => $deliveryStatus,
                'error' => $errorMessage,
            ];
        } catch (\Throwable $exception) {
            $errorMessage = Str::limit($exception->getMessage(), 1000, '');

            CreditServiceRequest::query()
                ->whereKey($requestId)
                ->update([
                    'email_alerted_at' => now(),
                    'email_alert_status' => 'failed',
                    'email_alert_error' => $errorMessage,
                ]);

            Log::warning('PLYRCARD service-request status notification failed.', [
                'request_id' => $requestId,
                'previous_status' => $previousStatus,
                'status' => $currentStatus,
                'error' => $exception->getMessage(),
            ]);

            return [
                'success' => false,
                'player' => ['success' => false, 'error' => $errorMessage],
                'admins' => [],
                'error' => $errorMessage,
            ];
        }
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
        $recipients = $this->adminRecipients();

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
     * Use the exact same global admin-alert recipients configured under
     * Support Tickets -> System Settings. This is the single source of truth
     * for internal PLYRCARD alert emails across the admin.
     */
    protected function adminRecipients(): array
    {
        $emails = app(SupportAlertService::class)->adminRecipients();

        return collect($emails)
            ->mapWithKeys(fn (string $email): array => [$email => $email])
            ->all();
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
        $result = app(PlyrcardMailTransport::class)->sendHtml(
            recipient: $recipient,
            subject: $subject,
            html: $html,
            replyTo: PlyrcardMailSender::email(),
            context: [
                'purpose' => $purpose,
                'request_id' => $request->getKey(),
                'user_id' => $request->user_id,
                'notification_audience' => 'admin',
            ],
        );

        if (! ($result['success'] ?? false)) {
            $this->logAdminFailure($request, $recipient, $subject, $purpose, $result);
        }

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