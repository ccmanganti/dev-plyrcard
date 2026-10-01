<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class CreditPointTransaction extends Model
{
    protected $fillable = [
        'user_id', 'idempotency_key', 'type', 'points', 'grant_id', 'item_key', 'qty',
        'catalog_version', 'unit_price', 'modifier', 'reason', 'actor', 'deliverable_id',
        'source_type', 'source_id', 'meta',
    ];
    protected $casts = [
        'points' => 'integer',
        'qty' => 'integer',
        'catalog_version' => 'integer',
        'unit_price' => 'integer',
        'meta' => 'array',
    ];
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
