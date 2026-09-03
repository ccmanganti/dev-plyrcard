<?php

namespace App\Services;

use App\Models\BillingInformation;
use App\Models\PaymentTransaction;
use App\Models\Schedule;
use App\Models\School;
use App\Models\User;
use App\Models\Website;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class LockerRoomDataService
{
    public function __construct(
        protected BillingAccountService $billingAccount,
    ) {
    }

    public function snapshot(User $user): array
    {
        $user->loadMissing(['roles', 'school', 'club', 'league', 'nationalTeam']);

        $billing = BillingInformation::query()
            ->where('user_id', $user->id)
            ->latest('updated_at')
            ->first();

        if (filled($user->ghl_subscriber_contact_id)) {
            $billing ??= BillingInformation::query()->firstOrCreate(
                ['user_id' => $user->id],
                [
                    'billing_name' => trim((string) ($user->first_name . ' ' . $user->last_name)),
                    'billing_email' => $user->email,
                    'billing_phone' => $user->phone,
                    'billing_address_1' => $user->street,
                    'billing_city' => $user->city,
                    'billing_state' => $user->state,
                    'billing_country' => $user->country ?: 'US',
                    'currency' => 'USD',
                    'ghl_location_id' => config('ghl.location_id'),
                ],
            );

            $this->billingAccount->syncSubscriberAccount($user, $billing);
            $billing->refresh();
        }

        $website = Website::query()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->latest('updated_at')
            ->first();

        $latestPaymentTransaction = null;
        try {
            if (class_exists(PaymentTransaction::class)) {
                $latestPaymentTransaction = PaymentTransaction::query()
                    ->where('user_id', $user->id)
                    ->orderByRaw('paid_at IS NULL')
                    ->latest('paid_at')
                    ->latest('id')
                    ->first();
            }
        } catch (\Throwable) {
            // Keep Locker Room usable when the optional payment ledger is not available yet.
            $latestPaymentTransaction = null;
        }

        $planKey = $this->planKey($user, $billing);
        $isFree = $planKey === 'free';
        $isPremium = $planKey === 'my-journey';
        $workspaceReady = $this->workspaceReady($user);

        return [
            'user' => $this->profilePayload($user, $isPremium),
            'plan' => [
                'key' => $planKey,
                'label' => $planKey === 'my-journey' ? 'My Journey' : 'Free',
                'is_free' => $isFree,
                'is_premium' => $isPremium,
                'workspace_ready' => $workspaceReady,
                'show_preparing' => $isPremium && ! $workspaceReady,
                'jumpstart_active' => $this->hasRole($user, 'Jumpstart'),
                'amplify_active' => $this->hasRole($user, 'Amplify'),
            ],
            'dashboard' => $this->dashboardPayload($user),
            'photos' => $this->photosPayload($user),
            'schedule' => $this->schedulePayload($user),
            'settings' => $this->settingsPayload($user, $website, $isPremium),
            'billing' => $this->billingPayload($billing, $user, $latestPaymentTransaction),
            'website' => $this->websitePayload($website),
            'plans' => $this->plans($planKey, $user),
            'integrations' => [
                'support_form_url' => 'https://systems.plyrcard.com/widget/form/HDaBy0CDwdO7Fw54wi1K',
                'book_call_url' => 'https://systems.plyrcard.com/widget/booking/SvuQy1svAyETQ5Q9px9l',
            ],
        ];
    }

    protected function planKey(User $user, ?BillingInformation $billing): string
    {
        // PLYRCARD roles are authoritative for product access/current tier.
        // Billing subscription state is verified separately against the payer
        // contact and must not accidentally promote/downgrade application access.
        // Amplify is a one-time service entitlement, not a plan tier.
        if ($this->hasRole($user, 'My Journey')) {
            return 'my-journey';
        }

        if ($this->hasRole($user, 'Free')) {
            return 'free';
        }

        // Compatibility fallback for legacy users that do not yet have a tier role.
        $billingPlan = strtolower(trim((string) ($billing?->plan_key ?? '')));
        if ($billingPlan === 'amplify') {
            return 'my-journey';
        }
        if (in_array($billingPlan, ['my-journey', 'my_journey'], true)) {
            return 'my-journey';
        }

        return 'free';
    }

    protected function hasRole(User $user, string $role): bool
    {
        try {
            if (method_exists($user, 'hasRole') && $user->hasRole($role)) {
                return true;
            }
        } catch (\Throwable) {
        }

        return $user->getRoleNames()
            ->contains(fn ($name): bool => strcasecmp(trim((string) $name), $role) === 0);
    }

    protected function workspaceReady(User $user): bool
    {
        $values = [
            method_exists($user, 'getRawOriginal') ? $user->getRawOriginal('ghl_api_key') : $user->ghl_api_key,
            method_exists($user, 'getRawOriginal') ? $user->getRawOriginal('ghl_location_id') : $user->ghl_location_id,
        ];

        $missing = ['', 'null', 'none', 'pending', 'not set', 'n/a'];

        foreach ($values as $value) {
            if (in_array(strtolower(trim((string) $value)), $missing, true)) {
                return false;
            }
        }

        return true;
    }

    protected function profilePayload(User $user, bool $isPremium): array
    {
        $positions = is_array($user->position) ? array_values($user->position) : array_values(array_filter([(string) $user->position]));

        return [
            'id' => $user->id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'full_name' => trim($user->first_name . ' ' . $user->last_name),
            'email' => $user->email,
            'personal_email' => $user->personal_email,
            'phone' => $user->phone,
            'street' => $user->street,
            'city' => $user->city,
            'state' => $user->state,
            'country' => $user->country,
            'sport' => $user->sport,
            'position' => $positions,
            'jersey_number' => $user->jersey_number,
            'year' => $user->year,
            'gender' => $user->gender,
            'birth' => $this->dateInputValue($user->birth),
            'gpa' => $user->gpa,
            'height' => $user->height,
            'weight' => $user->weight,
            'dominant_foot' => $user->dominant_foot,
            'ncaa_field_id' => $user->ncaa_field_id,
            'max_speed' => $user->max_speed,
            'player_bio' => $user->player_bio,
            'academic_accolades' => $user->academic_accolades,
            'sports_accolades' => $user->sports_accolades,
            'ig_handle' => $isPremium ? $user->ig_handle : null,
            'x_handle' => $isPremium ? $user->x_handle : null,
            'yt_url' => $isPremium ? $user->yt_url : null,
            'featured_video_url' => $isPremium ? $user->featured_video_url : null,
            'featured_video_urls' => $isPremium ? $user->featured_video_urls : null,
            'parent' => $user->parent,
            'parent_email' => $user->parent_email,
            'parent_phone' => $user->parent_phone,
            'sec_parent' => $user->sec_parent,
            'sec_parent_email' => $user->sec_parent_email,
            'sec_parent_phone' => $user->sec_parent_phone,
            'club_coach' => $user->club_coach,
            'club_coach_email' => $user->club_coach_email,
            'club_coach_phone' => $user->club_coach_phone,
            'natl_coach' => $user->natl_coach,
            'natl_coach_email' => $user->natl_coach_email,
            'natl_coach_phone' => $user->natl_coach_phone,
            'tech_trainer' => $user->tech_trainer,
            'tech_trainer_email' => $user->tech_trainer_email,
            'tech_trainer_phone' => $user->tech_trainer_phone,
            'snc_trainer' => $user->snc_trainer,
            'snc_trainer_email' => $user->snc_trainer_email,
            'snc_trainer_phone' => $user->snc_trainer_phone,
            'school_id' => $user->school_id,
            'school' => $user->school?->name,
            'league_id' => $user->league_id,
            'league' => $user->league?->name,
            'club_id' => $user->club_id,
            'club' => $user->club?->name,
            'club_league_id' => $user->club_league_id,
            'team_name' => $user->team_name,
            'national_team_id' => $user->national_team_id,
            'national_team' => $user->nationalTeam?->name,
            'national_team_period' => $user->national_team_period,
            'pro_club_name' => $user->pro_club_name,
            'pro_club_logo' => $user->pro_club_logo,
            'pro_club_logo_url' => $this->storageUrl($user->pro_club_logo),
            'profile_image_url' => $this->storageUrl($user->player_image ?: $user->plyrcard_image),
            'player_image_url' => $this->storageUrl($user->player_image),
            'plyrcard_image_url' => $this->storageUrl($user->plyrcard_image),
            'action_image_url' => $this->storageUrl($user->action_image),
            'national_team_image_url' => $this->storageUrl($user->national_team_image),
            'mobile_hero_image_url' => $this->storageUrl($user->mobile_hero_image),
            'youtube_thumbnail_url' => $this->storageUrl($user->youtube_thumbnail),
            'raw_player_images' => collect(is_array($user->raw_player_images) ? $user->raw_player_images : [])
                ->filter(fn ($path): bool => filled($path))
                ->map(fn ($path): array => [
                    'path' => (string) $path,
                    'url' => $this->storageUrl((string) $path),
                ])
                ->values()
                ->all(),
            'plyrcard_images' => collect(is_array($user->plyrcard_images ?? null) ? $user->plyrcard_images : [])
                ->filter(fn ($path): bool => filled($path))
                ->map(fn ($path): array => [
                    'path' => (string) $path,
                    'url' => $this->storageUrl((string) $path),
                ])
                ->values()
                ->all(),
            'profile_completion' => $this->profileCompletion($user),
            'sport_options' => $this->sportOptions(),
            'position_options' => $this->positionOptions(),
            'age_group_options' => collect(config('plyrcard.age_groups', [
                'u13' => 'U13', 'u14' => 'U14', 'u15' => 'U15', 'u16' => 'U16',
                'u17' => 'U17', 'u18' => 'U18', 'u19' => 'U19',
            ]))->values()->mapWithKeys(fn ($label) => [(string) $label => (string) $label])->all(),
        ];
    }

    protected function photosPayload(User $user): array
    {
        $player = collect(is_array($user->raw_player_images ?? null) ? $user->raw_player_images : [])
            ->map(fn ($path): string => trim((string) $path))
            ->filter()->unique()->values()
            ->map(fn (string $path, int $index): array => [
                'index' => $index,
                'source' => 'player',
                'field' => null,
                'path' => $path,
                'url' => $this->storageUrl($path),
                'name' => 'Player photo',
            ])->all();

        $websiteFields = [
            'plyrcard_image', 'player_image', 'action_image',
            'national_team_image', 'mobile_hero_image', 'youtube_thumbnail',
        ];

        $plyrcard = collect($websiteFields)
            ->map(function (string $field) use ($user): ?array {
                $path = trim((string) ($user->{$field} ?? ''));
                if ($path === '') return null;
                return [
                    'index' => 0,
                    'source' => 'field',
                    'field' => $field,
                    'path' => $path,
                    'url' => $this->storageUrl($path),
                    'name' => 'PLYRCARD photo',
                ];
            })
            ->filter()
            ->concat(
                collect(is_array($user->plyrcard_images ?? null) ? $user->plyrcard_images : [])
                    ->map(fn ($path): string => trim((string) $path))
                    ->filter()->unique()->values()
                    ->map(fn (string $path, int $index): array => [
                        'index' => $index,
                        'source' => 'additional',
                        'field' => null,
                        'path' => $path,
                        'url' => $this->storageUrl($path),
                        'name' => 'PLYRCARD photo',
                    ])
            )
            ->unique('url')->values()->all();

        $canManagePlyrcard = false;
        try {
            $canManagePlyrcard = method_exists($user, 'isSuperadminOrImpersonating')
                ? (bool) $user->isSuperadminOrImpersonating()
                : (method_exists($user, 'hasRole') && ($user->hasRole('superadmin') || $user->hasRole('Superadmin')));
        } catch (\Throwable) {
            $canManagePlyrcard = false;
        }

        return [
            'player' => $player,
            'plyrcard' => $plyrcard,
            'can_manage_player' => true,
            'can_manage_plyrcard' => $canManagePlyrcard,
            'player_max' => 20,
            'plyrcard_max' => 30,
        ];
    }

    protected function dashboardPayload(User $user): array
    {
        $tracking = [];
        $remoteStats = [];

        try {
            $tracking = app(LocalRecruitingTrackingService::class)->dashboardStats($user);
        } catch (\Throwable) {
            $tracking = [];
        }

        // Match the Admin Dashboard source layering: local tracking is authoritative,
        // while cached dashboard sync values may contribute metrics that are not stored
        // as local redirect/open events (for example coach replies).
        try {
            $summary = Cache::get($this->dashboardActivitySummaryCacheKey($user), []);
            $remoteStats = is_array($summary) && is_array($summary['stats'] ?? null)
                ? $summary['stats']
                : [];
        } catch (\Throwable) {
            $remoteStats = [];
        }

        $number = static fn (...$values): int => max(array_map(static fn ($value): int => is_numeric($value) ? (int) $value : 0, $values));

        $profileViews = $number(
            $tracking['profile_views'] ?? 0,
            $tracking['view_profile_total'] ?? 0,
            $tracking['profile_view_school_click_count'] ?? 0,
            $remoteStats['profile_views'] ?? 0,
            $remoteStats['view_profile_total'] ?? 0
        );

        $emailsSent = $number(
            $tracking['emails_sent'] ?? 0,
            $tracking['email_sent_count'] ?? 0,
            $remoteStats['emails_sent'] ?? 0,
            $remoteStats['email_sent_count'] ?? 0,
            $remoteStats['Sent email count'] ?? 0,
            (int) ($remoteStats['personal_emails_sent'] ?? 0) + (int) ($remoteStats['campaigns_sent'] ?? 0),
            $user->total_emails_sent ?? 0
        );

        // v10.51: use the exact same source-of-truth rule as Coach Database.
        // When LocalRecruitingTrackingService has attributed engagement rows, those
        // rows are authoritative for BOTH the dashboard card and its drill-down.
        // Never max them against cached/legacy counters; doing so is what caused
        // Locker Room to show inflated totals while Coach Database showed the
        // correct smaller number.
        $engagement = $this->localCoachEngagementSnapshot($user, $tracking);
        $instagramClicks = (int) ($engagement['platform_counts']['instagram'] ?? 0);
        $youtubeClicks = (int) ($engagement['platform_counts']['youtube'] ?? 0);
        $xClicks = (int) ($engagement['platform_counts']['x'] ?? 0);
        $socialClicks = (int) ($engagement['total'] ?? ($instagramClicks + $youtubeClicks + $xClicks));

        $profileUniqueContacts = $number(
            $tracking['profile_view_unique_contact_count'] ?? 0,
            $tracking['unique_profile_view_contacts'] ?? 0,
            $tracking['unique_profile_views'] ?? 0,
            $tracking['unique_profile_view_count'] ?? 0,
            $remoteStats['profile_view_unique_contact_count'] ?? 0,
            $remoteStats['unique_profile_view_contacts'] ?? 0,
            $remoteStats['unique_profile_views'] ?? 0,
            $remoteStats['unique_profile_view_count'] ?? 0
        );
        $profileUniqueSchools = $number(
            $tracking['profile_view_unique_school_count'] ?? 0,
            $tracking['schools_with_profile_views'] ?? 0,
            $tracking['schools_with_clicks'] ?? 0,
            $remoteStats['profile_view_unique_school_count'] ?? 0,
            $remoteStats['schools_with_profile_views'] ?? 0,
            $remoteStats['schools_with_clicks'] ?? 0
        );

        // Match the Coach Database dashboard's Favorites card.
        // This is a local PLYRCARD relationship, so no external request is needed.
        try {
            $favorites = (int) $user->favoriteSchoolRecords()->count();
        } catch (\Throwable) {
            $favorites = 0;
        }

        $schoolsEngaged = $number(
            $tracking['schools_with_clicks'] ?? 0,
            $tracking['schools_with_profile_views'] ?? 0,
            $tracking['profile_view_unique_school_count'] ?? 0,
            $remoteStats['schools_with_clicks'] ?? 0,
            $remoteStats['schools_with_profile_views'] ?? 0
        );

        $upcoming = Schedule::query()
            ->where(function ($query) use ($user): void {
                $query->where('created_by_user_id', $user->id)
                    ->orWhereHas('users', fn ($q) => $q->where('users.id', $user->id));
            })
            ->where('status', 'upcoming')
            ->whereDate('game_date', '>=', now()->toDateString())
            ->orderBy('game_date')
            ->orderBy('game_time')
            ->first();

        return [
            'profile_completion' => $this->profileCompletion($user),
            'stats' => [
                'profile_views' => $profileViews,
                'profile_unique_contacts' => $profileUniqueContacts,
                'profile_unique_schools' => $profileUniqueSchools,
                'favorites' => $favorites,
                'emails_sent' => $emailsSent,
                'email_clicks' => $number(
                    $tracking['email_click_count'] ?? 0,
                    $tracking['email_clicks'] ?? 0,
                    $remoteStats['email_click_count'] ?? 0,
                    $remoteStats['email_clicks'] ?? 0,
                    $remoteStats['Click count'] ?? 0
                ),
                'social_clicks' => $socialClicks,
                'coach_engagement' => $socialClicks,
                'instagram_clicks' => $instagramClicks,
                'youtube_clicks' => $youtubeClicks,
                'x_clicks' => $xClicks,
                'schools_engaged' => $schoolsEngaged,
                'email_opens' => $number(
                    $tracking['email_opens'] ?? 0,
                    $tracking['email_open_count'] ?? 0,
                    $remoteStats['email_opens'] ?? 0,
                    $remoteStats['email_open_count'] ?? 0,
                    $remoteStats['Open count'] ?? 0
                ),
                'coach_replies' => $number($tracking['coach_replies'] ?? 0, $remoteStats['coach_replies'] ?? 0),
            ],
            'next_schedule' => $upcoming ? $this->scheduleRow($upcoming, $user) : null,
        ];
    }

    /**
     * Return Coach Engagement using the exact LocalRecruitingTrackingService rows
     * used by Coach Database. This mirrors InteractsWithCoachDatabase:
     * - coachEngagementRows() wins when rows exist;
     * - Instagram/YouTube/X are normalized per row;
     * - each row's aggregate count is summed exactly once;
     * - legacy cached counters are used only when there are no authoritative rows.
     */
    protected function localCoachEngagementSnapshot(User $user, array $dashboardStats = []): array
    {
        $rawRows = [];

        try {
            $rawRows = app(LocalRecruitingTrackingService::class)->coachEngagementRows($user);
        } catch (\Throwable) {
            $rawRows = [];
        }

        $rows = collect(is_array($rawRows) ? $rawRows : [])
            ->filter(fn ($row): bool => is_array($row))
            ->values();

        if ($rows->isNotEmpty()) {
            $platformCounts = [
                'instagram' => $this->dashboardSocialClickTotal($rows, 'instagram'),
                'youtube' => $this->dashboardSocialClickTotal($rows, 'youtube'),
                'x' => $this->dashboardSocialClickTotal($rows, 'x'),
            ];

            return [
                'total' => array_sum($platformCounts),
                'platform_counts' => $platformCounts,
                'unique_coaches' => $rows->pluck('coach_id')->filter()->unique()->count(),
                'unique_schools' => $rows->map(function (array $row): string {
                    return trim((string) ($row['school_key'] ?? $row['school_id'] ?? $row['school_business_id'] ?? $row['business_id'] ?? $row['school'] ?? ''));
                })->filter()->unique()->count(),
                'rows' => $this->formatCoachEngagementRows($rows),
                'authoritative_rows' => true,
            ];
        }

        // Same fallback concept as Coach Database when no local rows exist. Keep it
        // local-only here; remote snapshot counters are deliberately not maxed in.
        $instagram = max(
            (int) ($dashboardStats['instagram_click_count'] ?? 0),
            (int) ($dashboardStats['instagram_clicks'] ?? 0),
        );
        $youtube = max(
            (int) ($dashboardStats['youtube_click_count'] ?? 0),
            (int) ($dashboardStats['youtube_clicks'] ?? 0),
        );
        $x = max(
            (int) ($dashboardStats['x_click_count'] ?? 0),
            (int) ($dashboardStats['twitter_click_count'] ?? 0),
            (int) ($dashboardStats['x_clicks'] ?? 0),
            (int) ($dashboardStats['twitter_clicks'] ?? 0),
        );

        return [
            'total' => $instagram + $youtube + $x,
            'platform_counts' => ['instagram' => $instagram, 'youtube' => $youtube, 'x' => $x],
            'unique_coaches' => (int) ($dashboardStats['engagement_unique_coaches'] ?? $dashboardStats['unique_link_click_contacts'] ?? 0),
            'unique_schools' => (int) ($dashboardStats['engagement_unique_schools'] ?? $dashboardStats['schools_with_clicks'] ?? 0),
            'rows' => [],
            'authoritative_rows' => false,
        ];
    }

    protected function normalizeDashboardSocialPlatform(mixed $platform = null, array $row = []): string
    {
        $raw = strtolower(trim((string) $platform));
        $haystack = strtolower(trim(implode(' ', array_filter(array_map(
            fn ($value): string => is_scalar($value) ? (string) $value : '',
            [
                $raw,
                $row['platform_key'] ?? null,
                $row['platform'] ?? null,
                $row['rc_platform'] ?? null,
                $row['utm_content'] ?? null,
                $row['source'] ?? null,
                $row['type'] ?? null,
                $row['event_type'] ?? null,
                $row['url'] ?? null,
                $row['destination_url'] ?? null,
                $row['href'] ?? null,
                $row['link'] ?? null,
                $row['last_clicked_url'] ?? null,
            ]
        )))));

        return match (true) {
            str_contains($haystack, 'instagram'),
            preg_match('/(^|[^a-z0-9])ig([^a-z0-9]|$)/', $haystack) === 1 => 'instagram',
            str_contains($haystack, 'youtube'),
            str_contains($haystack, 'youtu.be'),
            preg_match('/(^|[^a-z0-9])yt([^a-z0-9]|$)/', $haystack) === 1 => 'youtube',
            str_contains($haystack, 'twitter'),
            str_contains($haystack, 'x.com'),
            $raw === 'x' => 'x',
            default => $raw === 'twitter' ? 'x' : $raw,
        };
    }

    protected function dashboardTrackingRowClickCount(array $row): int
    {
        foreach (['clicks', 'click_count', 'clicks_count', 'total', 'count', 'events_count', 'value'] as $key) {
            if (isset($row[$key]) && is_numeric($row[$key])) {
                return max(0, (int) $row[$key]);
            }
        }

        return 1;
    }

    protected function dashboardSocialClickTotal(\Illuminate\Support\Collection $rows, string $platform): int
    {
        $platform = $platform === 'twitter' ? 'x' : strtolower(trim($platform));

        return $rows
            ->filter(fn ($row): bool => is_array($row))
            ->filter(function (array $row) use ($platform): bool {
                return $this->normalizeDashboardSocialPlatform(
                    $row['platform_key'] ?? $row['platform'] ?? $row['rc_platform'] ?? $row['utm_content'] ?? null,
                    $row,
                ) === $platform;
            })
            ->sum(fn (array $row): int => $this->dashboardTrackingRowClickCount($row));
    }

    protected function formatCoachEngagementRows(\Illuminate\Support\Collection $rows): array
    {
        return $rows
            ->map(function (array $row): ?array {
                $platform = $this->normalizeDashboardSocialPlatform(
                    $row['platform_key'] ?? $row['platform'] ?? $row['rc_platform'] ?? $row['utm_content'] ?? null,
                    $row,
                );

                if (! in_array($platform, ['instagram', 'youtube', 'x'], true)) {
                    return null;
                }

                $clicks = $this->dashboardTrackingRowClickCount($row);
                $contactId = trim((string) ($row['coach_id'] ?? $row['coach_contact_id'] ?? $row['contact_id'] ?? ''));
                $coachName = trim((string) ($row['coach_name'] ?? $row['name'] ?? $row['title'] ?? 'Known coach contact')) ?: 'Known coach contact';
                $schoolRef = trim((string) ($row['school_id'] ?? $row['school_business_id'] ?? $row['business_id'] ?? $row['school_key'] ?? ''));
                $schoolName = trim((string) ($row['school'] ?? $row['school_name'] ?? $row['company_name'] ?? ''));
                $school = ($schoolRef !== '' || $schoolName !== '')
                    ? $this->resolveSchool($schoolRef !== '' ? $schoolRef : 'school:' . $schoolName)
                    : null;

                // Use the same coach -> school resolution used by Profile Views.
                // Coach Engagement events often contain only the coach identity,
                // so resolve the canonical local school before the row is rendered.
                if (! $school && $contactId !== '') {
                    $school = $this->resolveSchoolFromCoachReference('coach:' . $contactId);
                }
                if (! $school && filled($row['coach_email'] ?? $row['email'] ?? null)) {
                    $school = $this->resolveSchoolFromCoachReference('coach-email:' . trim((string) ($row['coach_email'] ?? $row['email'])));
                }
                if ($school && $schoolName === '') {
                    $schoolName = trim((string) $school->name);
                }

                $identity = $contactId !== ''
                    ? 'coach:' . $contactId
                    : 'viewer:' . strtolower($schoolRef . '|' . $coachName . '|' . ($row['coach_email'] ?? $row['email'] ?? ''));

                return [
                    'identity_key' => $identity,
                    'contact_id' => $contactId ?: null,
                    'coach_name' => $coachName,
                    'coach_email' => $row['coach_email'] ?? $row['email'] ?? null,
                    'coach_title' => $row['coach_title'] ?? $row['title_name'] ?? null,
                    'school' => $school ? $this->schoolPayload($school) : [
                        'id' => null,
                        'reference' => $schoolRef !== '' ? $schoolRef : ($schoolName !== '' ? 'school:' . $schoolName : null),
                        'name' => $schoolName,
                        'logo_url' => $row['school_logo_url'] ?? $row['business_logo_url'] ?? $row['logo_url'] ?? null,
                        'conference' => $row['conference'] ?? null,
                        'division' => $row['division'] ?? null,
                        'city' => $row['city'] ?? null,
                        'state' => $row['state'] ?? null,
                    ],
                    // Keep coach rows actionable even when the tracking payload only
                    // contains a coach/contact identity and no canonical school id.
                    // dashboardSchool() resolves these coach references back to the
                    // same local school record used by Coach Database.
                    'school_open_reference' => $school
                        ? (string) ($this->schoolPayload($school)['reference'] ?? $school->getKey())
                        : ($schoolRef !== ''
                            ? $schoolRef
                            : ($schoolName !== ''
                                ? 'school:' . $schoolName
                                : ($contactId !== ''
                                    ? 'coach:' . $contactId
                                    : (filled($row['coach_email'] ?? $row['email'] ?? null) ? 'coach-email:' . trim((string) ($row['coach_email'] ?? $row['email'])) : null)))),
                    'count' => $clicks,
                    'platform_counts' => [
                        'instagram' => $platform === 'instagram' ? $clicks : 0,
                        'youtube' => $platform === 'youtube' ? $clicks : 0,
                        'x' => $platform === 'x' ? $clicks : 0,
                    ],
                    'last_at' => $row['last_at'] ?? $row['occurred_at'] ?? $row['time'] ?? $row['created_at'] ?? null,
                    'last_at_label' => $this->activityTimeLabel($row['last_at'] ?? $row['occurred_at'] ?? $row['time'] ?? $row['created_at'] ?? null),
                    'last_subject' => $row['last_subject'] ?? $row['subject'] ?? null,
                ];
            })
            ->filter()
            ->groupBy('identity_key')
            ->map(function ($group): array {
                $group = collect($group);
                $base = (array) $group->sortByDesc(fn ($row) => strtotime((string) ($row['last_at'] ?? '')) ?: 0)->first();
                $base['platform_counts'] = [
                    'instagram' => (int) $group->sum(fn ($row) => (int) data_get($row, 'platform_counts.instagram', 0)),
                    'youtube' => (int) $group->sum(fn ($row) => (int) data_get($row, 'platform_counts.youtube', 0)),
                    'x' => (int) $group->sum(fn ($row) => (int) data_get($row, 'platform_counts.x', 0)),
                ];
                $base['count'] = array_sum($base['platform_counts']);
                return $base;
            })
            ->sortByDesc(fn ($row) => (int) ($row['count'] ?? 0))
            ->take(100)
            ->values()
            ->all();
    }

    /**
     * Locker Room dashboard drill-down using the same local tracking source that
     * powers the Admin Dashboard. Only coach-attributed rows are returned in the
     * coach list; aggregate/direct visits remain represented in the stat total.
     */
    public function dashboardActivity(User $user, string $metric): array
    {
        $metric = strtolower(trim($metric));
        $definitions = [
            'profile_views' => ['label' => 'Profile Views', 'icon' => 'eye'],
            'email_clicks' => ['label' => 'Email Link Clicks', 'icon' => 'cursor'],
            'email_opens' => ['label' => 'Email Opens', 'icon' => 'envelope-open'],
            'social_clicks' => ['label' => 'Coach Engagement', 'icon' => 'share'],
            'emails_sent' => ['label' => 'Emails Sent', 'icon' => 'paper-plane'],
            'coach_replies' => ['label' => 'Coach Replies', 'icon' => 'reply'],
            'schools_engaged' => ['label' => 'Schools Engaged', 'icon' => 'school'],
        ];

        if (! isset($definitions[$metric])) {
            return ['metric' => $metric, 'label' => 'Recruiting Activity', 'total' => 0, 'identified_count' => 0, 'rows' => []];
        }

        $dashboard = $this->dashboardPayload($user);
        $total = (int) data_get($dashboard, 'stats.' . $metric, 0);

        if ($metric === 'coach_replies') {
            $rows = $this->cachedReplyActivityRows($user);
            return [
                'metric' => $metric,
                'label' => $definitions[$metric]['label'],
                'icon' => $definitions[$metric]['icon'],
                'total' => $total,
                'identified_count' => count($rows),
                'rows' => $rows,
                'note' => $total > count($rows) ? 'Some reply activity is available only as an aggregate count.' : null,
            ];
        }

        if ($metric === 'profile_views') {
            $cachedRows = $this->cachedProfileViewRows($user);
            if ($cachedRows !== []) {
                return [
                    'metric' => $metric,
                    'label' => $definitions[$metric]['label'],
                    'icon' => $definitions[$metric]['icon'],
                    'total' => $total,
                    'identified_count' => (int) data_get($dashboard, 'stats.profile_unique_contacts', count($cachedRows)),
                    'schools_reached' => (int) data_get($dashboard, 'stats.profile_unique_schools', 0),
                    'rows' => $cachedRows,
                    'note' => 'Direct or anonymous visits are included in the total but are not shown as identified coaches.',
                ];
            }
        }

        if ($metric === 'social_clicks') {
            $tracking = [];
            try {
                $tracking = app(LocalRecruitingTrackingService::class)->dashboardStats($user);
            } catch (\Throwable) {
                $tracking = [];
            }

            $engagement = $this->localCoachEngagementSnapshot($user, $tracking);
            if (! empty($engagement['rows']) || (bool) ($engagement['authoritative_rows'] ?? false)) {
                return [
                    'metric' => $metric,
                    'label' => $definitions[$metric]['label'],
                    'icon' => $definitions[$metric]['icon'],
                    'total' => (int) ($engagement['total'] ?? 0),
                    'identified_count' => (int) ($engagement['unique_coaches'] ?? count($engagement['rows'] ?? [])),
                    'schools_reached' => (int) ($engagement['unique_schools'] ?? 0),
                    'platform_counts' => $engagement['platform_counts'] ?? ['x' => 0, 'instagram' => 0, 'youtube' => 0],
                    'rows' => $engagement['rows'] ?? [],
                ];
            }
        }

        if (! Schema::hasTable('coach_database_tracking_events')) {
            return [
                'metric' => $metric,
                'label' => $definitions[$metric]['label'],
                'icon' => $definitions[$metric]['icon'],
                'total' => $total,
                'identified_count' => 0,
                'rows' => [],
            ];
        }

        $query = DB::table('coach_database_tracking_events')
            ->where('athlete_user_id', $user->getKey());

        if ($metric === 'profile_views') {
            $query->where('event_type', 'profile_view');
        } elseif ($metric === 'email_clicks') {
            $query->where('event_type', 'link_click')
                ->whereNotNull('message_uuid')
                ->where('message_uuid', '<>', '');
        } elseif ($metric === 'email_opens') {
            $query->where('event_type', 'email_open');
        } elseif ($metric === 'social_clicks') {
            $query->where('event_type', 'link_click')
                ->whereNotNull('message_uuid')
                ->where('message_uuid', '<>', '')
                ->whereIn('platform', ['instagram', 'youtube', 'x']);
        } elseif ($metric === 'emails_sent') {
            $query->where('event_type', 'email_sent');
        } elseif ($metric === 'schools_engaged') {
            $query->where(function ($builder): void {
                $builder->where('event_type', 'profile_view')
                    ->orWhere(function ($clicks): void {
                        $clicks->where('event_type', 'link_click')
                            ->whereNotNull('message_uuid')
                            ->where('message_uuid', '<>', '');
                    });
            })->whereNotNull('school_business_id')->where('school_business_id', '<>', '');
        }

        $events = $query
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit(1000)
            ->get()
            ->map(fn ($row): array => (array) $row)
            ->values();

        if ($metric === 'schools_engaged') {
            $rows = $this->groupSchoolActivityRows($events->all());
        } else {
            $rows = $this->groupCoachActivityRows($events->all(), $metric);
        }

        return [
            'metric' => $metric,
            'label' => $definitions[$metric]['label'],
            'icon' => $definitions[$metric]['icon'],
            'total' => $total,
            'identified_count' => $metric === 'profile_views'
                ? (int) data_get($dashboard, 'stats.profile_unique_contacts', count($rows))
                : count($rows),
            'schools_reached' => $metric === 'profile_views'
                ? (int) data_get($dashboard, 'stats.profile_unique_schools', 0)
                : null,
            'platform_counts' => $metric === 'social_clicks' ? [
                'x' => (int) data_get($dashboard, 'stats.x_clicks', 0),
                'instagram' => (int) data_get($dashboard, 'stats.instagram_clicks', 0),
                'youtube' => (int) data_get($dashboard, 'stats.youtube_clicks', 0),
            ] : null,
            'rows' => $rows,
            'note' => $metric === 'profile_views' && $total > array_sum(array_map(fn (array $row): int => (int) ($row['count'] ?? 0), $rows))
                ? 'Direct or anonymous visits are included in the total but are not shown as identified coaches.'
                : null,
        ];
    }

    public function dashboardSchool(User $user, string $reference): array
    {
        $reference = trim(urldecode($reference));
        if ($reference === '') {
            return ['school' => null, 'coaches' => [], 'lists' => []];
        }

        $school = $this->resolveSchool($reference);

        // Analytics rows are coach-first. If a historical tracking row does not
        // carry a canonical school id/business id, resolve the coach back to its
        // local Coach Database school so clicking the coach remains equivalent to
        // clicking that school inside Recruiting Center.
        if (! $school && (str_starts_with($reference, 'coach:') || str_starts_with($reference, 'coach-email:'))) {
            $school = $this->resolveSchoolFromCoachReference($reference);
        }

        if (! $school) {
            return ['school' => null, 'coaches' => [], 'lists' => []];
        }

        // Use the same local Recruiting Center row builder as the Admin school drawer.
        // This keeps favorite/list state, canonical local school identity, logo/meta,
        // and local coaching staff aligned between Admin and Locker Room.
        $schoolRow = null;
        $lists = [];
        try {
            if (class_exists(\App\Services\LocalRecruitingDatabaseService::class)) {
                $database = app(\App\Services\LocalRecruitingDatabaseService::class);
                $schoolRow = $database->schoolRow($user, (string) $school->getKey());
                $lists = collect($database->lists($user))
                    ->filter(fn ($row): bool => is_array($row))
                    ->values()->all();
            }
        } catch (\Throwable) {
            $schoolRow = null;
            $lists = [];
        }

        $coaches = collect(is_array($schoolRow) ? ($schoolRow['coaches'] ?? []) : [])
            ->filter(fn ($row): bool => is_array($row))
            ->map(function (array $row): array {
                return [
                    'id' => $row['id'] ?? null,
                    'contact_id' => $row['contact_id'] ?? $row['ghl_contact_id'] ?? null,
                    'name' => trim((string) ($row['name'] ?? (($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')))) ?: 'Coach',
                    'email' => $row['email'] ?? null,
                    'phone' => $row['phone'] ?? null,
                    'title' => $row['title'] ?? $row['position'] ?? null,
                ];
            })->values()->all();

        if ($coaches === [] && Schema::hasTable('coaches')) {
            $query = DB::table('coaches')->where('school_id', $school->getKey());
            if (Schema::hasColumn('coaches', 'deleted_at')) $query->whereNull('deleted_at');
            $coaches = $query->get()->map(function ($row): array {
                $row = (array) $row;
                return [
                    'id' => $row['id'] ?? null,
                    'contact_id' => $row['ghl_contact_id'] ?? null,
                    'name' => trim((string) ($row['display_name'] ?? '')) ?: trim((string) (($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''))) ?: 'Coach',
                    'email' => $row['email'] ?? null,
                    'phone' => $row['phone'] ?? null,
                    'title' => $row['title'] ?? null,
                ];
            })->sortBy(fn (array $row): string => strtolower((string) $row['name']))->values()->all();
        }

        $payload = is_array($schoolRow) ? $schoolRow : $this->schoolPayload($school);
        $payload['id'] = (string) ($payload['id'] ?? $school->getKey());
        $payload['school_id'] = (string) ($payload['school_id'] ?? $school->getKey());
        $payload['name'] = (string) ($payload['name'] ?? $school->name);
        $payload['logo_url'] = $payload['logo_url'] ?? $school->logo_url ?? null;
        $payload['conference'] = $payload['conference'] ?? $school->conference ?? null;
        $payload['division'] = $payload['division'] ?? $school->division ?? null;
        $payload['city'] = $payload['city'] ?? $school->city ?? null;
        $payload['state'] = $payload['state'] ?? $school->state ?? null;
        $payload['coaches'] = $coaches;

        return [
            'school' => $payload,
            'coaches' => $coaches,
            'lists' => $lists,
            'roster' => [
                'available' => false,
                'message' => 'Team roster and school performance insights will be available here soon.',
            ],
            'communications' => $this->dashboardSchoolCommunications($user, $school, $coaches),
        ];
    }

    protected function dashboardSchoolCommunications(User $user, School $school, array $coaches): array
    {
        $schoolId = (string) $school->getKey();
        $schoolName = trim((string) $school->name);
        $businessId = trim((string) ($school->ghl_business_id ?? ''));
        $cacheKey = 'locker-room:school-comms:' . $user->getKey() . ':' . md5($schoolId . '|' . $businessId . '|' . strtolower($schoolName));

        return Cache::remember($cacheKey, now()->addMinutes(10), function () use ($user, $schoolId, $schoolName, $businessId, $coaches): array {
            try {
                $coachIds = collect($coaches)
                    ->flatMap(fn (array $coach): array => [
                        $coach['contact_id'] ?? null,
                        $coach['ghl_contact_id'] ?? null,
                        $coach['id'] ?? null,
                    ])
                    ->map(fn ($value): string => strtolower(trim((string) $value)))
                    ->filter()->unique()->values();

                $coachEmails = collect($coaches)
                    ->map(fn (array $coach): string => strtolower(trim((string) ($coach['email'] ?? ''))))
                    ->filter()->unique()->values();

                $coachNames = collect($coaches)
                    ->map(fn (array $coach): string => strtolower(trim((string) ($coach['name'] ?? ''))))
                    ->filter()->unique()->values();

                $schoolIds = collect([$schoolId, $businessId])
                    ->map(fn ($value): string => strtolower(trim((string) $value)))
                    ->filter()->unique()->values();
                $schoolNameKey = strtolower($schoolName);

                $result = app(GoHighLevelService::class)->getConversationsForUser($user, [
                    'limit' => 200,
                    'status' => 'all',
                    'search' => '',
                    'fetch_all' => true,
                ]);

                if (! ($result['success'] ?? false)) {
                    return [];
                }

                $matched = collect((array) ($result['conversations'] ?? []))
                    ->filter(fn ($row): bool => is_array($row))
                    ->filter(function (array $conversation) use ($coachIds, $coachEmails, $coachNames, $schoolIds, $schoolNameKey): bool {
                        $conversationContactIds = collect([
                            $conversation['contact_id'] ?? null,
                            $conversation['contactId'] ?? null,
                            $conversation['coach_id'] ?? null,
                            $conversation['coach_contact_id'] ?? null,
                        ])->map(fn ($value): string => strtolower(trim((string) $value)))->filter()->unique();

                        $conversationSchoolIds = collect([
                            $conversation['school_id'] ?? null,
                            $conversation['school_business_id'] ?? null,
                            $conversation['business_id'] ?? null,
                            $conversation['company_id'] ?? null,
                            $conversation['ghl_business_id'] ?? null,
                        ])->map(fn ($value): string => strtolower(trim((string) $value)))->filter()->unique();

                        $email = strtolower(trim((string) ($conversation['email'] ?? $conversation['contact_email'] ?? '')));
                        $name = strtolower(trim((string) ($conversation['contact_name'] ?? $conversation['name'] ?? $conversation['coach_name'] ?? '')));
                        $conversationSchool = strtolower(trim((string) ($conversation['school'] ?? $conversation['school_name'] ?? $conversation['company_name'] ?? $conversation['company'] ?? '')));

                        return ($conversationContactIds->isNotEmpty() && $coachIds->intersect($conversationContactIds)->isNotEmpty())
                            || ($email !== '' && $coachEmails->contains($email))
                            || ($conversationSchoolIds->isNotEmpty() && $schoolIds->intersect($conversationSchoolIds)->isNotEmpty())
                            || ($schoolNameKey !== '' && $conversationSchool === $schoolNameKey)
                            || ($name !== '' && $coachNames->contains($name));
                    })
                    ->take(30)
                    ->values();

                $rows = [];
                foreach ($matched as $conversation) {
                    $conversationId = trim((string) ($conversation['id'] ?? ''));
                    if ($conversationId === '') {
                        continue;
                    }

                    $messages = app(GoHighLevelService::class)->getConversationMessagesForUser($user, $conversationId, null, 20);
                    $messageRows = collect(($messages['success'] ?? false) ? ($messages['messages'] ?? []) : [])
                        ->filter(fn ($row): bool => is_array($row))
                        ->values();

                    if ($messageRows->isEmpty()) {
                        $rows[] = $this->dashboardSchoolCommunicationRow($conversation, $conversation);
                        continue;
                    }

                    foreach ($messageRows as $message) {
                        $rows[] = $this->dashboardSchoolCommunicationRow($message, $conversation);
                    }
                }

                return collect($rows)
                    ->filter(fn (array $row): bool => trim((string) ($row['preview'] ?? '')) !== '')
                    ->unique(fn (array $row): string => (string) ($row['id'] ?? md5(json_encode($row) ?: '')))
                    ->sortByDesc(fn (array $row): int => (int) ($row['_timestamp'] ?? 0))
                    ->take(30)
                    ->map(function (array $row): array { unset($row['_timestamp']); return $row; })
                    ->values()->all();
            } catch (\Throwable) {
                // School details should still open even when conversation history is
                // temporarily unavailable from the connected recruiting account.
                return [];
            }
        });
    }

    protected function dashboardSchoolCommunicationRow(array $message, array $conversation = []): array
    {
        $directionRaw = strtolower(trim((string) ($message['direction'] ?? $message['messageDirection'] ?? $conversation['direction'] ?? '')));
        $direction = str_contains($directionRaw, 'in') ? 'inbound' : 'outbound';
        $coachName = trim((string) ($conversation['contact_name'] ?? $conversation['name'] ?? $conversation['coach_name'] ?? $message['contact_name'] ?? 'Coach')) ?: 'Coach';
        $body = trim(strip_tags((string) ($message['body'] ?? $message['message'] ?? $message['text'] ?? $message['subject'] ?? $conversation['last_message'] ?? $conversation['snippet'] ?? 'Recruiting email activity')));
        $preview = Str::limit(preg_replace('/\s+/', ' ', $body) ?: 'Recruiting email activity', 180);
        $time = $message['dateAdded'] ?? $message['createdAt'] ?? $message['created_at'] ?? $conversation['last_message_at'] ?? $conversation['updated_at'] ?? $conversation['created_at'] ?? null;
        $carbon = null;
        try { if ($time) $carbon = Carbon::parse($time); } catch (\Throwable) { $carbon = null; }

        return [
            'id' => (string) ($message['id'] ?? $message['_id'] ?? $conversation['id'] ?? md5($coachName . '|' . $preview . '|' . (string) $time)),
            'direction' => $direction,
            'title' => $coachName,
            'preview' => $preview,
            'date_label' => $carbon ? $carbon->diffForHumans() : '',
            'opened' => (bool) ($message['opened'] ?? $message['isOpened'] ?? false),
            'reply' => $direction === 'inbound',
            '_timestamp' => $carbon ? $carbon->getTimestamp() : 0,
        ];
    }

    public function hasPremiumLockerRoomAccess(User $user): bool
    {
        $billing = BillingInformation::query()
            ->where('user_id', $user->id)
            ->latest('updated_at')
            ->first();

        return $this->planKey($user, $billing) === 'my-journey';
    }

    protected function groupCoachActivityRows(array $events, string $metric): array
    {
        $contactIds = collect($events)
            ->map(fn (array $row): string => trim((string) ($row['coach_contact_id'] ?? '')))
            ->filter()
            ->unique()
            ->values();

        $coaches = collect();
        if ($contactIds->isNotEmpty() && Schema::hasTable('coaches') && Schema::hasColumn('coaches', 'ghl_contact_id')) {
            $coachQuery = DB::table('coaches')->whereIn('ghl_contact_id', $contactIds->all());
            if (Schema::hasColumn('coaches', 'deleted_at')) {
                $coachQuery->whereNull('deleted_at');
            }
            $coaches = $coachQuery->get()->map(fn ($row): array => (array) $row)
                ->keyBy(fn (array $row): string => trim((string) ($row['ghl_contact_id'] ?? '')));
        }

        $grouped = [];

        foreach ($events as $event) {
            $contactId = trim((string) ($event['coach_contact_id'] ?? ''));
            if ($contactId === '') {
                continue;
            }

            $metadata = $this->trackingMetadata($event['metadata'] ?? null);
            $coach = $coaches->get($contactId, []);
            $name = trim((string) ($coach['display_name'] ?? ''))
                ?: trim((string) (($coach['first_name'] ?? '') . ' ' . ($coach['last_name'] ?? '')))
                ?: trim((string) ($metadata['coach_name'] ?? $metadata['contact_name'] ?? ''))
                ?: 'Known coach contact';
            $email = trim((string) ($coach['email'] ?? $metadata['coach_email'] ?? ''));
            $title = trim((string) ($coach['title'] ?? $metadata['coach_title'] ?? ''));
            $schoolReference = trim((string) ($event['school_business_id'] ?? ''));
            $schoolName = trim((string) ($metadata['school_name'] ?? $metadata['school'] ?? $metadata['business_name'] ?? ''));

            $school = null;
            $coachSchoolId = $coach['school_id'] ?? null;
            if ($coachSchoolId) {
                $school = School::query()->find($coachSchoolId);
            }
            if (! $school && ($schoolReference !== '' || $schoolName !== '')) {
                $school = $this->resolveSchool($schoolReference !== '' ? $schoolReference : 'school:' . $schoolName);
            }

            $schoolPayload = $school ? $this->schoolPayload($school) : [
                'id' => null,
                'reference' => $schoolReference !== '' ? $schoolReference : ($schoolName !== '' ? 'school:' . $schoolName : null),
                'name' => $schoolName,
                'logo_url' => null,
                'conference' => $coach['conference'] ?? null,
                'division' => $coach['division'] ?? null,
                'city' => $coach['city'] ?? null,
                'state' => $coach['state'] ?? null,
            ];

            $key = strtolower($contactId);
            if (! isset($grouped[$key])) {
                $grouped[$key] = [
                    'coach_id' => $coach['id'] ?? null,
                    'contact_id' => $contactId,
                    'coach_name' => $name,
                    'coach_email' => $email !== '' ? $email : null,
                    'coach_title' => $title !== '' ? $title : null,
                    'school' => $schoolPayload,
                    'school_open_reference' => $school
                        ? (string) ($schoolPayload['reference'] ?? $school->getKey())
                        : ($schoolReference !== ''
                            ? $schoolReference
                            : ($schoolName !== ''
                                ? 'school:' . $schoolName
                                : ($contactId !== '' ? 'coach:' . $contactId : ($email !== '' ? 'coach-email:' . $email : null)))),
                    'count' => 0,
                    'platform_counts' => ['instagram' => 0, 'youtube' => 0, 'x' => 0, 'website' => 0, 'email' => 0],
                    'last_at' => $event['occurred_at'] ?? $event['created_at'] ?? null,
                    'last_at_label' => $this->activityTimeLabel($event['occurred_at'] ?? $event['created_at'] ?? null),
                    'last_subject' => trim((string) ($metadata['email_subject'] ?? $metadata['subject'] ?? '')) ?: null,
                ];
            }

            $grouped[$key]['count']++;
            $platform = strtolower(trim((string) ($event['platform'] ?? '')));
            if (array_key_exists($platform, $grouped[$key]['platform_counts'])) {
                $grouped[$key]['platform_counts'][$platform]++;
            }
        }

        return collect($grouped)
            ->sortByDesc(fn (array $row): int => (int) ($row['count'] ?? 0))
            ->values()
            ->take(100)
            ->all();
    }

    protected function groupSchoolActivityRows(array $events): array
    {
        $rows = [];

        foreach ($events as $event) {
            $metadata = $this->trackingMetadata($event['metadata'] ?? null);
            $reference = trim((string) ($event['school_business_id'] ?? ''));
            $name = trim((string) ($metadata['school_name'] ?? $metadata['school'] ?? $metadata['business_name'] ?? ''));
            if ($reference === '' && $name === '') {
                continue;
            }

            $school = $this->resolveSchool($reference !== '' ? $reference : 'school:' . $name);
            $payload = $school ? $this->schoolPayload($school) : [
                'id' => null,
                'reference' => $reference !== '' ? $reference : 'school:' . $name,
                'name' => $name ?: $reference,
                'logo_url' => null,
                'conference' => null,
                'division' => null,
                'city' => null,
                'state' => null,
            ];

            $key = strtolower((string) ($payload['reference'] ?? $payload['id'] ?? $payload['name'] ?? ''));
            if ($key === '') {
                continue;
            }

            if (! isset($rows[$key])) {
                $rows[$key] = [
                    'school' => $payload,
                    'count' => 0,
                    'coach_contacts' => [],
                    'last_at' => $event['occurred_at'] ?? $event['created_at'] ?? null,
                    'last_at_label' => $this->activityTimeLabel($event['occurred_at'] ?? $event['created_at'] ?? null),
                ];
            }

            $rows[$key]['count']++;
            $contactId = trim((string) ($event['coach_contact_id'] ?? ''));
            if ($contactId !== '') {
                $rows[$key]['coach_contacts'][$contactId] = true;
            }
        }

        return collect($rows)
            ->map(function (array $row): array {
                $row['coach_count'] = count($row['coach_contacts'] ?? []);
                unset($row['coach_contacts']);
                return $row;
            })
            ->sortByDesc(fn (array $row): int => (int) ($row['count'] ?? 0))
            ->values()
            ->take(100)
            ->all();
    }

    protected function cachedProfileViewRows(User $user): array
    {
        $rows = Cache::get($this->dashboardActivityHistoryCacheKey($user), []);

        return collect(is_array($rows) ? $rows : [])
            ->filter(fn ($row): bool => is_array($row))
            ->filter(function (array $row): bool {
                $haystack = strtolower(implode(' ', [
                    (string) ($row['type'] ?? ''),
                    (string) ($row['title'] ?? ''),
                    (string) ($row['copy'] ?? ''),
                ]));
                return str_contains($haystack, 'view') || str_contains($haystack, 'profile');
            })
            ->map(function (array $row): array {
                $title = trim((string) ($row['coach_name'] ?? $row['title'] ?? 'Coach viewed profile')) ?: 'Coach viewed profile';
                $copy = trim(strip_tags((string) ($row['copy'] ?? 'Tracked profile activity'))) ?: 'Tracked profile activity';
                $views = max(1, (int) ($row['views'] ?? $row['count'] ?? 1));
                if (preg_match('/(\d[\d,]*)\s+tracked\s+profile\s+views?/i', $copy, $matches)) {
                    $views = max($views, (int) str_replace(',', '', $matches[1]));
                }

                $schoolRef = trim((string) ($row['school_id'] ?? $row['school_business_id'] ?? $row['business_id'] ?? ''));
                $schoolName = trim((string) ($row['school'] ?? $row['school_name'] ?? ''));
                $school = ($schoolRef !== '' || $schoolName !== '')
                    ? $this->resolveSchool($schoolRef !== '' ? $schoolRef : 'school:' . $schoolName)
                    : null;
                $contactId = trim((string) ($row['coach_id'] ?? $row['coach_contact_id'] ?? $row['contact_id'] ?? ''));

                return [
                    'identity_key' => $contactId !== '' ? 'coach:' . $contactId : 'viewer:' . strtolower($schoolRef . '|' . $title),
                    'contact_id' => $contactId ?: null,
                    'coach_name' => $title,
                    'coach_email' => $row['coach_email'] ?? $row['email'] ?? null,
                    'coach_title' => null,
                    'school' => $school ? $this->schoolPayload($school) : [
                        'id' => null,
                        'reference' => $schoolRef !== '' ? $schoolRef : ($schoolName !== '' ? 'school:' . $schoolName : null),
                        'name' => $schoolName,
                        'logo_url' => $row['logo'] ?? null,
                        'conference' => null,
                        'division' => null,
                        'city' => null,
                        'state' => null,
                    ],
                    'school_open_reference' => $school
                        ? (string) ($this->schoolPayload($school)['reference'] ?? $school->getKey())
                        : ($schoolRef !== ''
                            ? $schoolRef
                            : ($schoolName !== ''
                                ? 'school:' . $schoolName
                                : ($contactId !== ''
                                    ? 'coach:' . $contactId
                                    : (filled($row['coach_email'] ?? $row['email'] ?? null) ? 'coach-email:' . trim((string) ($row['coach_email'] ?? $row['email'])) : null)))),
                    'count' => $views,
                    'platform_counts' => [],
                    'last_at' => $row['time'] ?? $row['created_at'] ?? null,
                    'last_at_label' => $this->activityTimeLabel($row['time'] ?? $row['created_at'] ?? null),
                    'last_subject' => null,
                ];
            })
            ->groupBy('identity_key')
            ->map(fn ($group) => collect($group)->sortByDesc(fn ($row) => (int) ($row['count'] ?? 0))->first())
            ->filter()
            ->sortByDesc(fn ($row) => (int) ($row['count'] ?? 0))
            ->take(100)
            ->values()
            ->all();
    }

    protected function cachedCoachEngagementRows(User $user): array
    {
        $rows = Cache::get($this->dashboardActivityHistoryCacheKey($user), []);

        $normalizePlatform = static function (array $row): string {
            $raw = strtolower(trim((string) (
                $row['platform_icon_key']
                ?? $row['platform']
                ?? $row['platform_key']
                ?? $row['type']
                ?? $row['title']
                ?? ''
            )));

            return match (true) {
                str_contains($raw, 'instagram'), $raw === 'ig' => 'instagram',
                str_contains($raw, 'youtube'), str_contains($raw, 'you_tube'), $raw === 'yt' => 'youtube',
                $raw === 'x', str_contains($raw, 'twitter'), str_contains($raw, 'x.com'), str_contains($raw, 'social_click_x') => 'x',
                default => '',
            };
        };

        return collect(is_array($rows) ? $rows : [])
            ->filter(fn ($row): bool => is_array($row))
            ->map(function (array $row) use ($normalizePlatform): ?array {
                $platform = $normalizePlatform($row);
                if ($platform === '') return null;

                $title = trim((string) ($row['coach_name'] ?? $row['title'] ?? 'Tracked coach engagement')) ?: 'Tracked coach engagement';
                $clicks = max(1, (int) ($row['clicks'] ?? $row['count'] ?? 1));
                $schoolRef = trim((string) ($row['school_id'] ?? $row['school_business_id'] ?? $row['business_id'] ?? ''));
                $schoolName = trim((string) ($row['school'] ?? $row['school_name'] ?? ''));
                $school = ($schoolRef !== '' || $schoolName !== '')
                    ? $this->resolveSchool($schoolRef !== '' ? $schoolRef : 'school:' . $schoolName)
                    : null;
                $contactId = trim((string) ($row['coach_id'] ?? $row['coach_contact_id'] ?? $row['contact_id'] ?? ''));
                if (! $school && $contactId !== '') {
                    $school = $this->resolveSchoolFromCoachReference('coach:' . $contactId);
                }
                if (! $school && filled($row['coach_email'] ?? $row['email'] ?? null)) {
                    $school = $this->resolveSchoolFromCoachReference('coach-email:' . trim((string) ($row['coach_email'] ?? $row['email'])));
                }
                if ($school && $schoolName === '') {
                    $schoolName = trim((string) $school->name);
                }
                $identity = $contactId !== '' ? 'coach:' . $contactId : 'viewer:' . strtolower($schoolRef . '|' . $title);

                return [
                    'identity_key' => $identity,
                    'contact_id' => $contactId ?: null,
                    'coach_name' => $title,
                    'coach_email' => $row['coach_email'] ?? $row['email'] ?? null,
                    'coach_title' => null,
                    'school' => $school ? $this->schoolPayload($school) : [
                        'id' => null,
                        'reference' => $schoolRef !== '' ? $schoolRef : ($schoolName !== '' ? 'school:' . $schoolName : null),
                        'name' => $schoolName,
                        'logo_url' => null,
                        'conference' => null,
                        'division' => null,
                        'city' => null,
                        'state' => null,
                    ],
                    'school_open_reference' => $school
                        ? (string) ($this->schoolPayload($school)['reference'] ?? $school->getKey())
                        : ($schoolRef !== ''
                            ? $schoolRef
                            : ($schoolName !== ''
                                ? 'school:' . $schoolName
                                : ($contactId !== ''
                                    ? 'coach:' . $contactId
                                    : (filled($row['coach_email'] ?? $row['email'] ?? null) ? 'coach-email:' . trim((string) ($row['coach_email'] ?? $row['email'])) : null)))),
                    'count' => $clicks,
                    'platform_counts' => [
                        'instagram' => $platform === 'instagram' ? $clicks : 0,
                        'youtube' => $platform === 'youtube' ? $clicks : 0,
                        'x' => $platform === 'x' ? $clicks : 0,
                    ],
                    'last_at' => $row['time'] ?? $row['created_at'] ?? null,
                    'last_at_label' => $this->activityTimeLabel($row['time'] ?? $row['created_at'] ?? null),
                    'last_subject' => null,
                ];
            })
            ->filter()
            ->groupBy('identity_key')
            ->map(function ($group): array {
                $group = collect($group);
                $base = (array) $group->sortByDesc(fn ($row) => (int) ($row['count'] ?? 0))->first();
                $base['platform_counts'] = [
                    'instagram' => (int) $group->sum(fn ($row) => (int) data_get($row, 'platform_counts.instagram', 0)),
                    'youtube' => (int) $group->sum(fn ($row) => (int) data_get($row, 'platform_counts.youtube', 0)),
                    'x' => (int) $group->sum(fn ($row) => (int) data_get($row, 'platform_counts.x', 0)),
                ];
                $base['count'] = array_sum($base['platform_counts']);
                return $base;
            })
            ->sortByDesc(fn ($row) => (int) ($row['count'] ?? 0))
            ->take(100)
            ->values()
            ->all();
    }

    protected function cachedReplyActivityRows(User $user): array
    {
        $rows = Cache::get($this->dashboardActivityHistoryCacheKey($user), []);

        return collect(is_array($rows) ? $rows : [])
            ->filter(fn ($row): bool => is_array($row))
            ->filter(function (array $row): bool {
                $haystack = strtolower(implode(' ', [
                    (string) ($row['type'] ?? ''),
                    (string) ($row['title'] ?? ''),
                    (string) ($row['copy'] ?? ''),
                ]));
                return str_contains($haystack, 'reply') || str_contains($haystack, 'replied');
            })
            ->map(function (array $row): array {
                $schoolRef = trim((string) ($row['school_id'] ?? $row['business_id'] ?? ''));
                $schoolName = trim((string) ($row['school'] ?? $row['school_name'] ?? ''));
                $school = ($schoolRef !== '' || $schoolName !== '')
                    ? $this->resolveSchool($schoolRef !== '' ? $schoolRef : 'school:' . $schoolName)
                    : null;

                return [
                    'coach_id' => $row['coach_id'] ?? null,
                    'contact_id' => $row['contact_id'] ?? $row['coach_contact_id'] ?? null,
                    'coach_name' => trim((string) ($row['coach_name'] ?? $row['title'] ?? 'Coach')) ?: 'Coach',
                    'coach_email' => $row['coach_email'] ?? $row['email'] ?? null,
                    'coach_title' => null,
                    'school' => $school ? $this->schoolPayload($school) : [
                        'id' => null,
                        'reference' => $schoolRef !== '' ? $schoolRef : ($schoolName !== '' ? 'school:' . $schoolName : null),
                        'name' => $schoolName,
                        'logo_url' => null,
                        'conference' => null,
                        'division' => null,
                        'city' => null,
                        'state' => null,
                    ],
                    'count' => max(1, (int) ($row['count'] ?? 1)),
                    'platform_counts' => [],
                    'last_at' => $row['time'] ?? null,
                    'last_at_label' => $this->activityTimeLabel($row['time'] ?? null),
                    'last_subject' => null,
                ];
            })
            ->take(100)
            ->values()
            ->all();
    }

    protected function dashboardActivitySummaryCacheKey(User $user): string
    {
        return 'coach-database:dashboard-activity:' . $user->id . ':' . md5((string) ($user->ghl_location_id ?? '') . '|' . substr((string) ($user->ghl_api_key ?? ''), -12));
    }

    protected function dashboardActivityHistoryCacheKey(User $user): string
    {
        return 'coach-database:dashboard-activity-history:' . $user->id . ':' . md5((string) ($user->ghl_location_id ?? ''));
    }

    protected function trackingMetadata($raw): array
    {
        if (is_array($raw)) {
            return $raw;
        }

        if (! is_string($raw) || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    protected function resolveSchoolFromCoachReference(string $reference): ?School
    {
        if (! Schema::hasTable('coaches')) {
            return null;
        }

        $query = DB::table('coaches');
        if (Schema::hasColumn('coaches', 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        if (str_starts_with($reference, 'coach-email:')) {
            $email = trim(substr($reference, strlen('coach-email:')));
            if ($email === '' || ! Schema::hasColumn('coaches', 'email')) {
                return null;
            }
            $query->whereRaw('LOWER(email) = ?', [strtolower($email)]);
        } else {
            $contactId = trim(substr($reference, strlen('coach:')));
            if ($contactId === '') {
                return null;
            }

            $query->where(function ($builder) use ($contactId): void {
                $matched = false;
                foreach (['ghl_contact_id', 'contact_id', 'id'] as $column) {
                    if (! Schema::hasColumn('coaches', $column)) {
                        continue;
                    }
                    if (! $matched) {
                        $builder->where($column, $contactId);
                        $matched = true;
                    } else {
                        $builder->orWhere($column, $contactId);
                    }
                }
                if (! $matched) {
                    $builder->whereRaw('1 = 0');
                }
            });
        }

        $coach = $query->first();
        if (! $coach) {
            return null;
        }

        $coach = (array) $coach;
        if (! empty($coach['school_id'])) {
            $school = School::query()->find($coach['school_id']);
            if ($school) {
                return $school;
            }
        }

        foreach (['school_business_id', 'business_id', 'ghl_business_id'] as $column) {
            $value = trim((string) ($coach[$column] ?? ''));
            if ($value !== '') {
                $school = $this->resolveSchool($value);
                if ($school) {
                    return $school;
                }
            }
        }

        $schoolName = trim((string) ($coach['school_name'] ?? $coach['school'] ?? ''));
        return $schoolName !== '' ? $this->resolveSchool('school:' . $schoolName) : null;
    }

    protected function resolveSchool(string $reference): ?School
    {
        $reference = trim(urldecode($reference));
        if ($reference === '') {
            return null;
        }

        if (str_starts_with($reference, 'school:')) {
            $name = trim(substr($reference, 7));
            return $name !== ''
                ? School::query()->whereRaw('LOWER(name) = ?', [strtolower($name)])->first()
                : null;
        }

        if (ctype_digit($reference)) {
            $school = School::query()->find((int) $reference);
            if ($school) {
                return $school;
            }
        }

        if (Schema::hasColumn('schools', 'ghl_business_id')) {
            $school = School::query()->where('ghl_business_id', $reference)->first();
            if ($school) {
                return $school;
            }
        }

        return School::query()->whereRaw('LOWER(name) = ?', [strtolower($reference)])->first();
    }

    protected function schoolPayload(School $school): array
    {
        $logo = $school->logo_url ?? $school->logo ?? null;
        if ($logo && ! Str::startsWith((string) $logo, ['http://', 'https://'])) {
            $logo = $this->storageUrl((string) $logo);
        }

        return [
            'id' => $school->getKey(),
            'reference' => filled($school->ghl_business_id ?? null) ? (string) $school->ghl_business_id : (string) $school->getKey(),
            'business_id' => $school->ghl_business_id ?? null,
            'name' => $school->name ?? 'School',
            'logo_url' => $logo,
            'conference' => $school->conference ?? null,
            'division' => $school->division ?? null,
            'city' => $school->city ?? null,
            'state' => $school->state ?? null,
        ];
    }

    protected function activityTimeLabel($value): string
    {
        if (! $value) {
            return 'Recent';
        }

        try {
            return Carbon::parse($value)->diffForHumans();
        } catch (\Throwable) {
            return 'Recent';
        }
    }

    protected function schedulePayload(User $user): array
    {
        $rows = Schedule::query()
            ->where(function ($query) use ($user): void {
                $query->where('created_by_user_id', $user->id)
                    ->orWhereHas('users', fn ($q) => $q->where('users.id', $user->id));
            })
            ->orderBy('game_date')
            ->orderBy('game_time')
            ->limit(100)
            ->get()
            ->map(fn (Schedule $schedule): array => $this->scheduleRow($schedule, $user))
            ->values()
            ->all();

        return [
            'items' => $rows,
            'upcoming_count' => collect($rows)->where('status', 'upcoming')->count(),
            'total_count' => count($rows),
        ];
    }

    protected function scheduleRow(Schedule $schedule, User $user): array
    {
        $time = null;
        if ($schedule->game_time) {
            try {
                $time = \Illuminate\Support\Carbon::parse($schedule->game_time)->format('H:i');
            } catch (\Throwable) {
                $time = (string) $schedule->game_time;
            }
        }

        return [
            'id' => $schedule->id,
            'title' => $schedule->title,
            'opponent' => $schedule->opponent,
            'game_date' => optional($schedule->game_date)->format('Y-m-d'),
            'date_label' => optional($schedule->game_date)->format('M j, Y'),
            'game_time' => $time,
            'time_label' => $time ? \Illuminate\Support\Carbon::parse($time)->format('g:i A') : null,
            'location' => $schedule->location,
            'venue' => $schedule->venue,
            'status' => $schedule->status ?: 'upcoming',
            'is_home' => (bool) $schedule->is_home,
            'result' => $schedule->result,
            'score' => $schedule->score,
            'notes' => $schedule->notes,
            'can_edit' => (int) $schedule->created_by_user_id === (int) $user->id,
        ];
    }

    protected function settingsPayload(User $user, ?Website $website, bool $isPremium): array
    {
        $defaults = [
            'profile_views' => true,
            'instagram_clicks' => true,
            'youtube_clicks' => true,
            'x_clicks' => true,
            'email_opens' => true,
            'coach_replies' => true,
            'weekly_digest' => false,
            'product_news' => false,
        ];

        $stored = Cache::get('coach-database:notification-settings:' . $user->id, []);
        $notifications = is_array($stored) ? array_merge($defaults, $stored) : $defaults;

        return [
            'notifications' => $notifications,
            'website' => [
                'available' => $isPremium,
                'article_section_type' => in_array($website?->article_section_type, ['follow_me', 'calendar'], true)
                    ? $website->article_section_type
                    : 'follow_me',
                'calendar_name' => $website?->ghl_calendar_name,
            ],
        ];
    }

    protected function billingPayload(?BillingInformation $billing, User $user, ?PaymentTransaction $transaction = null): array
    {
        $brand = $billing?->payment_brand ?: $transaction?->card_brand;
        $lastFour = $billing?->card_last_four ?: $transaction?->card_last_four;
        $provider = $billing?->payment_provider ?: $transaction?->payment_provider;
        $amountPaid = (int) ($billing?->amount_paid_cents ?? 0);
        if ($amountPaid <= 0 && $transaction) {
            $amountPaid = (int) ($transaction->amount_cents ?? 0);
        }

        return [
            'billing_name' => $billing?->billing_name ?: trim($user->first_name . ' ' . $user->last_name),
            'billing_email' => $billing?->billing_email ?: $user->email,
            'billing_phone' => $billing?->billing_phone ?: $user->phone,
            'billing_company' => $billing?->billing_company,
            'billing_address_1' => $billing?->billing_address_1 ?: $user->street,
            'billing_address_2' => $billing?->billing_address_2,
            'billing_city' => $billing?->billing_city ?: $user->city,
            'billing_state' => $billing?->billing_state ?: $user->state,
            'billing_postal_code' => $billing?->billing_postal_code,
            'billing_country' => $billing?->billing_country ?: $user->country,
            'profile_complete' => $billing ? app(BillingProfileService::class)->isComplete($billing) : false,
            'missing_required_fields' => $billing ? app(BillingProfileService::class)->missingRequiredFields($billing) : app(BillingProfileService::class)->requiredProfileFields(),
            'subscriber_contact_ready' => filled($user->ghl_subscriber_contact_id) || filled($billing?->ghl_contact_id),
            'plan_key' => $billing?->plan_key,
            'billing_cycle' => $billing?->billing_cycle,
            'currency' => $billing?->currency ?: 'USD',
            'recurring_amount_cents' => (int) ($billing?->recurring_amount_cents ?? 0),
            'setup_fee_cents' => (int) ($billing?->setup_fee_cents ?? 0),
            'initial_amount_cents' => (int) ($billing?->initial_amount_cents ?? 0),
            'amount_paid_cents' => $amountPaid,
            'amount_refunded_cents' => (int) ($billing?->amount_refunded_cents ?? $transaction?->refunded_amount_cents ?? 0),
            'payment_status' => $billing?->payment_status,
            'subscription_status' => $billing?->subscription_status,
            'cancellation_requested' => (bool) data_get($billing?->registration_meta ?? [], 'cancellation_requested_at'),
            'cancellation_requested_at' => data_get($billing?->registration_meta ?? [], 'cancellation_requested_at'),
            'cardholder_name' => $billing?->cardholder_name,
            'payment_brand' => $brand,
            'card_last_four' => $lastFour,
            'card_expiration' => $billing?->card_expiration,
            'payment_provider' => $provider,
            'payment_mode' => $billing?->payment_mode ?: $transaction?->payment_mode,
            'payment_live_mode' => $billing?->payment_live_mode ?? $transaction?->live_mode,
            'last_transaction_status' => $transaction?->status,
            'last_transaction_amount_cents' => (int) ($transaction?->amount_cents ?? 0),
            'last_transaction_paid_at' => optional($transaction?->paid_at ?: $transaction?->ghl_created_at)->toIso8601String(),
            'payment_synced_at' => optional($billing?->payment_synced_at ?: $transaction?->synced_at)->toIso8601String(),
            'payment_method_update_url' => app(BillingProfileService::class)->paymentMethodUpdateUrl($user, $billing),
            'admin_billing_url' => url('/admin/billing'),
        ];
    }

    protected function websitePayload(?Website $website): array
    {
        $url = null;

        if ($website) {
            if (filled($website->domain)) {
                $domain = preg_replace('#^https?://#i', '', trim((string) $website->domain));
                $url = 'https://' . rtrim($domain, '/');
            } elseif (filled($website->slug)) {
                $url = url('/' . ltrim((string) $website->slug, '/'));
            }
        }

        return [
            'exists' => (bool) $website,
            'is_published' => (bool) ($website?->is_published ?? false),
            'url' => $url,
            'domain' => $website?->domain,
            'slug' => $website?->slug,
        ];
    }

    protected function plans(string $currentPlan, User $user): array
    {
        $configured = (array) config('plyrcard-registration.plans', []);
        $journeyRecurring = (int) data_get($configured, 'my-journey.recurring_amount_cents', 4900);
        $amplifySetup = (int) data_get($configured, 'amplify.setup_fee_cents', 50000);
        $amplifyRecurring = $journeyRecurring;
        $amplifyFirstMonth = (bool) data_get($configured, 'amplify.charge_first_month_upfront', true);
        $amplifyDue = $currentPlan === 'my-journey'
            ? $amplifySetup
            : $amplifySetup + ($amplifyFirstMonth ? $amplifyRecurring : 0);
        $amplifyActive = $this->hasRole($user, 'Amplify');
        $jumpstartActive = $this->hasRole($user, 'Jumpstart');
        $jumpstartCents = (int) data_get($configured, 'jumpstart.setup_fee_cents', 14900);
        $jumpstartDue = $currentPlan === 'my-journey' ? $jumpstartCents : $jumpstartCents + $journeyRecurring;

        $money = static function (int $cents): string {
            $amount = $cents / 100;
            return '$' . (floor($amount) === $amount ? number_format($amount, 0) : number_format($amount, 2));
        };

        return [
            [
                'key' => 'free',
                'name' => 'Free',
                'price' => '$0',
                'suffix' => '/mo',
                'current' => $currentPlan === 'free',
                'description' => 'A simple PLYRCARD with your essential athlete information.',
                'features' => ['Simple PLYRCARD page', 'Quick athlete info', 'Bio & basic stats', 'Email support'],
                'action_label' => $currentPlan === 'free' ? 'Current Plan' : 'Downgrade to Free',
                'action_url' => '#',
            ],
            [
                'key' => 'my-journey',
                'name' => 'My Journey',
                'price' => $money($journeyRecurring),
                'suffix' => '/mo',
                'current' => $currentPlan === 'my-journey',
                'description' => 'Your recruiting HQ with a personalized domain, coach database, outreach tools, and tracking.',
                'features' => ['Everything in Free', 'Personalized domain', 'Your own recruiting email', 'Coach engagement tracking', 'Outreach templates', 'Coach database access', '1-on-1 onboarding'],
                'action_label' => 'Get My Journey',
                'action_url' => '#',
                'action_kind' => 'my_journey_checkout',
            ],
            [
                'key' => 'jumpstart',
                'name' => 'Jumpstart',
                'price' => $money($jumpstartCents),
                'suffix' => 'one time',
                'due_today' => $currentPlan === 'my-journey'
                    ? $money($jumpstartCents) . ' one-time Jumpstart service · My Journey stays active'
                    : $money($jumpstartDue) . ' due today: ' . $money($jumpstartCents) . ' Jumpstart + ' . $money($journeyRecurring) . ' first My Journey month',
                'current' => false,
                'description' => $currentPlan === 'my-journey'
                    ? 'A one-time recruiting service extension added to your active My Journey membership.'
                    : 'Start My Journey and add the one-time Jumpstart recruiting service in one checkout.',
                'features' => ['Everything in My Journey', '1 Coach Outreach Campaign', '1 Highlight Edit', '1 Custom Graphic'],
                'action_label' => $jumpstartActive ? 'Jumpstart Purchased' : 'Get Jumpstart',
                'action_url' => '#',
                'action_kind' => 'jumpstart_checkout',
                'active_addon' => $jumpstartActive,
                'button_disabled' => $jumpstartActive,
            ],
            [
                'key' => 'amplify',
                'name' => 'Amplify',
                'price' => $money($amplifySetup),
                'suffix' => 'one time',
                'due_today' => $currentPlan === 'my-journey'
                    ? $money($amplifySetup) . ' one-time Amplify purchase'
                    : ($money($amplifyDue) . ' due today' . ($amplifyFirstMonth
                        ? ': ' . $money($amplifySetup) . ' setup + ' . $money($amplifyRecurring) . ' first month'
                        : ': ' . $money($amplifySetup) . ' setup; My Journey is ' . $money($amplifyRecurring) . '/mo')),
                'current' => false,
                'description' => $currentPlan === 'my-journey'
                    ? 'A one-time done-for-you setup package added to your active My Journey membership.'
                    : 'Start My Journey and add the one-time Amplify done-for-you package in one checkout.',
                'features' => ['Everything in My Journey', '4 Highlight Reels', '4 Custom Graphics', '4 Managed Coach Outreach sends', '8 Hours of Support', 'Full onboarding'],
                'action_label' => $currentPlan === 'my-journey' ? 'Amplify My Recruiting' : 'Get Amplify',
                'action_url' => '#',
                'action_kind' => 'amplify_checkout',
                'active_addon' => $amplifyActive,
                'button_disabled' => $amplifyActive,
            ],
        ];
    }

    protected function profileCompletion(User $user): int
    {
        // Use the exact same completion calculator as the Coach Database dashboard.
        try {
            return (int) app(ProfileCompletionService::class)->calculate($user);
        } catch (\Throwable) {
            $checks = [
                filled($user->first_name), filled($user->last_name), filled($user->email), filled($user->personal_email),
                filled($user->phone), filled($user->sport), filled($user->position), filled($user->gender), filled($user->year),
                filled($user->birth), filled($user->school_id), filled($user->height), filled($user->weight), filled($user->player_bio),
                filled($user->city), filled($user->state), filled($user->country), filled($user->player_image) || filled($user->plyrcard_image),
                filled($user->league_id), filled($user->club_id), filled($user->team_name),
            ];

            return (int) round((collect($checks)->filter()->count() / max(count($checks), 1)) * 100);
        }
    }

    protected function dateInputValue($value): ?string
    {
        if (blank($value)) {
            return null;
        }

        try {
            return $value instanceof \DateTimeInterface
                ? Carbon::instance($value)->format('Y-m-d')
                : Carbon::parse((string) $value)->format('Y-m-d');
        } catch (\Throwable) {
            return is_scalar($value) ? trim((string) $value) : null;
        }
    }

    protected function storageUrl(?string $path): ?string
    {
        if (blank($path)) {
            return null;
        }

        if (Str::startsWith($path, ['http://', 'https://'])) {
            return $path;
        }

        return Storage::disk('public')->url($path);
    }

    protected function sportOptions(): array
    {
        return [
            'basketball' => 'Basketball', 'volleyball' => 'Volleyball', 'football' => 'Football',
            'baseball' => 'Baseball', 'softball' => 'Softball', 'soccer' => 'Soccer', 'tennis' => 'Tennis',
            'badminton' => 'Badminton', 'table_tennis' => 'Table Tennis', 'track_and_field' => 'Track and Field',
            'swimming' => 'Swimming', 'boxing' => 'Boxing', 'martial_arts' => 'Martial Arts',
        ];
    }

    protected function positionOptions(): array
    {
        return [
            'basketball' => ['point_guard' => 'Point Guard','shooting_guard' => 'Shooting Guard','small_forward' => 'Small Forward','power_forward' => 'Power Forward','center' => 'Center'],
            'volleyball' => ['setter' => 'Setter','outside_hitter' => 'Outside Hitter','opposite_hitter' => 'Opposite Hitter','middle_blocker' => 'Middle Blocker','libero' => 'Libero','defensive_specialist' => 'Defensive Specialist'],
            'football' => ['quarterback' => 'Quarterback','running_back' => 'Running Back','wide_receiver' => 'Wide Receiver','tight_end' => 'Tight End','offensive_line' => 'Offensive Line','defensive_line' => 'Defensive Line','linebacker' => 'Linebacker','cornerback' => 'Cornerback','safety' => 'Safety','kicker' => 'Kicker','punter' => 'Punter'],
            'baseball' => ['pitcher' => 'Pitcher','catcher' => 'Catcher','first_base' => 'First Base','second_base' => 'Second Base','third_base' => 'Third Base','shortstop' => 'Shortstop','left_field' => 'Left Field','center_field' => 'Center Field','right_field' => 'Right Field','designated_hitter' => 'Designated Hitter'],
            'softball' => ['pitcher' => 'Pitcher','catcher' => 'Catcher','first_base' => 'First Base','second_base' => 'Second Base','third_base' => 'Third Base','shortstop' => 'Shortstop','left_field' => 'Left Field','center_field' => 'Center Field','right_field' => 'Right Field'],
            'soccer' => ['goalkeeper' => 'Goalkeeper','defender' => 'Defender','center_back' => 'Center Back','full_back' => 'Full Back','wing_back' => 'Wing Back','midfielder' => 'Midfielder','defensive_midfielder' => 'Defensive Midfielder','central_midfielder' => 'Central Midfielder','attacking_midfielder' => 'Attacking Midfielder','winger' => 'Winger','forward' => 'Forward','striker' => 'Striker'],
            'tennis' => ['singles' => 'Singles','doubles' => 'Doubles'],
            'badminton' => ['singles' => 'Singles','doubles' => 'Doubles','mixed_doubles' => 'Mixed Doubles'],
            'table_tennis' => ['singles' => 'Singles','doubles' => 'Doubles','mixed_doubles' => 'Mixed Doubles'],
            'track_and_field' => ['sprinter' => 'Sprinter','middle_distance' => 'Middle Distance','long_distance' => 'Long Distance','hurdler' => 'Hurdler','jumper' => 'Jumper','thrower' => 'Thrower','relay_runner' => 'Relay Runner','decathlete' => 'Decathlete','heptathlete' => 'Heptathlete'],
            'swimming' => ['freestyle' => 'Freestyle','backstroke' => 'Backstroke','breaststroke' => 'Breaststroke','butterfly' => 'Butterfly','individual_medley' => 'Individual Medley','relay' => 'Relay'],
            'boxing' => ['flyweight' => 'Flyweight','bantamweight' => 'Bantamweight','featherweight' => 'Featherweight','lightweight' => 'Lightweight','welterweight' => 'Welterweight','middleweight' => 'Middleweight','light_heavyweight' => 'Light Heavyweight','heavyweight' => 'Heavyweight'],
            'martial_arts' => ['lightweight' => 'Lightweight','welterweight' => 'Welterweight','middleweight' => 'Middleweight','heavyweight' => 'Heavyweight','striker' => 'Striker','grappler' => 'Grappler','all_rounder' => 'All-Rounder'],
        ];
    }
}