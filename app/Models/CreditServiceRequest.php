<?php

namespace App\Models;

use App\Services\CreditServiceRequestNotificationService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CreditServiceRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'request_token',
        'item_key',
        'item_name',
        'quantity',
        'unit_price_points',
        'modifier',
        'points_spent',
        'credits_returned',
        'notes',
        'request_resources',
        'status',
        'admin_notes',
        'managed_by_user_id',
        'reviewed_at',
        'completed_at',
        'admin_contacted_at',
        'last_admin_subject',
        'last_admin_message',
        'delivery_file_path',
        'delivery_url',
        'delivery_notes',
        'provided_at',
        'provided_by_user_id',
        'email_alerted_at',
        'email_alert_status',
        'email_alert_error',
        'credit_point_transaction_id',
    ];

    /**
     * Snapshot of the status before the current update.
     *
     * Capture this before Eloquent syncs its original values so every status
     * transition can be detected reliably from Filament, actions, controllers,
     * or any other normal model save path.
     */
    protected ?string $statusBeforeUpdate = null;

    /**
     * Filament's row EditAction sends status notifications explicitly after its
     * save. Suppress only the model-event notification during that save to avoid
     * duplicate emails while preserving all other model events.
     */
    protected static bool $suppressStatusChangeNotifications = false;

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price_points' => 'integer',
            'points_spent' => 'integer',
            'credits_returned' => 'integer',
            'request_resources' => 'array',
            'reviewed_at' => 'datetime',
            'completed_at' => 'datetime',
            'admin_contacted_at' => 'datetime',
            'provided_at' => 'datetime',
            'email_alerted_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (CreditServiceRequest $request): void {
            if ($request->isDirty('status')) {
                $request->statusBeforeUpdate = trim((string) $request->getOriginal('status'));
            }
        });

        static::saving(function (CreditServiceRequest $request): void {
            if ($request->isDirty('status')) {
                if (
                    in_array($request->status, ['reviewed', 'pending_information', 'in_progress', 'completed'], true)
                    && ! $request->reviewed_at
                ) {
                    $request->reviewed_at = now();
                }

                if ($request->status === 'completed') {
                    $request->completed_at ??= now();
                } else {
                    $request->completed_at = null;
                }
            }

            if (
                auth()->check()
                && ($request->isDirty('status') || $request->isDirty('admin_notes'))
            ) {
                $request->managed_by_user_id = auth()->id();
            }
        });

        /*
         * A brand-new request already has its player-facing submission email in the
         * checkout/submission flow. This hook adds the missing admin email without
         * sending the player a duplicate message.
         */
        static::created(function (CreditServiceRequest $request): void {
            if ((string) $request->status !== 'submitted') {
                return;
            }

            $requestId = (int) $request->getKey();

            $notifyAdmins = function () use ($requestId): void {
                try {
                    $fresh = CreditServiceRequest::query()
                        ->with(['user', 'managedBy'])
                        ->find($requestId);

                    if (! $fresh?->user) {
                        return;
                    }

                    app(CreditServiceRequestNotificationService::class)
                        ->notifyAdminsOfNewSubmission($fresh);
                } catch (\Throwable $exception) {
                    Log::warning('PLYRCARD new service-request admin notification failed.', [
                        'request_id' => $requestId,
                        'error' => $exception->getMessage(),
                    ]);
                }
            };

            if (DB::transactionLevel() > 0) {
                DB::afterCommit($notifyAdmins);
            } else {
                $notifyAdmins();
            }
        });

        /*
         * Every actual status transition is notification-worthy, regardless of
         * direction. That includes moving a request backwards, such as:
         * Completed -> In Progress, In Progress -> Pending Information, or
         * Reviewed -> Submitted.
         */
        static::updated(function (CreditServiceRequest $request): void {
            if (static::$suppressStatusChangeNotifications || ! $request->wasChanged('status')) {
                return;
            }

            // Use the value captured in the updating event. This happens before
            // Eloquent syncs originals, so it is stable even when Filament/Livewire
            // has already completed its own save lifecycle by the time updated runs.
            $previousStatus = trim((string) ($request->statusBeforeUpdate ?? ''));

            // Defensive fallback for updates initiated by unusual framework paths.
            if ($previousStatus === '') {
                $previous = method_exists($request, 'getPrevious')
                    ? (array) $request->getPrevious()
                    : [];

                $previousStatus = trim((string) (
                    $previous['status']
                    ?? $request->getRawOriginal('status')
                    ?? $request->getOriginal('status')
                    ?? ''
                ));
            }
            $currentStatus = trim((string) $request->status);

            if ($currentStatus === '' || $previousStatus === $currentStatus) {
                return;
            }

            $requestId = (int) $request->getKey();

            $fresh = CreditServiceRequest::query()
                ->with(['user', 'managedBy'])
                ->find($requestId);

            if (! $fresh?->user) {
                Log::warning('PLYRCARD service-request status notification skipped: request has no player.', [
                    'request_id' => $requestId,
                    'previous_status' => $previousStatus,
                    'status' => $currentStatus,
                ]);

                return;
            }

            app(CreditServiceRequestNotificationService::class)
                ->notifyStatusChangedAndRecord($fresh, $previousStatus);
        });
    }

    public static function withoutStatusChangeNotifications(callable $callback): mixed
    {
        $previous = static::$suppressStatusChangeNotifications;
        static::$suppressStatusChangeNotifications = true;

        try {
            return $callback();
        } finally {
            static::$suppressStatusChangeNotifications = $previous;
        }
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function creditTransaction(): BelongsTo
    {
        return $this->belongsTo(CreditPointTransaction::class, 'credit_point_transaction_id');
    }

    public function managedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'managed_by_user_id');
    }

    public function providedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'provided_by_user_id');
    }

    public static function statusOptions(): array
    {
        return [
            'submitted' => 'Submitted',
            'reviewed' => 'Reviewed',
            'pending_information' => 'Pending Information',
            'in_progress' => 'In Progress',
            'completed' => 'Completed',
        ];
    }

    public function statusLabel(): string
    {
        return static::statusOptions()[$this->status] ?? Str::headline((string) $this->status);
    }

    public function batchToken(): string
    {
        return (string) preg_replace('/:\\d+$/', '', (string) $this->request_token);
    }

    public function refundableCredits(): int
    {
        return max(0, (int) $this->points_spent - (int) $this->credits_returned);
    }
}