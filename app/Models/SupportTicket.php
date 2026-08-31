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
        'conversation',
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
        'conversation' => 'array',
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

    public function statusLabel(): string
    {
        return (string) data_get(config('plyrcard-support.statuses', []), $this->status, Str::headline($this->status));
    }

    public function appendConversation(string $senderType, ?int $senderId, string $senderName, string $message): void
    {
        $conversation = is_array($this->conversation) ? $this->conversation : [];
        $conversation[] = [
            'sender_type' => $senderType,
            'sender_id' => $senderId,
            'sender_name' => trim($senderName) !== '' ? trim($senderName) : ucfirst($senderType),
            'message' => trim($message),
            'created_at' => now()->toIso8601String(),
        ];

        $this->conversation = $conversation;
    }

    public function getConversationTextAttribute(): string
    {
        $rows = collect(is_array($this->conversation) ? $this->conversation : [])
            ->map(function (array $entry): string {
                $name = trim((string) ($entry['sender_name'] ?? ucfirst((string) ($entry['sender_type'] ?? 'message'))));
                $time = trim((string) ($entry['created_at'] ?? ''));
                $message = trim((string) ($entry['message'] ?? ''));

                return trim($name . ($time !== '' ? ' - ' . $time : '') . "\n" . $message);
            })
            ->filter()
            ->implode("\n\n------------------------------\n\n");

        return $rows !== '' ? $rows : $this->message;
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