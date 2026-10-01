<?php
namespace App\Services;
use App\Models\CreditPointTransaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
class CreditPointService
{
    public function balance(User $user): int
    {
        return (int) $user->fresh()->points_available;
    }
    public function packagePoints(string $package): int
    {
        return max(0, (int) config('plyrcard-points.packages.' . $package, 0));
    }
    public function grantPackage(User $user, string $package, string $sourceId, array $meta = []): CreditPointTransaction
    {
        $points = $this->packagePoints($package);
        if ($points <= 0) {
            throw ValidationException::withMessages(['credits' => 'This package does not include credit points.']);
        }
        return $this->write(
            user: $user,
            type: 'grant',
            points: $points,
            idempotencyKey: 'package:' . $package . ':' . $sourceId,
            attributes: [
                'grant_id' => 'grant:' . $package . ':' . $sourceId,
                'source_type' => 'package_purchase',
                'source_id' => $sourceId,
                'reason' => ucfirst($package) . ' purchase',
                'actor' => 'system',
                'meta' => array_merge(['package' => $package], $meta),
            ],
        );
    }
    public function grant(User $user, int $points, string $idempotencyKey, array $attributes = []): CreditPointTransaction
    {
        return $this->write($user, 'grant', $points, $idempotencyKey, $attributes);
    }
    public function hold(User $user, int $points, string $idempotencyKey, array $attributes = []): CreditPointTransaction
    {
        return $this->write($user, 'hold', $points, $idempotencyKey, $attributes);
    }
    public function release(User $user, int $points, string $idempotencyKey, array $attributes = []): CreditPointTransaction
    {
        return $this->write($user, 'release', $points, $idempotencyKey, $attributes);
    }
    public function debit(User $user, int $points, string $idempotencyKey, array $attributes = []): CreditPointTransaction
    {
        return $this->write($user, 'debit', $points, $idempotencyKey, $attributes);
    }
    public function refund(User $user, int $points, string $idempotencyKey, array $attributes = []): CreditPointTransaction
    {
        return $this->write($user, 'refund', $points, $idempotencyKey, $attributes);
    }
    public function adjust(User $user, int $signedPoints, string $reason, string $actor, string $idempotencyKey, array $attributes = []): CreditPointTransaction
    {
        if ($signedPoints === 0) {
            throw ValidationException::withMessages(['points' => 'Adjustment cannot be zero.']);
        }
        return $this->write($user, 'adjust', abs($signedPoints), $idempotencyKey, array_merge($attributes, [
            'reason' => $reason,
            'actor' => $actor,
            'meta' => array_merge((array) ($attributes['meta'] ?? []), ['adjust_sign' => $signedPoints < 0 ? -1 : 1]),
        ]));
    }
    public function settleHold(User $user, CreditPointTransaction $hold, string $deliverableId, string $idempotencyPrefix): void
    {
        if ($hold->type !== 'hold') {
            throw ValidationException::withMessages(['hold' => 'Only a hold can be settled.']);
        }
        DB::transaction(function () use ($user, $hold, $deliverableId, $idempotencyPrefix): void {
            $this->release($user, $hold->points, $idempotencyPrefix . ':release', [
                'grant_id' => $hold->grant_id,
                'item_key' => $hold->item_key,
                'qty' => $hold->qty,
                'catalog_version' => $hold->catalog_version,
                'unit_price' => $hold->unit_price,
                'modifier' => $hold->modifier,
                'deliverable_id' => $deliverableId,
                'source_type' => 'hold_settlement',
                'source_id' => (string) $hold->getKey(),
            ]);
            $this->debit($user, $hold->points, $idempotencyPrefix . ':debit', [
                'grant_id' => $hold->grant_id,
                'item_key' => $hold->item_key,
                'qty' => $hold->qty,
                'catalog_version' => $hold->catalog_version,
                'unit_price' => $hold->unit_price,
                'modifier' => $hold->modifier,
                'deliverable_id' => $deliverableId,
                'source_type' => 'hold_settlement',
                'source_id' => (string) $hold->getKey(),
            ]);
        });
    }
    public function recent(User $user, int $limit = 20): array
    {
        return CreditPointTransaction::query()
            ->where('user_id', $user->getKey())
            ->latest('id')
            ->limit(max(1, min($limit, 100)))
            ->get()
            ->map(fn (CreditPointTransaction $row): array => [
                'id' => $row->id,
                'type' => $row->type,
                'points' => $this->signedDelta($row),
                'reason' => $row->reason,
                'source_type' => $row->source_type,
                'source_id' => $row->source_id,
                'created_at' => optional($row->created_at)->toIso8601String(),
            ])->all();
    }
    public function reconcile(User $user): int
    {
        return DB::transaction(function () use ($user): int {
            $locked = User::query()->lockForUpdate()->findOrFail($user->getKey());
            $balance = CreditPointTransaction::query()->where('user_id', $locked->getKey())->get()->sum(fn ($row) => $this->signedDelta($row));
            $locked->forceFill(['points_available' => max(0, (int) $balance)])->save();
            return (int) $locked->points_available;
        });
    }
    protected function write(User $user, string $type, int $points, string $idempotencyKey, array $attributes): CreditPointTransaction
    {
        if ($points <= 0) {
            throw ValidationException::withMessages(['points' => 'Points must be greater than zero.']);
        }
        return DB::transaction(function () use ($user, $type, $points, $idempotencyKey, $attributes): CreditPointTransaction {
            $existing = CreditPointTransaction::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                return $existing;
            }
            $locked = User::query()->lockForUpdate()->findOrFail($user->getKey());
            $delta = $this->deltaFor($type, $points, (array) ($attributes['meta'] ?? []));
            $next = (int) $locked->points_available + $delta;
            if ($next < 0) {
                throw ValidationException::withMessages(['credits' => 'Not enough available credit points for this request.']);
            }
            $row = CreditPointTransaction::query()->create(array_merge([
                'user_id' => $locked->getKey(),
                'idempotency_key' => $idempotencyKey,
                'type' => $type,
                'points' => $points,
                'qty' => 1,
                'catalog_version' => (int) config('plyrcard-points.catalog_version', 1),
                'actor' => 'system',
            ], $attributes));
            $locked->forceFill(['points_available' => $next])->save();
            return $row;
        });
    }
    protected function deltaFor(string $type, int $points, array $meta = []): int
    {
        return match ($type) {
            'grant', 'release', 'refund' => $points,
            'hold', 'debit' => -$points,
            'adjust' => ((int) ($meta['adjust_sign'] ?? 1) < 0 ? -1 : 1) * $points,
            default => throw ValidationException::withMessages(['type' => 'Unsupported credit transaction type.']),
        };
    }
    protected function signedDelta(CreditPointTransaction $row): int
    {
        return $this->deltaFor($row->type, (int) $row->points, (array) $row->meta);
    }
}
