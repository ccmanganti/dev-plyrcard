<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\CreditServiceRequests\CreditServiceRequestResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\AdminSupportMessage;
use App\Models\CreditPointTransaction;
use App\Models\CreditServiceRequest;
use App\Models\CoachDatabaseEmailMessage;
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

    public static function navigationMetrics(): array
    {
        try {
            $data = app(static::class)->getViewData();

            return [
                'athletes' => (int) ($data['athlete_count'] ?? 0),
                'followups' => count((array) ($data['attention_all'] ?? [])),
                'subscriptions' => (int) ($data['billing_stats']['active'] ?? 0),
                'amplify' => (int) ($data['amplify_credit_stats']['athletes'] ?? 0),
            ];
        } catch (Throwable) {
            return [
                'athletes' => 0,
                'followups' => 0,
                'subscriptions' => 0,
                'amplify' => 0,
            ];
        }
    }

    public function getViewData(): array
    {
        return Cache::remember(
            'superadmin:control-center:v3',
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
        $outreachStats = $this->outreachStatsByAthlete($athleteIds);
        $reminderHistory = $this->reminderHistoryByAthlete($athleteIds);
        $paymentSummary = $this->paymentSummaryByAthlete($athleteIds);

        $rows = $athletes
            ->map(function (User $user) use ($profileService, $loginTracking, $lastOutreach, $outreachStats, $reminderHistory, $paymentSummary): array {
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
                $userOutreach = (array) ($outreachStats[(int) $user->getKey()] ?? []);
                $userReminders = (array) ($reminderHistory[(int) $user->getKey()] ?? []);
                $userPayment = (array) ($paymentSummary[(int) $user->getKey()] ?? []);

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
                    'outreach_sent' => max(0, (int) ($userOutreach['sent'] ?? 0)),
                    'health' => $health,
                    'state' => $state,
                    'flags' => $flags,
                    'top_flag' => $topFlag,
                    'reminder_concern' => (string) ($topFlag['concern'] ?? $this->reminderConcernForFlag($topFlag)),
                    'reminders' => $userReminders,
                    'is_recurring' => $isRecurring,
                    'points_available' => max(0, (int) ($user->points_available ?? 0)),
                    'billing' => [
                        'plan_key' => trim((string) ($billing?->plan_key ?? '')),
                        'payment_status' => trim((string) ($billing?->payment_status ?? '')) ?: 'Not available',
                        'subscription_status' => trim((string) ($billing?->subscription_status ?? '')) ?: 'Not available',
                        'recurring_cents' => max(0, (int) ($billing?->recurring_amount_cents ?? 0)),
                        'setup_cents' => max(0, (int) ($billing?->setup_fee_cents ?? 0)),
                        'provider' => trim((string) ($billing?->payment_provider ?? '')) ?: 'Not set',
                        'started' => $billing?->created_at?->format('M j, Y') ?? $user->created_at?->format('M j, Y') ?? 'Unknown',
                        'last_payment' => (string) ($userPayment['when'] ?? 'No successful payment recorded'),
                        'last_payment_amount' => (string) ($userPayment['amount'] ?? '—'),
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
                'setup_cents' => $items->sum(fn (array $row): int => (int) ($row['billing']['setup_cents'] ?? 0)),
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
        $setupFeesCents = $rows->sum(fn (array $row): int => (int) ($row['billing']['setup_cents'] ?? 0));
        $conversion = $rows->count() > 0
            ? (int) round((($rows->count() - $freeCount) / $rows->count()) * 100)
            : 0;

        $amplifyCredits = $this->amplifyCreditsData($rows, $athleteIds);
        $coachOutreachSent = 0;

        if (class_exists(CoachDatabaseEmailMessage::class) && Schema::hasTable('coach_database_email_messages')) {
            try {
                $coachOutreachSent = CoachDatabaseEmailMessage::query()
                    ->whereIn('athlete_user_id', $athleteIds)
                    ->whereNotNull('sent_at')
                    ->count();
            } catch (Throwable) {
                $coachOutreachSent = 0;
            }
        }

        $queues = collect([
            'getting_started' => [
                'title' => 'Never logged in / dormant',
                'description' => 'New or dormant accounts that need a push back into PLYRCARD.',
                'concern' => 'getting_started',
            ],
            'billing' => [
                'title' => 'Payment failed',
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
                'title' => 'Quiet 30+ days',
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
        })->values();

        $idleCreditItems = collect($amplifyCredits['rows'] ?? [])
            ->filter(fn (array $row): bool => (bool) ($row['idle'] ?? false))
            ->map(function (array $creditRow) use ($rows): array {
                return $rows->firstWhere('id', $creditRow['id']) ?? [];
            })
            ->filter()
            ->values()
            ->all();

        $queues->splice(2, 0, [[
            'key' => 'amplify_credits',
            'title' => 'Amplify credits idle',
            'description' => 'Package value is still available and has not been used recently.',
            'concern' => 'amplify_credits',
            'count' => count($idleCreditItems),
            'items' => $idleCreditItems,
        ]]);

        $queues = $queues->values()->all();

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
                'setup_fees' => $this->money($setupFeesCents),
            ],
            'amplify_credit_rows' => $amplifyCredits['rows'],
            'amplify_credit_stats' => $amplifyCredits['stats'],
            'amplify_credit_services' => $amplifyCredits['services'],
            'coach_outreach_sent' => $coachOutreachSent,
            'reminders_this_week' => $this->recentReminderActivity($athleteIds),
            'service_activity' => $serviceActivity,
            'payment_activity' => $paymentActivity,
            'new_signups' => $newSignups,
            'users_url' => $this->userIndexUrl(),
            'credit_requests_url' => $this->creditRequestIndexUrl(),
            'login_tracking_available' => $loginTracking,
        ];
    }

    protected function outreachStatsByAthlete(array $athleteIds): array
    {
        if (
            $athleteIds === []
            || ! class_exists(CoachDatabaseEmailMessage::class)
            || ! Schema::hasTable('coach_database_email_messages')
        ) {
            return [];
        }

        try {
            return CoachDatabaseEmailMessage::query()
                ->whereIn('athlete_user_id', $athleteIds)
                ->whereNotNull('sent_at')
                ->selectRaw('athlete_user_id, COUNT(*) as sent_count, MAX(sent_at) as last_sent_at')
                ->groupBy('athlete_user_id')
                ->get()
                ->mapWithKeys(fn ($row): array => [
                    (int) $row->athlete_user_id => [
                        'sent' => (int) $row->sent_count,
                        'last_sent_at' => $row->last_sent_at,
                    ],
                ])
                ->all();
        } catch (Throwable) {
            return [];
        }
    }

    protected function reminderHistoryByAthlete(array $athleteIds): array
    {
        if (
            $athleteIds === []
            || ! class_exists(AdminSupportMessage::class)
            || ! Schema::hasTable('admin_support_messages')
        ) {
            return [];
        }

        try {
            return AdminSupportMessage::query()
                ->whereIn('user_id', $athleteIds)
                ->whereNotNull('sent_at')
                ->latest('sent_at')
                ->get()
                ->groupBy('user_id')
                ->map(function (Collection $messages): array {
                    return $messages
                        ->groupBy(fn (AdminSupportMessage $message): string => (string) ($message->concern ?: 'custom'))
                        ->map(function (Collection $concernMessages): array {
                            /** @var AdminSupportMessage|null $latest */
                            $latest = $concernMessages->first();

                            return [
                                'count' => $concernMessages->count(),
                                'week_count' => $concernMessages->filter(
                                    fn (AdminSupportMessage $message): bool => (bool) $message->sent_at?->gte(now()->subDays(7))
                                )->count(),
                                'when' => $latest?->sent_at?->diffForHumans(),
                                'sent_at' => $latest?->sent_at?->toIso8601String(),
                                'channel' => trim((string) ($latest?->channel ?? '')),
                            ];
                        })
                        ->all();
                })
                ->mapWithKeys(fn ($value, $userId): array => [(int) $userId => $value])
                ->all();
        } catch (Throwable) {
            return [];
        }
    }

    protected function recentReminderActivity(array $athleteIds): array
    {
        if (
            $athleteIds === []
            || ! class_exists(AdminSupportMessage::class)
            || ! Schema::hasTable('admin_support_messages')
        ) {
            return [];
        }

        try {
            return AdminSupportMessage::query()
                ->with('user:id,first_name,last_name,email,personal_email')
                ->whereIn('user_id', $athleteIds)
                ->whereNotNull('sent_at')
                ->where('sent_at', '>=', now()->subDays(7))
                ->latest('sent_at')
                ->limit(8)
                ->get()
                ->map(function (AdminSupportMessage $message): array {
                    $concern = (string) ($message->concern ?: 'custom');

                    return [
                        'player' => $this->userName($message->user),
                        'initials' => $message->user ? $this->initials($message->user) : 'P',
                        'label' => (string) config(
                            'plyrcard-admin-support.concerns.' . $concern . '.label',
                            Str::headline($concern)
                        ),
                        'when' => $message->sent_at?->diffForHumans() ?? '',
                        'channel' => trim((string) ($message->channel ?? '')),
                        'email_status' => trim((string) ($message->email_status ?? '')),
                        'sms_status' => trim((string) ($message->sms_status ?? '')),
                    ];
                })
                ->all();
        } catch (Throwable) {
            return [];
        }
    }

    protected function paymentSummaryByAthlete(array $athleteIds): array
    {
        if (
            $athleteIds === []
            || ! class_exists(PaymentTransaction::class)
            || ! Schema::hasTable('payment_transactions')
        ) {
            return [];
        }

        try {
            return PaymentTransaction::query()
                ->whereIn('user_id', $athleteIds)
                ->where('amount_cents', '>', 0)
                ->whereIn('status', ['paid', 'succeeded', 'success', 'completed', 'complete'])
                ->orderByDesc('paid_at')
                ->orderByDesc('id')
                ->get()
                ->groupBy('user_id')
                ->map(function (Collection $payments): array {
                    /** @var PaymentTransaction|null $latest */
                    $latest = $payments->first();
                    $at = $latest?->paid_at ?: $latest?->created_at;

                    return [
                        'when' => $at?->diffForHumans() ?? 'No successful payment recorded',
                        'amount' => $latest ? $this->money((int) $latest->amount_cents) : '—',
                    ];
                })
                ->mapWithKeys(fn ($value, $userId): array => [(int) $userId => $value])
                ->all();
        } catch (Throwable) {
            return [];
        }
    }

    protected function amplifyCreditsData(Collection $rows, array $athleteIds): array
    {
        $serviceDefinitions = [
            'outreach' => 'Coach outreach campaigns',
            'reel' => 'Highlight reels',
            'graphic' => 'Custom graphics',
            'production_hour' => 'Rep hours',
        ];

        $emptyServices = collect($serviceDefinitions)
            ->map(fn (string $label, string $key): array => [
                'key' => $key,
                'label' => $label,
                'requests' => 0,
                'points' => 0,
            ])
            ->values()
            ->all();

        $packagePlanRows = $rows->filter(
            fn (array $row): bool => in_array($row['plan_key'] ?? '', ['amplify', 'jumpstart'], true)
        );

        if ($athleteIds === [] || ! Schema::hasTable('credit_point_transactions')) {
            $fallbackRows = $packagePlanRows
                ->map(function (array $row): array {
                    return [
                        'id' => $row['id'],
                        'name' => $row['name'],
                        'initials' => $row['initials'],
                        'sport' => $row['sport'],
                        'package' => $row['plan'],
                        'package_key' => $row['plan_key'],
                        'granted_points' => 0,
                        'points_available' => (int) ($row['points_available'] ?? 0),
                        'points_redeemed' => 0,
                        'usage_percent' => null,
                        'usage' => [
                            'outreach' => ['qty' => 0, 'points' => 0],
                            'reel' => ['qty' => 0, 'points' => 0],
                            'graphic' => ['qty' => 0, 'points' => 0],
                            'production_hour' => ['qty' => 0, 'points' => 0],
                        ],
                        'last_activity' => 'No service activity',
                        'idle' => (int) ($row['points_available'] ?? 0) > 0,
                        'user_url' => $row['user_url'],
                    ];
                })
                ->values()
                ->all();

            return [
                'rows' => $fallbackRows,
                'stats' => [
                    'athletes' => count($fallbackRows),
                    'amplify' => collect($fallbackRows)->where('package_key', 'amplify')->count(),
                    'jumpstart' => collect($fallbackRows)->where('package_key', 'jumpstart')->count(),
                    'idle' => collect($fallbackRows)->where('idle', true)->count(),
                    'points_granted' => 0,
                    'points_available' => collect($fallbackRows)->sum('points_available'),
                    'points_redeemed' => 0,
                ],
                'services' => $emptyServices,
            ];
        }

        try {
            $transactions = CreditPointTransaction::query()
                ->whereIn('user_id', $athleteIds)
                ->whereIn('source_type', ['package_purchase', 'service_request'])
                ->get();

            $packageGrants = $transactions
                ->filter(fn (CreditPointTransaction $row): bool => $row->type === 'grant' && $row->source_type === 'package_purchase')
                ->groupBy('user_id');

            $packageIds = $packagePlanRows
                ->pluck('id')
                ->merge($packageGrants->keys()->map(fn ($id): int => (int) $id))
                ->unique()
                ->values();

            $requests = collect();

            if (Schema::hasTable('credit_service_requests')) {
                $requests = CreditServiceRequest::query()
                    ->whereIn('user_id', $packageIds->all())
                    ->whereIn('item_key', array_keys($serviceDefinitions))
                    ->get();
            }

            $requestsByUser = $requests->groupBy('user_id');

            $creditRows = $rows
                ->filter(fn (array $row): bool => $packageIds->contains((int) $row['id']))
                ->map(function (array $row) use ($packageGrants, $requestsByUser, $serviceDefinitions): array {
                    $userId = (int) $row['id'];
                    $grants = collect($packageGrants->get($userId, collect()));
                    $latestGrant = $grants->sortByDesc('id')->first();
                    $meta = (array) ($latestGrant?->meta ?? []);
                    $packageKey = Str::lower(trim((string) ($meta['package'] ?? '')));

                    if (! in_array($packageKey, ['amplify', 'jumpstart'], true)) {
                        $packageKey = in_array($row['plan_key'] ?? '', ['amplify', 'jumpstart'], true)
                            ? (string) $row['plan_key']
                            : '';
                    }

                    $packageLabel = match ($packageKey) {
                        'amplify' => 'Amplify',
                        'jumpstart' => 'Jumpstart',
                        default => $row['plan'] ?: 'Package',
                    };

                    $grantedPoints = (int) $grants->sum(fn (CreditPointTransaction $grant): int => max(0, (int) $grant->points));
                    $userRequests = collect($requestsByUser->get($userId, collect()));

                    $usage = [];
                    $pointsRedeemed = 0;

                    foreach ($serviceDefinitions as $key => $label) {
                        $serviceRequests = $userRequests->where('item_key', $key);
                        $qty = (int) $serviceRequests->sum(fn (CreditServiceRequest $request): int => max(1, (int) $request->quantity));
                        $points = (int) $serviceRequests->sum(
                            fn (CreditServiceRequest $request): int => max(0, (int) $request->points_spent - (int) $request->credits_returned)
                        );

                        $usage[$key] = [
                            'qty' => $qty,
                            'points' => $points,
                        ];

                        $pointsRedeemed += $points;
                    }

                    $lastRequest = $userRequests->sortByDesc('created_at')->first();
                    $lastActivityDays = null;
                    $lastActivity = 'No service activity';

                    if ($lastRequest?->created_at) {
                        $lastActivityDays = max(0, now()->diffInDays($lastRequest->created_at));
                        $lastActivity = $lastRequest->created_at->diffForHumans();
                    }

                    $available = max(0, (int) ($row['points_available'] ?? 0));
                    $usagePercent = $grantedPoints > 0
                        ? min(100, (int) round(($pointsRedeemed / $grantedPoints) * 100))
                        : null;

                    return [
                        'id' => $userId,
                        'name' => $row['name'],
                        'initials' => $row['initials'],
                        'sport' => $row['sport'],
                        'package' => $packageLabel,
                        'package_key' => $packageKey,
                        'granted_points' => $grantedPoints,
                        'points_available' => $available,
                        'points_redeemed' => $pointsRedeemed,
                        'usage_percent' => $usagePercent,
                        'usage' => $usage,
                        'last_activity' => $lastActivity,
                        'idle' => $available > 0 && ($lastActivityDays === null || $lastActivityDays > 30),
                        'user_url' => $row['user_url'],
                    ];
                })
                ->sortBy([
                    ['idle', 'desc'],
                    ['points_available', 'desc'],
                    ['name', 'asc'],
                ])
                ->values();

            $serviceTotals = collect($serviceDefinitions)
                ->map(function (string $label, string $key) use ($creditRows): array {
                    return [
                        'key' => $key,
                        'label' => $label,
                        'requests' => $creditRows->sum(fn (array $row): int => (int) ($row['usage'][$key]['qty'] ?? 0)),
                        'points' => $creditRows->sum(fn (array $row): int => (int) ($row['usage'][$key]['points'] ?? 0)),
                    ];
                })
                ->values()
                ->all();

            return [
                'rows' => $creditRows->all(),
                'stats' => [
                    'athletes' => $creditRows->count(),
                    'amplify' => $creditRows->where('package_key', 'amplify')->count(),
                    'jumpstart' => $creditRows->where('package_key', 'jumpstart')->count(),
                    'idle' => $creditRows->where('idle', true)->count(),
                    'points_granted' => $creditRows->sum('granted_points'),
                    'points_available' => $creditRows->sum('points_available'),
                    'points_redeemed' => $creditRows->sum('points_redeemed'),
                ],
                'services' => $serviceTotals,
            ];
        } catch (Throwable) {
            return [
                'rows' => [],
                'stats' => [
                    'athletes' => 0,
                    'amplify' => 0,
                    'jumpstart' => 0,
                    'idle' => 0,
                    'points_granted' => 0,
                    'points_available' => 0,
                    'points_redeemed' => 0,
                ],
                'services' => $emptyServices,
            ];
        }
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
