<?php

namespace App\Services;

use App\Models\BillingInformation;
use App\Models\PlayerReferral;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class PlayerReferralService
{
    public function normalizeCode(mixed $value): string
    {
        return Str::of((string) $value)
            ->upper()
            ->trim()
            ->replaceMatches('/[^A-Z0-9-]/', '')
            ->limit(64, '')
            ->toString();
    }

    public function codeFor(User $user): string
    {
        $current = $this->normalizeCode($user->referral_code ?? '');
        if ($current !== '') {
            return $current;
        }

        $first = Str::of((string) ($user->first_name ?: 'PLYR'))
            ->ascii()
            ->upper()
            ->replaceMatches('/[^A-Z0-9]/', '')
            ->limit(20, '')
            ->toString();

        $first = $first !== '' ? $first : 'PLYR';
        $suffix = strtoupper(base_convert((string) max(1, (int) $user->getKey()), 10, 36));
        $candidate = $first . '-' . $suffix;

        if (! Schema::hasColumn($user->getTable(), 'referral_code')) {
            return $candidate;
        }

        $base = $candidate;
        $counter = 2;
        while (User::query()
            ->where('id', '<>', $user->getKey())
            ->whereRaw('UPPER(referral_code) = ?', [$candidate])
            ->exists()) {
            $candidate = Str::limit($base, 58, '') . '-' . $counter++;
        }

        $user->forceFill(['referral_code' => $candidate])->saveQuietly();

        return $candidate;
    }

    public function resolveInviter(mixed $code): ?User
    {
        $code = $this->normalizeCode($code);
        if ($code === '' || ! Schema::hasColumn('users', 'referral_code')) {
            return null;
        }

        return User::query()
            ->whereRaw('UPPER(referral_code) = ?', [$code])
            ->first();
    }

    public function registerReferral(?User $inviter, User $invitee, string $planKey): ?PlayerReferral
    {
        if (! $inviter
            || $inviter->is($invitee)
            || ! Schema::hasTable('player_referrals')) {
            return null;
        }

        $code = $this->codeFor($inviter);

        return PlayerReferral::query()->firstOrCreate(
            ['invitee_user_id' => $invitee->getKey()],
            [
                'inviter_user_id' => $inviter->getKey(),
                'referral_code' => $code,
                'invitee_plan_key' => $this->normalizePlanKey($planKey),
                'status' => 'registered',
                'registered_at' => now(),
                'meta' => [
                    'invitee_email' => $invitee->email,
                    'registration_source' => $invitee->registration_source,
                ],
            ],
        );
    }

    public function notifyReferralUsed(?PlayerReferral $referral): void
    {
        if (! $referral || ! $referral->wasRecentlyCreated) {
            return;
        }

        try {
            $referral->loadMissing(['inviter', 'invitee']);
            if ($referral->inviter && $referral->invitee) {
                app(PlyrcardSystemEmailService::class)->sendReferralUsed(
                    $referral->inviter,
                    $referral->invitee,
                    (int) config('plyrcard-referrals.inviter_points', 50),
                );
            }
        } catch (\Throwable $exception) {
            Log::warning('PLYRCARD referral-used notification failed.', [
                'referral_id' => $referral->getKey(),
                'error' => $exception->getMessage(),
            ]);
        }
    }

    public function qualifyBilling(BillingInformation $billing): ?PlayerReferral
    {
        if (strtolower(trim((string) $billing->payment_status)) !== 'paid') {
            return null;
        }

        $user = $billing->user()->first();
        if (! $user) {
            return null;
        }

        $meta = is_array($billing->registration_meta) ? $billing->registration_meta : [];
        $plans = collect([
            $billing->plan_key,
            data_get($meta, 'source_utm_plan'),
            data_get($meta, 'stripe.checkout_plan_key'),
        ])->map(fn ($plan) => $this->normalizePlanKey($plan))->filter()->unique();

        $qualifyingPlans = collect((array) config('plyrcard-referrals.qualifying_plans', ['my-journey', 'jumpstart', 'amplify']))
            ->map(fn ($plan) => $this->normalizePlanKey($plan));

        $hasQualifyingPlan = $plans->contains(fn (string $plan) => $qualifyingPlans->contains($plan));
        $hasJourneyRole = method_exists($user, 'hasRole')
            && ($user->hasRole('My Journey') || $user->hasRole('my journey'));

        if (! $hasQualifyingPlan && ! $hasJourneyRole) {
            return null;
        }

        return $this->qualify($user, $billing);
    }

    public function qualifyUserIfEligible(User $user): ?PlayerReferral
    {
        if (! Schema::hasTable('player_referrals')) {
            return null;
        }

        $pending = PlayerReferral::query()
            ->where('invitee_user_id', $user->getKey())
            ->whereNull('qualified_at')
            ->exists();

        if (! $pending) {
            return null;
        }

        $billing = BillingInformation::query()
            ->where('user_id', $user->getKey())
            ->where('payment_status', 'paid')
            ->latest('id')
            ->first();

        return $billing ? $this->qualifyBilling($billing) : null;
    }

    protected function qualify(User $invitee, BillingInformation $billing): ?PlayerReferral
    {
        if (! Schema::hasTable('player_referrals')) {
            return null;
        }

        $newlyQualified = false;
        $referral = DB::transaction(function () use ($invitee, $billing, &$newlyQualified): ?PlayerReferral {
            $referral = PlayerReferral::query()
                ->where('invitee_user_id', $invitee->getKey())
                ->lockForUpdate()
                ->first();

            if (! $referral || $referral->qualified_at) {
                return $referral;
            }

            $inviter = User::query()->find($referral->inviter_user_id);
            if (! $inviter) {
                return $referral;
            }

            $inviterPoints = max(1, (int) config('plyrcard-referrals.inviter_points', 50));
            $inviteePoints = max(1, (int) config('plyrcard-referrals.invitee_points', 50));
            $credits = app(CreditPointService::class);

            $inviterGrant = $credits->grant(
                $inviter,
                $inviterPoints,
                'referral:' . $referral->getKey() . ':inviter',
                [
                    'source_type' => 'referral_reward',
                    'source_id' => (string) $referral->getKey(),
                    'reason' => 'Referral reward',
                    'actor' => 'system',
                    'meta' => [
                        'referral_code' => $referral->referral_code,
                        'invitee_user_id' => $invitee->getKey(),
                    ],
                ],
            );

            $inviteeGrant = $credits->grant(
                $invitee,
                $inviteePoints,
                'referral:' . $referral->getKey() . ':invitee',
                [
                    'source_type' => 'referral_welcome',
                    'source_id' => (string) $referral->getKey(),
                    'reason' => 'Referral signup bonus',
                    'actor' => 'system',
                    'meta' => [
                        'referral_code' => $referral->referral_code,
                        'inviter_user_id' => $inviter->getKey(),
                    ],
                ],
            );

            $referral->forceFill([
                'invitee_plan_key' => $this->normalizePlanKey(
                    data_get($billing->registration_meta, 'source_utm_plan') ?: $billing->plan_key
                ),
                'status' => 'qualified',
                'qualified_at' => now(),
                'inviter_points_awarded' => (int) $inviterGrant->points,
                'invitee_points_awarded' => (int) $inviteeGrant->points,
                'meta' => array_merge((array) $referral->meta, [
                    'qualified_billing_id' => $billing->getKey(),
                    'qualified_payment_status' => $billing->payment_status,
                    'qualified_at' => now()->toIso8601String(),
                ]),
            ])->save();

            $newlyQualified = true;

            return $referral->fresh(['inviter', 'invitee']);
        });

        if ($newlyQualified && $referral?->inviter && $referral?->invitee) {
            try {
                app(PlyrcardSystemEmailService::class)->sendReferralQualified(
                    $referral->inviter,
                    $referral->invitee,
                    (int) $referral->inviter_points_awarded,
                );
            } catch (\Throwable $exception) {
                Log::warning('PLYRCARD referral-qualified notification failed.', [
                    'referral_id' => $referral->getKey(),
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        return $referral;
    }

    public function dashboardData(User $user): array
    {
        if (! Schema::hasTable('player_referrals') || ! Schema::hasColumn('users', 'referral_code')) {
            return [
                'code' => '',
                'link' => '',
                'invited' => 0,
                'qualified' => 0,
                'points_earned' => 0,
                'rows' => collect(),
                'enabled' => false,
            ];
        }

        // Defensive retries for paid referrals if a payment event happened before
        // this referral service was deployed or an earlier observer run failed.
        $this->qualifyUserIfEligible($user);
        PlayerReferral::query()
            ->where('inviter_user_id', $user->getKey())
            ->whereNull('qualified_at')
            ->limit(100)
            ->pluck('invitee_user_id')
            ->each(function ($inviteeId): void {
                $invitee = User::query()->find($inviteeId);
                if ($invitee) {
                    $this->qualifyUserIfEligible($invitee);
                }
            });

        $code = $this->codeFor($user);
        $referrals = PlayerReferral::query()
            ->with('invitee')
            ->where('inviter_user_id', $user->getKey())
            ->latest('id')
            ->get();

        $rows = $referrals->take(5)->map(function (PlayerReferral $referral): array {
            $invitee = $referral->invitee;
            $first = trim((string) ($invitee?->first_name ?? 'Player')) ?: 'Player';
            $last = trim((string) ($invitee?->last_name ?? ''));
            $display = $first . ($last !== '' ? ' ' . mb_strtoupper(mb_substr($last, 0, 1)) . '.' : '');
            $initials = mb_strtoupper(mb_substr($first, 0, 1) . ($last !== '' ? mb_substr($last, 0, 1) : ''));

            if ($referral->qualified_at) {
                $detail = 'Joined My Journey · ' . optional($referral->qualified_at)->format('M j');
                $badge = '+' . number_format((int) $referral->inviter_points_awarded) . ' pts';
                $tone = 'earned';
            } elseif ($this->normalizePlanKey($referral->invitee_plan_key) === 'free') {
                $detail = 'Signed up free · ' . optional($referral->registered_at)->format('M j');
                $badge = 'Free plan';
                $tone = 'free';
            } else {
                $detail = 'Signed up · payment pending';
                $badge = 'Pending';
                $tone = 'pending';
            }

            return [
                'name' => $display,
                'initials' => $initials ?: 'P',
                'detail' => $detail,
                'badge' => $badge,
                'tone' => $tone,
            ];
        })->values();

        return [
            'code' => $code,
            'link' => route('marketing.registration', ['ref' => $code]),
            'invited' => $referrals->count(),
            'qualified' => $referrals->whereNotNull('qualified_at')->count(),
            'points_earned' => (int) $referrals->sum('inviter_points_awarded'),
            'rows' => $rows,
            'enabled' => true,
        ];
    }

    protected function normalizePlanKey(mixed $value): string
    {
        $value = Str::of((string) $value)
            ->lower()
            ->trim()
            ->replace(['_', ' '], '-')
            ->replaceMatches('/-+/', '-')
            ->toString();

        return match ($value) {
            'myjourney', 'journey' => 'my-journey',
            'jump-start' => 'jumpstart',
            'power-4', 'power4' => 'amplify',
            default => $value !== '' ? $value : 'free',
        };
    }
}
