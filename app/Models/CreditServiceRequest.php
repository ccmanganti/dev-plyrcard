<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
        'notes',
        'status',
        'credit_point_transaction_id',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price_points' => 'integer',
            'points_spent' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function creditTransaction(): BelongsTo
    {
        return $this->belongsTo(CreditPointTransaction::class, 'credit_point_transaction_id');
    }
}
