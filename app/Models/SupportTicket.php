<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class SupportTicket extends Model
{
    protected $fillable = [
        'ticket_number',
        'user_id',
        'email',
        'category',
        'message',
        'status',
        'priority',
        'source',
        'admin_notes',
        'resolved_at',
        'email_alerted_at',
        'email_alert_status',
        'email_alert_error',
        'metadata',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
        'email_alerted_at' => 'datetime',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (SupportTicket $ticket): void {
            if (blank($ticket->ticket_number)) {
                $ticket->ticket_number = static::newTicketNumber();
            }
        });

        static::saving(function (SupportTicket $ticket): void {
            if ($ticket->isDirty('status')) {
                if (in_array($ticket->status, ['resolved', 'closed'], true)) {
                    $ticket->resolved_at ??= now();
                } else {
                    $ticket->resolved_at = null;
                }
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function categoryLabel(): string
    {
        return (string) data_get(config('plyrcard-support.categories', []), $this->category, Str::headline($this->category));
    }

    public static function categories(): array
    {
        return (array) config('plyrcard-support.categories', []);
    }

    public static function statuses(): array
    {
        return (array) config('plyrcard-support.statuses', []);
    }

    public static function priorities(): array
    {
        return (array) config('plyrcard-support.priorities', []);
    }

    protected static function newTicketNumber(): string
    {
        do {
            $number = 'PLYR-' . now()->format('Ymd') . '-' . Str::upper(Str::random(7));
        } while (static::query()->where('ticket_number', $number)->exists());

        return $number;
    }
}
