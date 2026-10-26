<?php

namespace App\Models;

use App\Services\PlyrcardSystemEmailService;
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

        static::updated(function (CreditServiceRequest $request): void {
            if (! $request->wasChanged('status')) {
                return;
            }

            $status = (string) $request->status;
            if ($status === 'submitted') {
                // Submission already sends its own request-created notification.
                return;
            }

            $previousStatus = (string) ($request->getOriginal('status') ?? '');
            $requestId = (int) $request->getKey();

            $notifyPlayer = function () use ($requestId, $previousStatus): void {
                try {
                    $fresh = CreditServiceRequest::query()
                        ->with('user')
                        ->find($requestId);

                    if (! $fresh?->user) {
                        return;
                    }

                    app(PlyrcardSystemEmailService::class)
                        ->sendServiceRequestStatusUpdated($fresh, $previousStatus);
                } catch (\Throwable $exception) {
                    Log::warning('PLYRCARD service-request status notification failed.', [
                        'request_id' => $requestId,
                        'previous_status' => $previousStatus,
                        'error' => $exception->getMessage(),
                    ]);
                }
            };

            if (DB::transactionLevel() > 0) {
                DB::afterCommit($notifyPlayer);
            } else {
                $notifyPlayer();
            }
        });
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