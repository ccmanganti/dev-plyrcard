<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayerReferral extends Model
{
    protected $fillable = [
        'inviter_user_id',
        'invitee_user_id',
        'referral_code',
        'invitee_plan_key',
        'status',
        'registered_at',
        'qualified_at',
        'inviter_points_awarded',
        'invitee_points_awarded',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'registered_at' => 'datetime',
            'qualified_at' => 'datetime',
            'inviter_points_awarded' => 'integer',
            'invitee_points_awarded' => 'integer',
            'meta' => 'array',
        ];
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inviter_user_id');
    }

    public function invitee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invitee_user_id');
    }
}
