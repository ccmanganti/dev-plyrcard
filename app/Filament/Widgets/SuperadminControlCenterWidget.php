<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\CreditServiceRequests\CreditServiceRequestResource;
use App\Filament\Resources\PlyrLabTrainers\PlyrLabTrainerResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\AdminSupportMessage;
use App\Models\CreditPointTransaction;
use App\Models\CreditServiceRequest;
use App\Models\CoachDatabaseEmailMessage;
use App\Models\PaymentTransaction;
use App\Models\PlyrLabBooking;
use App\Models\PlyrLabTrainer;
use App\Models\User;
use App\Services\ProfileCompletionService;
use App\Services\PlyomatService;
use App\Jobs\ProvisionPlyrLabAthlete;
use Carbon\Carbon;
use Filament\Notifications\Notification;
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

    protected const LOGIN_TRACKING_STARTED_AT = '2026-11-03 00:00:00';

    public static function canView(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        $isSuperadmin = method_exists($user, 'hasRole')
            && (
                $user->hasRole('Superadmin')
                || $user->hasRole('superadmin')
                || $user->hasRole('Super Admin')
            );

        return $isSuperadmin || $user->plyrLabTrainerProfile()->exists();
    }

    public string $initialTab = 'overview';

    public array $plyrLabManualConnections = [];

    public array $plyrLabAdminConnect = [];

    public string $plyrLabTrainerApiKey = '';

    protected function currentUserIsSuperadmin(): bool
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

    protected function currentTrainerProfile(): ?PlyrLabTrainer
    {
        $user = auth()->user();

        if (! $user) {
            return null;
        }

        return $user->plyrLabTrainerProfile()->with('user')->first();
    }

    protected function canManagePlyrLabBooking(PlyrLabBooking $booking): bool
    {
        if ($this->currentUserIsSuperadmin()) {
            return true;
        }

        $trainer = $this->currentTrainerProfile();

        return $trainer && (int) $booking->trainer_id === (int) $trainer->getKey();
    }

    public function overridePlyrLabPlayer(): void
    {
        abort_unless($this->currentUserIsSuperadmin(), 403);

        $state = (array) $this->plyrLabAdminConnect;
        $playerId = (int) ($state['player_id'] ?? 0);
        $trainerId = (int) ($state['trainer_id'] ?? 0);
        $athleteId = trim((string) ($state['athlete_id'] ?? ''));

        if ($playerId < 1 || $trainerId < 1 || $athleteId === '') {
            Notification::make()
                ->title('Player, trainer, and Athlete ID are required')
                ->danger()
                ->send();

            return;
        }

        $player = User::query()->findOrFail($playerId);
        $trainer = PlyrLabTrainer::query()->findOrFail($trainerId);

        $booking = PlyrLabBooking::query()
            ->where('player_user_id', $player->getKey())
            ->whereIn('status', array_merge(PlyrLabBooking::ACCESS_STATUSES, ['cancelled']))
            ->latest('id')
            ->first();

        if (! $booking) {
            $booking = PlyrLabBooking::query()->create([
                'player_user_id' => $player->getKey(),
                'trainer_id' => $trainer->getKey(),
                'scheduled_for' => null,
                'status' => 'active',
                'notes' => 'Athlete ID assigned by PLYRLab admin override.',
            ]);
        }

        $booking->forceFill([
            'trainer_id' => $trainer->getKey(),
            'status' => 'active',
            'plyomat_athlete_id' => $athleteId,
            'plyomat_provisioned_at' => now(),
            'plyomat_last_synced_at' => null,
            // Never carry measurements from the previous Athlete ID into an
            // admin override. The next successful API sync repopulates them.
            'plyomat_stats' => null,
            'last_sync_error' => null,
        ])->save();

        $this->plyrLabAdminConnect = [];
        Cache::forget('superadmin:control-center:v6');

        Notification::make()
            ->title('Athlete ID overridden')
            ->body($this->userName($player) . ' now uses Athlete ID ' . $athleteId . ' through ' . $trainer->displayName() . '. API verification was intentionally bypassed.')
            ->warning()
            ->send();
    }

    public function overridePlyrLabBooking(int $bookingId): void
    {
        abort_unless($this->currentUserIsSuperadmin(), 403);

        $state = (array) ($this->plyrLabManualConnections[$bookingId] ?? []);
        $athleteId = trim((string) ($state['athlete_id'] ?? ''));

        $booking = PlyrLabBooking::query()
            ->with(['player', 'trainer'])
            ->findOrFail($bookingId);

        $trainerId = (int) ($state['trainer_id'] ?? $booking->trainer_id ?? 0);

        if ($trainerId < 1 || $athleteId === '') {
            Notification::make()
                ->title('Trainer and Athlete ID are required')
                ->danger()
                ->send();

            return;
        }

        $trainer = PlyrLabTrainer::query()->findOrFail($trainerId);

        $booking->forceFill([
            'trainer_id' => $trainer->getKey(),
            'status' => 'active',
            'plyomat_athlete_id' => $athleteId,
            'plyomat_provisioned_at' => now(),
            'plyomat_last_synced_at' => null,
            'plyomat_stats' => null,
            'last_sync_error' => null,
        ])->save();

        unset($this->plyrLabManualConnections[$bookingId]);
        Cache::forget('superadmin:control-center:v6');

        Notification::make()
            ->title('Athlete ID overridden')
            ->body($this->userName($booking->player) . ' now uses Athlete ID ' . $athleteId . '. API verification was intentionally bypassed.')
            ->warning()
            ->send();
    }

    public function manualConnectPlyrLabPlayer(): void
    {
        abort_unless($this->currentUserIsSuperadmin(), 403);

        $state = (array) $this->plyrLabAdminConnect;
        $playerId = (int) ($state['player_id'] ?? 0);
        $trainerId = (int) ($state['trainer_id'] ?? 0);
        $athleteId = trim((string) ($state['athlete_id'] ?? ''));

        if ($playerId < 1 || $trainerId < 1 || $athleteId === '') {
            Notification::make()
                ->title('Player, trainer, and Athlete ID are required')
                ->danger()
                ->send();

            return;
        }

        $player = User::query()->findOrFail($playerId);
        $trainer = PlyrLabTrainer::query()->with('user')->findOrFail($trainerId);

        if (! $trainer->hasPlyomatConnection()) {
            Notification::make()
                ->title('Trainer API key is not connected')
                ->body('Select a trainer whose Plyomat API key is configured.')
                ->danger()
                ->send();

            return;
        }

        $booking = PlyrLabBooking::query()
            ->where('player_user_id', $player->getKey())
            ->whereIn('status', PlyrLabBooking::OPEN_STATUSES)
            ->latest('id')
            ->first();

        $createdForManualConnect = false;

        if (! $booking) {
            $booking = PlyrLabBooking::query()->create([
                'player_user_id' => $player->getKey(),
                'trainer_id' => $trainer->getKey(),
                'scheduled_for' => null,
                'status' => 'awaiting_athlete_id',
                'notes' => 'Connected manually by PLYRLab admin.',
            ]);
            $createdForManualConnect = true;
        }

        $previous = [
            'trainer_id' => $booking->trainer_id,
            'athlete_id' => $booking->plyomat_athlete_id,
            'status' => $booking->status,
        ];

        try {
            $booking->setAttribute('trainer_id', $trainer->getKey());
            $booking->setAttribute('plyomat_athlete_id', $athleteId);
            $booking->setRelation('trainer', $trainer);

            $plyomat = app(PlyomatService::class);
            $plyomat->readAthlete($booking);
            $stats = $plyomat->fetchStats($booking);

            $booking->forceFill([
                'trainer_id' => $trainer->getKey(),
                'status' => 'active',
                'plyomat_athlete_id' => $athleteId,
                'plyomat_provisioned_at' => now(),
                'plyomat_last_synced_at' => now(),
                'plyomat_stats' => $stats,
                'last_sync_error' => null,
            ])->save();

            $this->plyrLabAdminConnect = [];
            Cache::forget('superadmin:control-center:v6');

            Notification::make()
                ->title('Player connected to PLYRLab')
                ->body($this->userName($player) . ' is connected through ' . $trainer->displayName() . '.')
                ->success()
                ->send();
        } catch (Throwable $exception) {
            if ($createdForManualConnect) {
                $booking->delete();
            } else {
                $booking->forceFill([
                    'trainer_id' => $previous['trainer_id'],
                    'plyomat_athlete_id' => $previous['athlete_id'],
                    'status' => $previous['status'],
                    'last_sync_error' => $exception->getMessage(),
                ])->save();
            }

            Notification::make()
                ->title('Manual connection failed')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
    }

    public function manualConnectPlyrLabBooking(int $bookingId): void
    {
        abort_unless($this->currentUserIsSuperadmin(), 403);

        $state = (array) ($this->plyrLabManualConnections[$bookingId] ?? []);
        $trainerId = (int) ($state['trainer_id'] ?? 0);
        $athleteId = trim((string) ($state['athlete_id'] ?? ''));

        if ($trainerId < 1 || $athleteId === '') {
            Notification::make()
                ->title('Trainer and Athlete ID are required')
                ->danger()
                ->send();

            return;
        }

        $booking = PlyrLabBooking::query()
            ->with(['player', 'trainer'])
            ->findOrFail($bookingId);

        $trainer = PlyrLabTrainer::query()->findOrFail($trainerId);

        if (! $trainer->hasPlyomatConnection()) {
            Notification::make()
                ->title('Trainer API key is not connected')
                ->body('Choose a trainer with a configured Plyomat API key.')
                ->danger()
                ->send();

            return;
        }

        $previousTrainerId = $booking->trainer_id;
        $previousAthleteId = $booking->plyomat_athlete_id;
        $previousStatus = $booking->status;

        try {
            $booking->setAttribute('trainer_id', $trainer->getKey());
            $booking->setAttribute('plyomat_athlete_id', $athleteId);
            $booking->setRelation('trainer', $trainer);

            $plyomat = app(PlyomatService::class);
            $plyomat->readAthlete($booking);
            $stats = $plyomat->fetchStats($booking);

            $booking->forceFill([
                'trainer_id' => $trainer->getKey(),
                'status' => 'active',
                'plyomat_athlete_id' => $athleteId,
                'plyomat_provisioned_at' => now(),
                'plyomat_last_synced_at' => now(),
                'plyomat_stats' => $stats,
                'last_sync_error' => null,
            ])->save();

            unset($this->plyrLabManualConnections[$bookingId]);

            Cache::forget('superadmin:control-center:v6');

            Notification::make()
                ->title('PLYRLab connected')
                ->body($this->userName($booking->player) . ' is now connected to Plyomat through ' . $trainer->displayName() . '.')
                ->success()
                ->send();
        } catch (Throwable $exception) {
            $booking->forceFill([
                'trainer_id' => $previousTrainerId,
                'plyomat_athlete_id' => $previousAthleteId,
                'status' => $previousStatus,
                'last_sync_error' => $exception->getMessage(),
            ])->save();

            Notification::make()
                ->title('Could not connect athlete')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
    }

    public function saveMyPlyrLabApiKey(): void
    {
        $trainer = $this->currentTrainerProfile();
        abort_unless($trainer, 403);

        $key = trim($this->plyrLabTrainerApiKey);

        if ($key === '') {
            Notification::make()
                ->title('Enter your Plyomat API key')
                ->danger()
                ->send();

            return;
        }

        $trainer->setAttribute('plyomat_api_key', $key);

        try {
            app(PlyomatService::class)->verifyConnection($trainer);
        } catch (Throwable $exception) {
            $trainer->refresh();

            Notification::make()
                ->title('Plyomat API connection failed')
                ->body($exception->getMessage())
                ->danger()
                ->send();

            return;
        }

        $this->plyrLabTrainerApiKey = '';

        $waiting = PlyrLabBooking::query()
            ->where('trainer_id', $trainer->getKey())
            ->where('status', 'awaiting_integration')
            ->latest('id')
            ->limit(20)
            ->get();

        foreach ($waiting as $booking) {
            $booking->forceFill([
                'status' => 'provisioning',
                'last_sync_error' => null,
            ])->save();

            try {
                ProvisionPlyrLabAthlete::dispatchSync($booking->getKey());
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        Cache::forget('superadmin:control-center:v6');

        Notification::make()
            ->title('Plyomat API key connected')
            ->body($waiting->isNotEmpty()
                ? 'Waiting player profiles were retried automatically.'
                : 'Your trainer account is ready for automatic athlete creation.')
            ->success()
            ->send();
    }

    public function setPlyrLabBookingStatus(int $bookingId, string $status): void
    {
        abort_unless(in_array($status, ['active', 'completed', 'cancelled'], true), 422);

        $booking = PlyrLabBooking::query()
            ->with('player')
            ->findOrFail($bookingId);

        abort_unless($this->canManagePlyrLabBooking($booking), 403);

        if ($status === 'active' && blank($booking->plyomat_athlete_id)) {
            Notification::make()
                ->title('Player is not connected yet')
                ->body('A verified Plyomat Athlete ID is required before marking this booking active.')
                ->danger()
                ->send();

            return;
        }

        $booking->forceFill([
            'status' => $status,
        ])->save();

        Cache::forget('superadmin:control-center:v6');

        Notification::make()
            ->title('Booking updated')
            ->body($this->userName($booking->player) . ' is now ' . Str::headline($status) . '.')
            ->success()
            ->send();
    }

    public static function navigationMetrics(): array
    {
        try {
            $data = app(static::class)->getViewData();

            return [
                'athletes' => (int) ($data['athlete_count'] ?? 0),
                'followups' => count((array) ($data['attention_all'] ?? [])),
                'subscriptions' => (int) ($data['billing_stats']['active'] ?? 0),
                'plyrlab' => (int) ($data['plyrlab_stats']['active_athletes'] ?? 0),
                'amplify' => (int) ($data['amplify_credit_stats']['athletes'] ?? 0),
            ];
        } catch (Throwable) {
            return [
                'athletes' => 0,
                'followups' => 0,
                'subscriptions' => 0,
                'plyrlab' => 0,
                'amplify' => 0,
            ];
        }
    }

    public function resetPlyrLabBooking(int $bookingId): void
    {
        $user = auth()->user();

        abort_unless(
            $user
            && method_exists($user, 'hasRole')
            && (
                $user->hasRole('Superadmin')
                || $user->hasRole('superadmin')
                || $user->hasRole('Super Admin')
            ),
            403
        );

        $booking = PlyrLabBooking::query()
            ->with('player:id,first_name,last_name,email,personal_email')
            ->findOrFail($bookingId);

        $playerName = $this->userName($booking->player);

        $booking->forceFill([
            // Keep the booking row as history, but remove it from all open/access
            // states so the player can start again with a fresh trainer booking.
            'status' => 'cancelled',
            'plyomat_athlete_id' => null,
            'plyomat_provisioned_at' => null,
            'plyomat_last_synced_at' => null,
            'plyomat_stats' => null,
            'last_sync_error' => null,
        ])->save();

        Cache::forget('superadmin:control-center:v6');

        Notification::make()
            ->title('PLYRLab reset')
            ->body($playerName . ' has been disconnected from Plyomat in PLYRCard and can book/connect again.')
            ->success()
            ->send();
    }

    public function getViewData(): array
    {
        if (! $this->currentUserIsSuperadmin() && $this->currentTrainerProfile()) {
            $base = parent::getViewData();
            $data = array_merge($base, $this->buildOperationalData());

            // Trainer accounts share the PLYRLab view shell, but must never
            // receive the broader superadmin athlete/support datasets.
            $data['athletes'] = [];
            $data['athlete_count'] = 0;
            $data['attention_all'] = [];
            $data['follow_up_queues'] = [];
            $data['plan_summary'] = [];
            $data['billing_rows'] = [];
            $data['amplify_credit_rows'] = [];
            $data['amplify_credit_services'] = [];
            $data['new_signups'] = [];
            $data['reminders_this_week'] = [];
            $data['service_activity'] = ['items' => [], 'open' => 0, 'week' => 0];
            $data['payment_activity'] = ['items' => [], 'week_count' => 0, 'week_amount' => '$0'];

            return $data;
        }

        return Cache::remember(
            'superadmin:control-center:v6',
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
                        $trackingStartedAt = Carbon::parse(static::LOGIN_TRACKING_STARTED_AT);
                        $createdAt = $user->created_at instanceof \DateTimeInterface
                            ? Carbon::instance($user->created_at)
                            : null;

                        if ($createdAt && $createdAt->gte($trackingStartedAt)) {
                            $flags[] = [
                                'key' => 'getting_started',
                                'label' => 'Never logged in',
                                'detail' => 'No login has been recorded since this account was created.',
                                'weight' => 30,
                                'tone' => 'danger',
                                'concern' => 'getting_started',
                                'action_label' => 'Send login reminder',
                            ];
                        } else {
                            $lastLoginLabel = 'Historical login not tracked';
                        }
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
                                    'detail' => 'Last recorded login was ' . number_format($days) . ' days ago.',
                                    'weight' => 18,
                                    'tone' => 'warning',
                                    'concern' => 'getting_started',
                                    'action_label' => 'Send login reminder',
                                ];
                            }
                        } catch (Throwable) {
                            $lastLoginLabel = 'Login timestamp invalid';
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
                'description' => 'Based on the recorded last_login_at value only.',
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
                'title' => 'No YouTube film',
                'description' => 'No YouTube/Youtu.be URL saved in the player link fields.',
                'concern' => 'send_film',
            ],
            'photo' => [
                'title' => 'No player-uploaded photo',
                'description' => 'No image exists in the player-uploaded My Photos / Quick Profile gallery.',
                'concern' => 'send_photo',
            ],
            'outreach' => [
                'title' => 'Quiet 30+ days',
                'description' => 'Recurring athletes with no coach outreach or 30+ days since the last sent outreach.',
                'concern' => 'start_outreach',
            ],
            'renewal' => [
                'title' => 'Renews within 7 days',
                'description' => 'Recurring Stripe subscriptions whose stored current period end is within 7 days.',
                'concern' => 'renewal_coming_up',
            ],
        ])->map(function (array $config, string $key) use ($rows): array {
            $items = $rows
                ->filter(fn (array $row): bool => collect($row['flags'])->contains(
                    fn (array $flag): bool => ($flag['key'] ?? '') === $key
                ))
                ->map(function (array $row) use ($key): array {
                    $flag = collect($row['flags'])->first(
                        fn (array $candidate): bool => ($candidate['key'] ?? '') === $key
                    );

                    $row['queue_detail'] = trim((string) ($flag['detail'] ?? ''));
                    $row['queue_label'] = trim((string) ($flag['label'] ?? ''));

                    return $row;
                })
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
                $row = $rows->firstWhere('id', $creditRow['id']) ?? [];

                if ($row !== []) {
                    $row['queue_label'] = 'Credits idle';
                    $row['queue_detail'] = number_format((int) ($creditRow['points_available'] ?? 0))
                        . ' PLYR Points available · last service activity '
                        . ($creditRow['last_activity'] ?? 'not recorded');
                }

                return $row;
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
        $plyrLabAdmin = $this->plyrLabAdminData();

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
            'plyrlab_trainers' => $plyrLabAdmin['trainers'],
            'plyrlab_bookings' => $plyrLabAdmin['bookings'],
            'plyrlab_stats' => $plyrLabAdmin['stats'],
            'plyrlab_trainers_url' => $plyrLabAdmin['trainers_url'],
            'plyrlab_add_trainer_url' => $plyrLabAdmin['add_trainer_url'],
            'plyrlab_is_superadmin' => (bool) ($plyrLabAdmin['is_superadmin'] ?? false),
            'plyrlab_is_trainer_portal' => (bool) ($plyrLabAdmin['is_trainer_portal'] ?? false),
            'plyrlab_viewer_trainer' => $plyrLabAdmin['viewer_trainer'] ?? null,
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

    protected function plyrLabAdminData(): array
    {
        $empty = [
            'trainers' => [],
            'bookings' => [],
            'stats' => [
                'trainers' => 0,
                'bookable' => 0,
                'connected' => 0,
                'bookings' => 0,
                'active_athletes' => 0,
                'needs_attention' => 0,
            ],
            'trainers_url' => null,
            'add_trainer_url' => null,
            'is_superadmin' => false,
            'is_trainer_portal' => false,
            'viewer_trainer' => null,
        ];

        if (
            ! class_exists(PlyrLabTrainer::class)
            || ! class_exists(PlyrLabBooking::class)
            || ! Schema::hasTable('plyr_lab_trainers')
            || ! Schema::hasTable('plyr_lab_bookings')
        ) {
            return $empty;
        }

        try {
            $isSuperadmin = $this->currentUserIsSuperadmin();
            $viewerTrainer = $this->currentTrainerProfile();

            if (! $isSuperadmin && ! $viewerTrainer) {
                return $empty;
            }

            $trainerQuery = PlyrLabTrainer::query()
                ->with('user:id,first_name,last_name,email,player_image,plyrcard_image');

            if (! $isSuperadmin && $viewerTrainer) {
                $trainerQuery->whereKey($viewerTrainer->getKey());
            }

            $trainers = $trainerQuery
                ->withCount('bookings')
                ->orderByDesc('is_active')
                ->orderBy('display_name')
                ->get();

            $trainerRows = $trainers
                ->map(function (PlyrLabTrainer $trainer): array {
                    $latestBooking = PlyrLabBooking::query()
                        ->where('trainer_id', $trainer->getKey())
                        ->latest('created_at')
                        ->first();

                    $activeAthletes = PlyrLabBooking::query()
                        ->where('trainer_id', $trainer->getKey())
                        ->whereIn('status', ['active', 'completed'])
                        ->whereNotNull('plyomat_athlete_id')
                        ->distinct('player_user_id')
                        ->count('player_user_id');

                    $name = $trainer->displayName();

                    return [
                        'id' => (int) $trainer->getKey(),
                        'name' => $name,
                        'initials' => collect(preg_split('/\s+/', trim($name)) ?: [])
                            ->filter()
                            ->take(2)
                            ->map(fn (string $part): string => Str::upper(Str::substr($part, 0, 1)))
                            ->implode('') ?: 'PL',
                        'email' => (string) ($trainer->user?->email ?? ''),
                        'headline' => trim((string) ($trainer->headline ?? '')) ?: 'PLYRLab Performance Trainer',
                        'location' => trim((string) ($trainer->location ?? '')),
                        'specialties' => array_values(array_filter((array) ($trainer->specialties ?? []))),
                        'bookable' => (bool) $trainer->is_active,
                        'plyomat_connected' => $trainer->hasPlyomatConnection(),
                        'bookings' => (int) ($trainer->bookings_count ?? 0),
                        'active_athletes' => $activeAthletes,
                        'last_booking' => $latestBooking?->created_at?->diffForHumans() ?? 'No bookings yet',
                        'edit_url' => $this->plyrLabTrainerUrl($trainer),
                    ];
                })
                ->values()
                ->all();

            $bookingRowsQuery = PlyrLabBooking::query()
                ->with([
                    'player:id,first_name,last_name,email,personal_email,sport,team_name',
                    'trainer.user:id,first_name,last_name,email',
                ]);

            if (! $isSuperadmin && $viewerTrainer) {
                $bookingRowsQuery->where('trainer_id', $viewerTrainer->getKey());
            }

            $bookings = $bookingRowsQuery
                ->latest('created_at')
                ->limit(30)
                ->get()
                ->map(function (PlyrLabBooking $booking) use ($isSuperadmin, $viewerTrainer): array {
                    $player = $booking->player;
                    $playerName = $this->userName($player);
                    $status = trim((string) $booking->status) ?: 'pending';

                    return [
                        'id' => (int) $booking->getKey(),
                        'player_id' => (int) ($booking->player_user_id ?? 0),
                        'player' => $playerName,
                        'initials' => $player ? $this->initials($player) : 'P',
                        'sport' => $player ? $this->sportLabel((string) ($player->sport ?? '')) : 'Athlete',
                        'trainer' => $booking->trainer?->displayName() ?? 'Trainer unavailable',
                        'scheduled_for' => $booking->scheduled_for?->format('M j, Y · g:i A') ?? 'Not scheduled',
                        'status' => $status,
                        'status_label' => match ($status) {
                            'provisioning', 'pending' => 'Connecting to Plyomat',
                            'awaiting_athlete_id' => 'Awaiting Athlete ID',
                            'awaiting_integration' => 'Awaiting Trainer API',
                            'provisioning_failed' => 'Connection Failed',
                            'active' => 'Active',
                            'completed' => 'Completed',
                            'cancelled' => 'Cancelled',
                            default => Str::headline($status),
                        },
                        'plyomat_ready' => filled($booking->plyomat_athlete_id),
                        'last_sync' => $booking->plyomat_last_synced_at?->diffForHumans()
                            ?? ($booking->plyomat_provisioned_at?->diffForHumans() ?? 'Not synced'),
                        'needs_attention' => in_array($status, ['awaiting_athlete_id', 'awaiting_integration', 'provisioning_failed'], true),
                        'can_reset' => $isSuperadmin && (
                            $status !== 'cancelled'
                            || filled($booking->plyomat_athlete_id)
                            || is_array($booking->plyomat_stats)
                        ),
                        'can_manage' => $isSuperadmin
                            || ($viewerTrainer && (int) $booking->trainer_id === (int) $viewerTrainer->getKey()),
                        'athlete_id' => (string) ($booking->plyomat_athlete_id ?? ''),
                        'connection_error' => (string) ($booking->last_sync_error ?? ''),
                        'user_url' => $player ? $this->userUrl($player) : null,
                    ];
                })
                ->all();

            $bookingQuery = PlyrLabBooking::query();

            if (! $isSuperadmin && $viewerTrainer) {
                $bookingQuery->where('trainer_id', $viewerTrainer->getKey());
            }

            return [
                'trainers' => $trainerRows,
                'bookings' => $bookings,
                'stats' => [
                    'trainers' => $trainers->count(),
                    'bookable' => $trainers->where('is_active', true)->count(),
                    'connected' => $trainers->filter(fn (PlyrLabTrainer $trainer): bool => $trainer->hasPlyomatConnection())->count(),
                    'bookings' => (clone $bookingQuery)->count(),
                    'active_athletes' => (clone $bookingQuery)
                        ->whereIn('status', ['active', 'completed'])
                        ->whereNotNull('plyomat_athlete_id')
                        ->distinct('player_user_id')
                        ->count('player_user_id'),
                    'needs_attention' => (clone $bookingQuery)
                        ->whereIn('status', ['awaiting_athlete_id', 'awaiting_integration', 'provisioning_failed'])
                        ->count(),
                ],
                'trainers_url' => $isSuperadmin ? $this->plyrLabTrainerIndexUrl() : null,
                'add_trainer_url' => $isSuperadmin ? $this->plyrLabTrainerCreateUrl() : null,
                'is_superadmin' => $isSuperadmin,
                'is_trainer_portal' => ! $isSuperadmin && (bool) $viewerTrainer,
                'viewer_trainer' => $viewerTrainer ? [
                    'id' => (int) $viewerTrainer->getKey(),
                    'name' => $viewerTrainer->displayName(),
                    'headline' => trim((string) ($viewerTrainer->headline ?? '')) ?: 'PLYRLab Performance Trainer',
                    'api_connected' => $viewerTrainer->hasPlyomatConnection(),
                    'location' => trim((string) ($viewerTrainer->location ?? '')),
                ] : null,
            ];
        } catch (Throwable) {
            return $empty;
        }
    }

    protected function plyrLabTrainerIndexUrl(): ?string
    {
        try {
            return PlyrLabTrainerResource::getUrl('index');
        } catch (Throwable) {
            return null;
        }
    }

    protected function plyrLabTrainerCreateUrl(): ?string
    {
        try {
            return PlyrLabTrainerResource::getUrl('create');
        } catch (Throwable) {
            return null;
        }
    }

    protected function plyrLabTrainerUrl(PlyrLabTrainer $trainer): ?string
    {
        try {
            return PlyrLabTrainerResource::getUrl('edit', ['record' => $trainer]);
        } catch (Throwable) {
            return null;
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
