<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminSupportMessage extends Model
{
    protected $fillable = [
        'admin_user_id',
        'user_id',
        'concern',
        'channel',
        'subject',
        'template_subject',
        'template_message',
        'rendered_message',
        'variables',
        'recipient_email',
        'recipient_phone',
        'email_status',
        'sms_status',
        'provider_contact_id',
        'provider_message_ids',
        'error_message',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'variables' => 'array',
            'provider_message_ids' => 'array',
            'sent_at' => 'datetime',
        ];
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_user_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
