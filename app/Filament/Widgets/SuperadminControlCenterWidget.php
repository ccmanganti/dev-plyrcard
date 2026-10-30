<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\CreditServiceRequests\CreditServiceRequestResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\CreditServiceRequest;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Services\ProfileCompletionService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class SuperadminControlCenterWidget extends SuperadminOverviewWidget
{
    protected string $view = 'filament.widgets.superadmin-control-center-widget';

    protected static ?int $sort = 1;

    public function getViewData(): array
    {
        return Cache::remember(
            'superadmin:control-center:v1',
            now()->addSeconds(45),
            function (): array {
                $base = parent::getViewData();

                return array_merge($base, $this->buildOperationalData());
            },
        );
    }

    protected function buildOperationalData(): array
    {
        $profileService = app(ProfileCompletionService::class);
        $loginTracking = Schema::hasColumn('users', 'last_login_at');

        $athletes = $this->athleteQuery()
            ->with([
                'roles',
                'billingInformation',
                'activeWebsite',
                'club',
            ])
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();

        $athleteIds = $athletes
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $lastOutreach = $this->lastOutreachByAthlete($athleteIds);

        $rows = $athletes
            ->map(function (User $user) use ($profileService, $loginTracking, $lastOutreach): array {
                $billing = $user->billingInformation;
                $profileCompletion = $this->profileCompletion($profileService, $user);
                $isRecurring = $this->isCurrentRecurring($user);

                $flags = $this->followUpFlags(
                    user: $user,
                    profileCompletion: $profileCompletion,
                    isRecurring: $isRecurring,
                    lastOutreachAt: $lastOutreach[(int) $user->getKey()] ?? null,
                );

                $lastLoginLabel = $loginTracking ? 'Never' : 'Not tracked';

                if ($loginTracking) {
                    $lastLogin = $user->getAttribute('last_login_at');

                    if (blank($lastLogin)) {
                        $flags[] = [
                            'key' => 'getting_started',
                            'label' => 'Never logged in',
                            'detail' => 'The account exists, but no login has been recorded yet.',
                            'weight' => 30,
                            'tone' => 'danger',
                            'concern' => 'getting_started',
                            'action_label' => 'Send login reminder',
                        ];
                    } else {
                        try {
                            $lastLoginAt = $lastLogin instanceof \DateTimeInterface
                                ? Carbon::instance($lastLogin)
                                : Carbon::parse((string) $lastLogin);

                            $days = max(0, now()->diffInDays($lastLoginAt));
                            $lastLoginLabel = $lastLoginAt->diffForHumans();

                            if ($days > 21) {
                                $flags[] = [
                                    'key' => 'getting_started',
                                    'label' => 'Dormant ' . number_format($days) . 'd',
                                    'detail' => 'The player has not logged in for ' . number_format($days) . ' days.',
                                    'weight' => 18,
                                    'tone' => 'warning',
                                    'concern' => 'getting_started',
                                    'action_label' => 'Send login reminder',
                                ];
                            }
                        } catch (Throwable) {
                            // Ignore malformed legacy timestamps.
                        }
                    }
                }

                $flags = collect($flags)
                    ->sortByDesc('weight')
                    ->values()
                    ->all();

                $health = max(0, min(100, 100 - collect($flags)->sum('weight')));
                $topFlag = collect($flags)->first();

                $positions = is_array($user->position)
                    ? collect($user->position)->filter()->implode(' / ')
                    : trim((string) $user->position);

                $lastOutreachAt = $lastOutreach[(int) $user->getKey()] ?? null;
                $lastOutreachLabel = 'No outreach';

                if ($lastOutreachAt) {
                    try {
                        $outreachAt = $lastOutreachAt instanceof \DateTimeInterface
                            ? Carbon::instance($lastOutreachAt)
                            : Carbon::parse((string) $lastOutreachAt);
                        $lastOutreachLabel = $outreachAt->diffForHumans();
                    } catch (Throwable) {
                        $lastOutreachLabel = 'Recorded';
                    }
                }

                $subscriptionStatus = Str::lower(trim((string) ($billing?->subscription_status ?? '')));
                $paymentStatus = Str::lower(trim((string) ($billing?->payment_status ?? '')));

                $state = match (true) {
                    in_array($subscriptionStatus, ['canceled', 'cancelled', 'expired', 'ended'], true) => 'churned',
                    in_array($subscriptionStatus, ['past_due', 'past due', 'unpaid', 'incomplete'], true)
                        || in_array($paymentStatus, ['failed', 'past_due', 'past due', 'unpaid', 'declined', 'incomplete'], true) => 'past_due',
                    $health >= 80 => 'active',
                    $health >= 50 => 'needs_push',
                    default => 'at_risk',
                };

                $website = $user->activeWebsite;

                return [
                    'id' => (int) $user->getKey(),
                    'name' => $this->userName($user),
                    'initials' => $this->initials($user),
                    'email' => (string) ($user->personal_email ?: $user->email ?: ''),
                    'sport' => $this->sportLabel((string) ($user->sport ?? '')),
                    'sport_key' => Str::lower(trim((string) ($user->sport ?? ''))),
                    'plan' => $this->planLabel($user),
                    'plan_key' => Str::slug($this->planLabel($user)),
                    'year' => trim((string) ($user->year ?? '')),
                    'position' => $positions !== '' ? $positions : 'Position not set',
                    'club' => trim((string) ($user->club?->name ?? $user->team_name ?? '')) ?: 'Club not set',
                    'profile_completion' => $profileCompletion ?? 0,
                    'joined' => $user->created_at?->format('M j, Y') ?? 'Unknown',
                    'joined_sort' => $user->created_at?->timestamp ?? 0,
                    'last_login' => $lastLoginLabel,
                    'last_outreach' => $lastOutreachLabel,
                    'health' => $health,
                    'state' => $state,
                    'flags' => $flags,
                    'top_flag' => $topFlag,
                    'reminder_concern' => (string) ($topFlag['concern'] ?? $this->reminderConcernForFlag($topFlag)),
                    'is_recurring' => $isRecurring,
                    'billing' => [
                        'plan_key' => trim((string) ($billing?->plan_key ?? '')),
                        'payment_status' => trim((string) ($billing?->payment_status ?? '')) ?: 'Not available',
                        'subscription_status' => trim((string) ($billing?->subscription_status ?? '')) ?: 'Not available',
                        'recurring_cents' => max(0, (int) ($billing?->recurring_amount_cents ?? 0)),
                        'setup_cents' => max(0, (int) ($billing?->setup_fee_cents ?? 0)),
                        'provider' => trim((string) ($billing?->payment_provider ?? '')) ?: 'Not set',
                    ],
                    'website' => [
                        'exists' => (bool) $website,
                        'published' => (bool) ($website?->is_published ?? false),
                        'domain' => trim((string) ($website?->domain ?? '')),
                        'slug' => trim((string) ($website?->slug ?? '')),
                    ],
                    'user_url' => $this->userUrl($user),
                ];
            })
            ->values();

        $attention = $rows
            ->filter(fn (array $row): bool => ! empty($row['flags']))
            ->sortBy([
                ['health', 'asc'],
                ['name', 'asc'],
            ])
            ->values();

        $planSummary = $rows
            ->groupBy('plan')
            ->map(fn (Collection $items, string $plan): array => [
                'plan' => $plan,
                'count' => $items->count(),
                'recurring_cents' => $items->sum(fn (array $row): int => $row['is_recurring']
                    ? (int) $row['billing']['recurring_cents']
                    : 0),
            ])
            ->sortByDesc('count')
            ->values()
            ->all();

        $billingRows = $rows
            ->filter(fn (array $row): bool => $row['billing']['plan_key'] !== '' || $row['is_recurring'])
            ->sortBy(function (array $row): int {
                return match ($row['state']) {
                    'past_due' => 0,
                    'active' => 1,
                    'needs_push' => 2,
                    'at_risk' => 3,
                    'churned' => 4,
                    default => 5,
                };
            })
            ->values()
            ->all();

        $freeCount = $rows->filter(fn (array $row): bool => str_contains(Str::lower($row['plan']), 'free'))->count();
        $activeSubscriptions = $rows->where('is_recurring', true)->count();
        $mrrCents = $rows->sum(fn (array $row): int => $row['is_recurring']
            ? (int) $row['billing']['recurring_cents']
            : 0);
        $pastDueRows = $rows->where('state', 'past_due');
        $atRiskCents = $pastDueRows->sum(fn (array $row): int => (int) $row['billing']['recurring_cents']);
        $churnedCount = $rows->where('state', 'churned')->count();
        $conversion = $rows->count() > 0
            ? (int) round((($rows->count() - $freeCount) / $rows->count()) * 100)
            : 0;

        $queues = collect([
            'getting_started' => [
                'title' => 'Login / onboarding',
                'description' => 'New or dormant accounts that need a push back into PLYRCARD.',
                'concern' => 'getting_started',
            ],
            'billing' => [
                'title' => 'Payment needs attention',
                'description' => 'Failed, past-due, unpaid, or incomplete billing states.',
                'concern' => 'payment_attention',
            ],
            'profile' => [
                'title' => 'Profile under 60%',
                'description' => 'Profiles with visible gaps where coaches look first.',
                'concern' => 'finish_profile',
            ],
            'film' => [
                'title' => 'No film uploaded',
                'description' => 'Players with no current highlight or match film.',
                'concern' => 'send_film',
            ],
            'photo' => [
                'title' => 'No photo on file',
                'description' => 'Players missing a profile or action image.',
                'concern' => 'send_photo',
            ],
            'outreach' => [
                'title' => 'Outreach quiet',
                'description' => 'Paying athletes with no outreach or 30+ days of silence.',
                'concern' => 'start_outreach',
            ],
        ])->map(function (array $config, string $key) use ($rows): array {
            $items = $rows
                ->filter(fn (array $row): bool => collect($row['flags'])->contains(
                    fn (array $flag): bool => ($flag['key'] ?? '') === $key
                ))
                ->sortBy('health')
                ->values()
                ->all();

            return array_merge($config, [
                'key' => $key,
                'count' => count($items),
                'items' => $items,
            ]);
        })->values()->all();

        $serviceActivity = $this->serviceActivity();
        $paymentActivity = $this->paymentActivity();

        $newSignups = $rows
            ->sortByDesc('joined_sort')
            ->take(6)
            ->values()
            ->all();

        return [
            'athletes' => $rows->all(),
            'athlete_count' => $rows->count(),
            'attention_all' => $attention->all(),
            'follow_up_queues' => $queues,
            'plan_summary' => $planSummary,
            'billing_rows' => $billingRows,
            'billing_stats' => [
                'mrr' => $this->money($mrrCents),
                'active' => $activeSubscriptions,
                'conversion' => $conversion,
                'free' => $freeCount,
                'at_risk' => $this->money($atRiskCents),
                'past_due' => $pastDueRows->count(),
                'churned' => $churnedCount,
            ],
            'service_activity' => $serviceActivity,
            'payment_activity' => $paymentActivity,
            'new_signups' => $newSignups,
            'users_url' => $this->userIndexUrl(),
            'credit_requests_url' => $this->creditRequestIndexUrl(),
            'login_tracking_available' => $loginTracking,
        ];
    }

    protected function serviceActivity(): array
    {
        if (! class_exists(CreditServiceRequest::class) || ! Schema::hasTable('credit_service_requests')) {
            return ['items' => [], 'open' => 0, 'week' => 0];
        }

        try {
            $open = CreditServiceRequest::query()
                ->whereIn('status', ['submitted', 'reviewed', 'pending_information', 'in_progress'])
                ->count();

            $week = CreditServiceRequest::query()
                ->where('created_at', '>=', now()->subDays(7))
                ->count();

            $items = CreditServiceRequest::query()
                ->with('user:id,first_name,last_name,email,personal_email')
                ->latest('created_at')
                ->latest('id')
                ->limit(30)
                ->get()
                ->map(function (CreditServiceRequest $request): array {
                    return [
                        'id' => (int) $request->getKey(),
                        'player' => $this->userName($request->user),
                        'initials' => $request->user ? $this->initials($request->user) : 'P',
                        'service' => trim((string) $request->item_name) ?: 'Service request',
                        'quantity' => max(1, (int) $request->quantity),
                        'points' => max(0, (int) $request->points_spent),
                        'status' => $request->statusLabel(),
                        'status_key' => (string) $request->status,
                        'rush' => (string) $request->modifier === 'rush',
                        'when' => $request->created_at?->diffForHumans() ?? '',
                        'url' => $this->creditRequestUrl($request),
                        'user_id' => (int) $request->user_id,
                    ];
                })
                ->all();

            return [
                'items' => $items,
                'open' => $open,
                'week' => $week,
            ];
        } catch (Throwable) {
            return ['items' => [], 'open' => 0, 'week' => 0];
        }
    }

    protected function paymentActivity(): array
    {
        if (! class_exists(PaymentTransaction::class) || ! Schema::hasTable('payment_transactions')) {
            return ['items' => [], 'week_count' => 0, 'week_amount' => '$0'];
        }

        try {
            $successful = static fn (): Builder => PaymentTransaction::query()
                ->where('amount_cents', '>', 0)
                ->whereIn('status', ['paid', 'succeeded', 'success', 'completed', 'complete']);

            $weekQuery = $successful()
                ->where(function (Builder $query): void {
                    $query
                        ->where('paid_at', '>=', now()->subDays(7))
                        ->orWhere(function (Builder $fallback): void {
                            $fallback
                                ->whereNull('paid_at')
                                ->where('created_at', '>=', now()->subDays(7));
                        });
                });

            $weekCount = (clone $weekQuery)->count();
            $weekAmount = (int) (clone $weekQuery)->sum('amount_cents');

            $items = $successful()
                ->with('user:id,first_name,last_name,email,personal_email')
                ->latest('paid_at')
                ->latest('id')
                ->limit(30)
                ->get()
                ->map(function (PaymentTransaction $payment): array {
                    $source = trim((string) $payment->source_name);
                    if ($source === '') {
                        $source = trim((string) $payment->plan_key) !== ''
                            ? Str::headline((string) $payment->plan_key)
                            : 'PLYRCARD payment';
                    }

                    return [
                        'id' => (int) $payment->getKey(),
                        'player' => $this->userName($payment->user),
                        'initials' => $payment->user ? $this->initials($payment->user) : 'P',
                        'amount' => $this->money((int) $payment->amount_cents),
                        'source' => $source,
                        'provider' => Str::headline((string) ($payment->payment_provider ?: 'Payment')),
                        'status' => Str::headline((string) $payment->status),
                        'when' => ($payment->paid_at ?: $payment->created_at)?->diffForHumans() ?? '',
                        'user_id' => (int) ($payment->user_id ?? 0),
                        'url' => $payment->user ? $this->userUrl($payment->user) : null,
                    ];
                })
                ->all();

            return [
                'items' => $items,
                'week_count' => $weekCount,
                'week_amount' => $this->money($weekAmount),
            ];
        } catch (Throwable) {
            return ['items' => [], 'week_count' => 0, 'week_amount' => '$0'];
        }
    }

    protected function creditRequestIndexUrl(): ?string
    {
        try {
            return CreditServiceRequestResource::getUrl('index');
        } catch (Throwable) {
            return null;
        }
    }

    protected function creditRequestUrl(CreditServiceRequest $request): ?string
    {
        try {
            return CreditServiceRequestResource::getUrl('edit', ['record' => $request]);
        } catch (Throwable) {
            return null;
        }
    }

    protected function userIndexUrl(): ?string
    {
        try {
            return UserResource::getUrl('index');
        } catch (Throwable) {
            return null;
        }
    }

    protected function userName(?User $user): string
    {
        if (! $user) {
            return 'Unknown player';
        }

        $name = trim((string) (($user->first_name ?? '') . ' ' . ($user->last_name ?? '')));

        return $name !== '' ? $name : ((string) ($user->email ?? 'Player'));
    }
}
