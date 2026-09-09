<?php

namespace App\Filament\Widgets;

use App\Models\AdminSupportMessage;
use App\Models\CoachDatabaseEmailMessage;
use App\Models\User;
use App\Services\ProfileCompletionService;
use Carbon\CarbonInterface;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class SuperadminOverviewWidget extends Widget
{
    protected string $view = 'filament.widgets.superadmin-overview-widget';

    protected static ?int $sort = 1;

    protected int | string | array $columnSpan = 'full';

    /** @var array<int, string> */
    protected array $athleteRoleNames = [
        'Free',
        'My Journey',
        'Jumpstart',
        'Amplify',
        'Plyr',
        'PLYR+',
    ];

    /** @var array<int, string> */
    protected array $operatorRoleNames = [
        'Superadmin',
        'superadmin',
        'Super Admin',
        'Administrator',
        'Admin',
        'Club Manager',
    ];

    public static function canView(): bool
    {
        $user = auth()->user();

        return (bool) ($user
            && method_exists($user, 'hasRole')
            && (
                $user->hasRole('Superadmin')
                || $user->hasRole('superadmin')
                || $user->hasRole('Super Admin')
            ));
    }

    public function getViewData(): array
    {
        return Cache::remember(
            'superadmin:overview:v10.109.1',
            now()->addSeconds(60),
            fn (): array => $this->buildDashboardData(),
        );
    }

    protected function buildDashboardData(): array
    {
        $athletes = $this->athleteQuery()
            ->with(['roles', 'billingInformation'])
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();

        $athleteIds = $athletes->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $lastOutreach = $this->lastOutreachByAthlete($athleteIds);
        $profileService = app(ProfileCompletionService::class);

        $rows = $athletes
            ->map(function (User $user) use ($lastOutreach, $profileService): array {
                $billing = $user->billingInformation;
                $profileCompletion = $this->profileCompletion($profileService, $user);
                $isRecurring = $this->isCurrentRecurring($user);
                $flags = $this->followUpFlags(
                    user: $user,
                    profileCompletion: $profileCompletion,
                    isRecurring: $isRecurring,
                    lastOutreachAt: $lastOutreach[(int) $user->getKey()] ?? null,
                );

                $health = max(0, min(100, 100 - collect($flags)->sum('weight')));
                $topFlag = collect($flags)->sortByDesc('weight')->first();

                return [
                    'id' => (int) $user->getKey(),
                    'name' => trim((string) (($user->first_name ?? '') . ' ' . ($user->last_name ?? ''))) ?: (string) ($user->email ?? 'Athlete'),
                    'initials' => $this->initials($user),
                    'sport' => $this->sportLabel((string) ($user->sport ?? '')),
                    'sport_key' => trim((string) ($user->sport ?? '')),
                    'plan' => $this->planLabel($user),
                    'profile_completion' => $profileCompletion,
                    'is_recurring' => $isRecurring,
                    'billing_issue' => collect($flags)->contains(fn (array $flag): bool => $flag['key'] === 'billing'),
                    'flags' => $flags,
                    'top_flag' => $topFlag,
                    'health' => $health,
                    'reminder_concern' => $this->reminderConcernForFlag($topFlag),
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

        $sports = $rows
            ->groupBy(fn (array $row): string => $row['sport'] !== '' ? $row['sport'] : 'Not set')
            ->map(fn (Collection $items, string $sport): array => [
                'sport' => $sport,
                'count' => $items->count(),
            ])
            ->sortByDesc('count')
            ->values()
            ->map(function (array $row) use ($rows): array {
                $row['percent'] = $rows->count() > 0
                    ? max(3, (int) round(($row['count'] / $rows->count()) * 100))
                    : 0;

                return $row;
            })
            ->all();

        $payingCount = $rows->where('is_recurring', true)->count();
        $mrrCents = $athletes->sum(fn (User $user): int => $this->isCurrentRecurring($user)
            ? max(0, (int) ($user->billingInformation?->recurring_amount_cents ?? 0))
            : 0);

        $billingFollowUps = $attention->where('billing_issue', true)->count();

        return [
            'stats' => [
                'athletes' => [
                    'value' => $rows->count(),
                    'sub' => number_format($payingCount) . ' paying · ' . number_format(max(0, $rows->count() - $payingCount)) . ' free',
                ],
                'mrr' => [
                    'value' => $this->money($mrrCents),
                    'sub' => number_format($payingCount) . ' active monthly subscription' . ($payingCount === 1 ? '' : 's'),
                ],
                'follow_up' => [
                    'value' => $attention->count(),
                    'sub' => $billingFollowUps > 0
                        ? number_format($billingFollowUps) . ' billing-related'
                        : 'profile, media, billing, and outreach checks',
                ],
            ],
            'attention' => $attention->take(7)->all(),
            'attention_total' => $attention->count(),
            'sports' => $sports,
            'reminders' => $this->recentReminders(),
            'last_updated' => now()->format('g:i A'),
            'login_tracking_available' => Schema::hasColumn('users', 'last_login_at'),
        ];
    }

    protected function athleteQuery(): Builder
    {
        return User::query()
            ->where(function (Builder $query): void {
                $query->whereHas('roles', fn (Builder $roles): Builder => $roles->whereIn('name', $this->athleteRoleNames))
                    ->orWhere(function (Builder $legacy): void {
                        $legacy->whereDoesntHave('roles')
                            ->whereNotNull('sport')
                            ->where('sport', '<>', '');
                    });
            })
            ->whereDoesntHave('roles', fn (Builder $roles): Builder => $roles->whereIn('name', $this->operatorRoleNames));
    }

    /** @return array<int, CarbonInterface|string|null> */
    protected function lastOutreachByAthlete(array $athleteIds): array
    {
        if ($athleteIds === []
            || ! class_exists(CoachDatabaseEmailMessage::class)
            || ! Schema::hasTable('coach_database_email_messages')) {
            return [];
        }

        try {
            return CoachDatabaseEmailMessage::query()
                ->whereIn('athlete_user_id', $athleteIds)
                ->whereNotNull('sent_at')
                ->selectRaw('athlete_user_id, MAX(sent_at) as last_sent_at')
                ->groupBy('athlete_user_id')
                ->pluck('last_sent_at', 'athlete_user_id')
                ->all();
        } catch (Throwable) {
            return [];
        }
    }

    protected function profileCompletion(ProfileCompletionService $service, User $user): ?int
    {
        try {
            return max(0, min(100, (int) $service->calculate($user)));
        } catch (Throwable) {
            return null;
        }
    }

    /** @return array<int, array{key:string,label:string,weight:int,tone:string}> */
    protected function followUpFlags(User $user, ?int $profileCompletion, bool $isRecurring, mixed $lastOutreachAt): array
    {
        $flags = [];
        $billing = $user->billingInformation;

        $paymentStatus = Str::lower(trim((string) ($billing?->payment_status ?? '')));
        $subscriptionStatus = Str::lower(trim((string) ($billing?->subscription_status ?? '')));
        $billingProblem = in_array($paymentStatus, ['failed', 'past_due', 'past due', 'unpaid', 'declined', 'incomplete'], true)
            || in_array($subscriptionStatus, ['past_due', 'past due', 'unpaid', 'incomplete'], true);

        if ($billingProblem) {
            $flags[] = ['key' => 'billing', 'label' => 'Payment needs attention', 'weight' => 30, 'tone' => 'danger'];
        }

        if ($profileCompletion !== null && $profileCompletion < 60) {
            $flags[] = [
                'key' => 'profile',
                'label' => 'Profile ' . $profileCompletion . '%',
                'weight' => 20,
                'tone' => 'warning',
            ];
        }

        if (blank($user->player_image) && blank($user->action_image)) {
            $flags[] = ['key' => 'photo', 'label' => 'No photo', 'weight' => 10, 'tone' => 'warning'];
        }

        $videoUrls = is_array($user->featured_video_urls ?? null) ? array_filter($user->featured_video_urls) : [];
        if (blank($user->featured_video_url) && $videoUrls === [] && blank($user->yt_url)) {
            $flags[] = ['key' => 'film', 'label' => 'No film', 'weight' => 12, 'tone' => 'warning'];
        }

        if ($isRecurring && Schema::hasTable('coach_database_email_messages')) {
            if (blank($lastOutreachAt)) {
                $flags[] = ['key' => 'outreach', 'label' => 'No outreach yet', 'weight' => 16, 'tone' => 'warning'];
            } else {
                try {
                    $days = max(0, now()->diffInDays($lastOutreachAt));
                    if ($days > 30) {
                        $flags[] = [
                            'key' => 'outreach',
                            'label' => 'Quiet ' . ($days > 90 ? '90+' : $days) . 'd',
                            'weight' => 16,
                            'tone' => 'warning',
                        ];
                    }
                } catch (Throwable) {
                    // A malformed historical timestamp should not turn into a false alert.
                }
            }
        }

        return $flags;
    }


    protected function reminderConcernForFlag(?array $flag): string
    {
        return match ((string) ($flag['key'] ?? '')) {
            'billing' => 'payment_attention',
            'profile' => 'finish_profile',
            'photo' => 'send_photo',
            'film' => 'send_film',
            'outreach' => 'start_outreach',
            default => 'custom',
        };
    }

    protected function isCurrentRecurring(User $user): bool
    {
        $billing = $user->billingInformation;

        if (! $billing || (int) ($billing->recurring_amount_cents ?? 0) <= 0) {
            return false;
        }

        $billingCycle = Str::lower(trim((string) ($billing->billing_cycle ?? '')));
        if ($billingCycle !== '' && ! in_array($billingCycle, ['month', 'monthly'], true)) {
            return false;
        }

        $subscriptionStatus = Str::lower(trim((string) ($billing->subscription_status ?? '')));
        if (in_array($subscriptionStatus, ['canceled', 'cancelled', 'expired', 'inactive', 'ended'], true)) {
            return false;
        }

        $roles = $this->roleNames($user);
        $hasJourney = $roles->contains(fn (string $role): bool => strcasecmp($role, 'My Journey') === 0);

        if ($hasJourney) {
            return true;
        }

        return in_array($subscriptionStatus, ['active', 'trialing', 'trial', 'past_due', 'past due'], true)
            || Str::lower(trim((string) ($billing->payment_status ?? ''))) === 'paid';
    }

    protected function planLabel(User $user): string
    {
        $roles = $this->roleNames($user);
        $has = static fn (string $name): bool => $roles->contains(fn (string $role): bool => strcasecmp($role, $name) === 0);

        if ($has('Amplify')) {
            return $has('My Journey') ? 'My Journey + Amplify' : 'Amplify';
        }

        if ($has('Jumpstart')) {
            return $has('My Journey') ? 'My Journey + Jumpstart' : 'Jumpstart';
        }

        if ($has('My Journey')) {
            return 'My Journey';
        }

        if ($has('Free')) {
            return 'Free PLYR';
        }

        if ($has('PLYR+')) {
            return 'PLYR+';
        }

        if ($has('Plyr')) {
            return 'Plyr';
        }

        return $roles->first() ?: 'Athlete';
    }

    protected function roleNames(User $user): Collection
    {
        try {
            return $user->roles
                ->pluck('name')
                ->filter()
                ->map(fn ($role): string => trim((string) $role))
                ->values();
        } catch (Throwable) {
            return collect();
        }
    }

    protected function recentReminders(): array
    {
        if (! class_exists(AdminSupportMessage::class) || ! Schema::hasTable('admin_support_messages')) {
            return [];
        }

        try {
            $concerns = (array) config('plyrcard-admin-support.concerns', []);

            return AdminSupportMessage::query()
                ->whereNotNull('sent_at')
                ->where('sent_at', '>=', now()->subDays(7))
                ->with('user:id,first_name,last_name')
                ->latest('sent_at')
                ->limit(5)
                ->get()
                ->map(function (AdminSupportMessage $message) use ($concerns): array {
                    $userName = trim((string) (($message->user?->first_name ?? '') . ' ' . ($message->user?->last_name ?? '')))
                        ?: 'Athlete';
                    $template = is_array($concerns[$message->concern] ?? null) ? $concerns[$message->concern] : [];
                    $label = trim((string) ($template['label'] ?? '')) ?: Str::headline((string) $message->concern);

                    return [
                        'name' => $userName,
                        'label' => $label,
                        'when' => $message->sent_at?->diffForHumans() ?? '',
                        'status' => trim((string) ($message->email_status ?? 'sent')) ?: 'sent',
                        'success' => ! in_array(Str::lower((string) $message->email_status), ['failed', 'error', 'bounced'], true),
                    ];
                })
                ->all();
        } catch (Throwable) {
            return [];
        }
    }

    protected function sportLabel(string $sport): string
    {
        $sport = trim($sport);

        return $sport === '' ? 'Not set' : Str::of($sport)->replace('_', ' ')->headline()->toString();
    }

    protected function initials(User $user): string
    {
        $first = trim((string) ($user->first_name ?? ''));
        $last = trim((string) ($user->last_name ?? ''));
        $initials = Str::upper(Str::substr($first, 0, 1) . Str::substr($last, 0, 1));

        return $initials !== '' ? $initials : 'P';
    }

    protected function money(int $cents): string
    {
        $amount = $cents / 100;

        return '$' . (floor($amount) === $amount
            ? number_format($amount, 0)
            : number_format($amount, 2));
    }
}