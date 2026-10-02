<?php
namespace App\Services;
use App\Models\CreditPointTransaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
class CreditPointService
{
    public function balance(User $user): int
    {
        return (int) $user->fresh()->points_available;
    }
    public function catalog(): array
    {
        $configured = (array) config('plyrcard-points.catalog', []);

        $defaults = [
            'graphic' => ['name' => 'Social graphic', 'points' => 20, 'description' => 'One social graphic.'],
            'reel' => ['name' => 'Highlight reel', 'points' => 50, 'description' => 'One highlight reel, up to 2 minutes.'],
            'outreach' => ['name' => 'Coach outreach campaign', 'points' => 30, 'description' => 'One outreach campaign to up to 25 coaches.'],
            'production_hour' => ['name' => 'Production hour', 'points' => 25, 'description' => 'One shoot or editing hour.'],
            'photo_batch' => ['name' => 'Photo retouch batch', 'points' => 15, 'description' => 'One photo retouch batch.'],
            'site_refresh' => ['name' => 'Profile site refresh', 'points' => 15, 'description' => 'One profile-site refresh.'],
            'film_breakdown' => ['name' => 'Match film breakdown', 'points' => 35, 'description' => 'One match-film breakdown.'],
        ];

        return collect($defaults)->mapWithKeys(function (array $fallback, string $key) use ($configured): array {
            $item = array_merge($fallback, (array) ($configured[$key] ?? []));
            $item['points'] = max(1, (int) ($item['points'] ?? $fallback['points']));
            $item['name'] = trim((string) ($item['name'] ?? $fallback['name'])) ?: $fallback['name'];
            $item['description'] = trim((string) ($item['description'] ?? $fallback['description']));

            return [$key => $item];
        })->all();
    }

    public function quoteService(string $itemKey, int $quantity = 1, bool $rush = false): array
    {
        $itemKey = strtolower(trim($itemKey));
        $catalog = $this->catalog();
        $item = $catalog[$itemKey] ?? null;

        if (! $item) {
            throw ValidationException::withMessages(['item_key' => 'That credit service is not available.']);
        }

        $quantity = max(1, min(20, $quantity));
        $unitPrice = (int) $item['points'];
        $basePoints = $unitPrice * $quantity;
        $totalPoints = $rush ? (int) ceil($basePoints * 1.5) : $basePoints;

        return [
            'item_key' => $itemKey,
            'item_name' => (string) $item['name'],
            'description' => (string) ($item['description'] ?? ''),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'modifier' => $rush ? 'rush' : null,
            'points' => $totalPoints,
            'catalog_version' => (int) config('plyrcard-points.catalog_version', 1),
        ];
    }

    public function spendForServiceRequest(User $user, string $itemKey, int $quantity, bool $rush, string $sourceId, array $meta = []): CreditPointTransaction
    {
        $quote = $this->quoteService($itemKey, $quantity, $rush);
        $sourceId = trim($sourceId);

        if ($sourceId === '') {
            throw ValidationException::withMessages(['credits' => 'A service request ID is required before credits can be spent.']);
        }

        return $this->debit($user, (int) $quote['points'], 'service-request:' . $sourceId, [
            'item_key' => $quote['item_key'],
            'qty' => $quote['quantity'],
            'catalog_version' => $quote['catalog_version'],
            'unit_price' => $quote['unit_price'],
            'modifier' => $quote['modifier'],
            'source_type' => 'service_request',
            'source_id' => $sourceId,
            'reason' => $quote['item_name'] . ' request',
            'actor' => 'athlete',
            'meta' => array_merge([
                'item_name' => $quote['item_name'],
                'irreversible_user_redemption' => true,
            ], $meta),
        ]);
    }

    public function packagePoints(string $package): int
    {
        $package = strtolower(trim($package));
        $configured = config('plyrcard-points.packages.' . $package);

        // Keep the paid package amounts safe even when the config file/key has not
        // been deployed yet. These values match the product UI and checkout copy.
        if ($configured === null) {
            $configured = match ($package) {
                'jumpstart' => 100,
                'amplify' => 600,
                default => 0,
            };
        }

        return max(0, (int) $configured);
    }
    public function grantPackage(User $user, string $package, string $sourceId, array $meta = []): CreditPointTransaction
    {
        $package = strtolower(trim($package));
        $sourceId = trim($sourceId);
        if ($sourceId === '') {
            throw ValidationException::withMessages(['credits' => 'A payment source ID is required before credits can be granted.']);
        }

        if (! Schema::hasTable('credit_point_transactions') || ! Schema::hasColumn('users', 'points_available')) {
            throw ValidationException::withMessages([
                'credits' => 'The credit ledger is not installed. Run the credit-points migration before processing package purchases.',
            ]);
        }

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
    public function hasPackageGrant(User $user, string $package, string $sourceId): bool
    {
        $package = strtolower(trim($package));
        $sourceId = trim($sourceId);
        if ($sourceId === '') {
            return false;
        }

        return CreditPointTransaction::query()
            ->where('user_id', $user->getKey())
            ->where('idempotency_key', 'package:' . $package . ':' . $sourceId)
            ->exists();
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