<?php

namespace App\Filament\Resources\Coaches\Pages;

use App\Filament\Resources\Coaches\CoachResource;
use App\Models\Coach;
use App\Models\Club;
use App\Models\League;
use App\Models\School;
use App\Models\User;
use App\Services\RecruitingVisibilityService;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema as SchemaFacade;
use Illuminate\Support\Str;

class ManageCoachExclusivity extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $resource = CoachResource::class;
    protected string $view = 'filament.resources.coaches.pages.manage-coach-exclusivity';

    public ?array $data = [];

    public string $visibilityType = 'users';
    public array|string|null $visibilityUserIds = [];
    public string $visibilityUserSearch = '';
    public ?string $visibilityClubId = null;
    public ?string $visibilityLeagueId = null;

    public function getTitle(): string
    {
        return 'Coach Database Exclusivity';
    }

    public function mount(): void
    {
        abort_unless($this->canManageExclusivity(), 403);

        $this->form->fill([
            'target_type' => 'school',
            'school_id' => null,
            'coach_id' => null,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Create or update an exclusivity rule')
                    ->description('Choose a school to restrict every coach under it, or choose one coach to restrict only that coach.')
                    ->columns(2)
                    ->schema([
                        Select::make('target_type')
                            ->label('Restrict')
                            ->options([
                                'school' => 'School (all coaches under the school)',
                                'coach' => 'Specific coach only',
                            ])
                            ->native(false)
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (Set $set): void {
                                $set('school_id', null);
                                $set('coach_id', null);
                            }),

                        Select::make('school_id')
                            ->label('School')
                            ->placeholder('Search for a school')
                            ->searchable()
                            ->preload(false)
                            ->required(fn (Get $get): bool => $get('target_type') === 'school')
                            ->visible(fn (Get $get): bool => $get('target_type') === 'school')
                            ->getSearchResultsUsing(fn (string $search): array => $this->schoolOptions($search))
                            ->getOptionLabelUsing(fn ($value): ?string => $this->schoolLabel($value)),

                        Select::make('coach_id')
                            ->label('Coach')
                            ->placeholder('Search coach, email, or school')
                            ->searchable()
                            ->preload(false)
                            ->required(fn (Get $get): bool => $get('target_type') === 'coach')
                            ->visible(fn (Get $get): bool => $get('target_type') === 'coach')
                            ->getSearchResultsUsing(fn (string $search): array => $this->coachOptions($search))
                            ->getOptionLabelUsing(fn ($value): ?string => $this->coachLabel($value)),
                    ]),
            ]);
    }

    public function save(): void
    {
        abort_unless($this->canManageExclusivity(), 403);

        $data = $this->form->getState();
        $type = (string) ($data['target_type'] ?? 'school');
        $service = app(RecruitingVisibilityService::class);

        try {
            $targetId = $type === 'coach'
                ? (int) ($data['coach_id'] ?? 0)
                : (int) ($data['school_id'] ?? 0);

            if ($targetId <= 0) {
                $this->addError($type === 'coach' ? 'data.coach_id' : 'data.school_id', $type === 'coach' ? 'Choose a coach.' : 'Choose a school.');
                return;
            }

            $targetLabel = $type === 'coach'
                ? ($this->coachLabel($targetId) ?: 'Coach')
                : ($this->schoolLabel($targetId) ?: 'School');

            if ($this->visibilityType === 'public') {
                if ($type === 'coach') {
                    $service->makeCoachPublic($targetId);
                } else {
                    $service->makeSchoolPublic($targetId);
                }

                $message = $targetLabel . ' is now visible to every eligible Recruiting Center user.';
            } else {
                $userIds = $this->resolveVisibilityUserIds();

                if ($type === 'coach') {
                    $service->syncCoach($targetId, $userIds);
                } else {
                    $service->syncSchool($targetId, $userIds);
                }

                $message = $targetLabel . ' is now limited to ' . $this->visibilityAudienceLabel($userIds) . '.';
            }
        } catch (\Throwable $exception) {
            Notification::make()
                ->title('Exclusivity could not be saved')
                ->body($exception->getMessage())
                ->danger()
                ->send();
            return;
        }

        Notification::make()
            ->title($this->visibilityType === 'public' ? 'Made public' : 'Exclusivity saved')
            ->body($message)
            ->success()
            ->send();

        $this->form->fill([
            'target_type' => $type,
            'school_id' => null,
            'coach_id' => null,
        ]);
        $this->resetVisibilityAudience();
    }

    public function editRule(string $type, int $targetId): void
    {
        abort_unless($this->canManageExclusivity(), 403);

        $type = $type === 'coach' ? 'coach' : 'school';
        $service = app(RecruitingVisibilityService::class);

        $this->form->fill([
            'target_type' => $type,
            'school_id' => $type === 'school' ? $targetId : null,
            'coach_id' => $type === 'coach' ? $targetId : null,
        ]);

        $this->visibilityType = 'users';
        $this->visibilityUserIds = $service->assignedUserIds($type, $targetId);
        $this->visibilityUserSearch = '';
        $this->visibilityClubId = null;
        $this->visibilityLeagueId = null;

        $this->dispatch('scroll-to-exclusivity-form');
    }

    public function makePublic(string $type, int $targetId): void
    {
        abort_unless($this->canManageExclusivity(), 403);

        $service = app(RecruitingVisibilityService::class);

        if ($type === 'coach') {
            $label = $this->coachLabel($targetId) ?: 'Coach';
            $service->makeCoachPublic($targetId);
        } else {
            $label = $this->schoolLabel($targetId) ?: 'School';
            $service->makeSchoolPublic($targetId);
        }

        Notification::make()
            ->title('Made public')
            ->body($label . ' is now visible to every eligible Recruiting Center user.')
            ->success()
            ->send();
    }

    public function setVisibilityType(string $type): void
    {
        if (! in_array($type, ['public', 'users', 'club', 'league'], true)) {
            return;
        }

        $this->visibilityType = $type;
        $this->visibilityUserIds = [];
        $this->visibilityUserSearch = '';
        $this->visibilityClubId = null;
        $this->visibilityLeagueId = null;
    }

    public function toggleVisibilityUser(int $userId): void
    {
        if ($userId <= 0 || ! $this->userQuery()->whereKey($userId)->exists()) {
            return;
        }

        $ids = collect($this->normalizedVisibilityUserIds());
        if ($ids->contains($userId)) {
            $ids = $ids->reject(fn (int $id): bool => $id === $userId);
        } else {
            $ids->push($userId);
        }

        $this->visibilityUserIds = $ids->unique()->values()->all();
    }

    public function removeVisibilityUser(int $userId): void
    {
        $this->visibilityUserIds = collect($this->normalizedVisibilityUserIds())
            ->reject(fn (int $id): bool => $id === $userId)
            ->values()
            ->all();
    }

    public function getVisibilityUserOptionsProperty(): array
    {
        $search = trim($this->visibilityUserSearch);

        return $this->userQuery($search)
            ->limit($search === '' ? 20 : 50)
            ->get(['id', 'first_name', 'last_name', 'email', 'sport'])
            ->mapWithKeys(fn (User $user): array => [
                (string) $user->getKey() => $this->formatUserLabel($user),
            ])
            ->all();
    }

    public function getVisibilitySelectedUsersProperty(): array
    {
        $ids = $this->normalizedVisibilityUserIds();
        if ($ids === []) {
            return [];
        }

        return $this->userQuery()
            ->whereIn('id', $ids)
            ->get(['id', 'first_name', 'last_name', 'email', 'sport'])
            ->map(fn (User $user): array => [
                'id' => (int) $user->getKey(),
                'label' => $this->formatUserLabel($user),
            ])
            ->values()
            ->all();
    }

    public function getVisibilityClubOptionsProperty(): array
    {
        return Club::query()
            ->orderBy('name')
            ->limit(500)
            ->pluck('name', 'id')
            ->mapWithKeys(fn ($name, $id): array => [(string) $id => (string) $name])
            ->all();
    }

    public function getVisibilityLeagueOptionsProperty(): array
    {
        return League::query()
            ->orderBy('name')
            ->limit(500)
            ->pluck('name', 'id')
            ->mapWithKeys(fn ($name, $id): array => [(string) $id => (string) $name])
            ->all();
    }

    public function getVisibilityReadyProperty(): bool
    {
        return match ($this->visibilityType) {
            'users' => $this->normalizedVisibilityUserIds() !== [],
            'club' => (int) $this->visibilityClubId > 0,
            'league' => (int) $this->visibilityLeagueId > 0,
            default => true,
        };
    }

    protected function resetVisibilityAudience(): void
    {
        $this->visibilityType = 'users';
        $this->visibilityUserIds = [];
        $this->visibilityUserSearch = '';
        $this->visibilityClubId = null;
        $this->visibilityLeagueId = null;
    }

    protected function normalizedVisibilityUserIds(): array
    {
        $value = $this->visibilityUserIds;
        if ($value === null || $value === '') {
            return [];
        }

        $values = is_array($value) ? $value : [$value];

        return collect($values)
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    protected function resolveVisibilityUserIds(): array
    {
        $type = $this->visibilityType;
        if (! in_array($type, ['users', 'club', 'league'], true)) {
            throw new \InvalidArgumentException('Choose a valid exclusivity audience.');
        }

        $ids = match ($type) {
            'users' => $this->userQuery()->whereIn('id', $this->normalizedVisibilityUserIds())->pluck('id'),
            'club' => $this->audienceUserIdsForClub((int) $this->visibilityClubId),
            'league' => $this->audienceUserIdsForLeague((int) $this->visibilityLeagueId),
        };

        $ids = collect($ids)
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            throw new \InvalidArgumentException(match ($type) {
                'users' => 'Select at least one user.',
                'club' => 'The selected club does not currently have any eligible users.',
                'league' => 'The selected league does not currently have any eligible users.',
            });
        }

        return $ids->all();
    }

    protected function audienceUserIdsForClub(int $clubId)
    {
        if ($clubId <= 0 || ! Club::query()->whereKey($clubId)->exists()) {
            throw new \InvalidArgumentException('Choose a valid club.');
        }

        $ids = collect();

        if (SchemaFacade::hasColumn('users', 'club_id')) {
            $ids = $ids->merge($this->userQuery()->where('club_id', $clubId)->pluck('id'));
        }
        if (SchemaFacade::hasColumn('users', 'legacy_club_id')) {
            $ids = $ids->merge($this->userQuery()->where('legacy_club_id', $clubId)->pluck('id'));
        }
        if (SchemaFacade::hasColumn('users', 'club_league_id') && SchemaFacade::hasTable('club_leagues')) {
            $clubLeagueQuery = DB::table('club_leagues')->where('club_id', $clubId);
            if (SchemaFacade::hasColumn('club_leagues', 'deleted_at')) {
                $clubLeagueQuery->whereNull('deleted_at');
            }
            $clubLeagueIds = $clubLeagueQuery->pluck('id')->all();
            if ($clubLeagueIds !== []) {
                $ids = $ids->merge($this->userQuery()->whereIn('club_league_id', $clubLeagueIds)->pluck('id'));
            }
        }

        return $ids->unique()->values();
    }

    protected function audienceUserIdsForLeague(int $leagueId)
    {
        if ($leagueId <= 0 || ! League::query()->whereKey($leagueId)->exists()) {
            throw new \InvalidArgumentException('Choose a valid league.');
        }

        $ids = collect();

        if (SchemaFacade::hasColumn('users', 'league_id')) {
            $ids = $ids->merge($this->userQuery()->where('league_id', $leagueId)->pluck('id'));
        }
        if (SchemaFacade::hasColumn('users', 'legacy_league_id')) {
            $ids = $ids->merge($this->userQuery()->where('legacy_league_id', $leagueId)->pluck('id'));
        }
        if (SchemaFacade::hasColumn('users', 'club_league_id') && SchemaFacade::hasTable('club_leagues')) {
            $clubLeagueQuery = DB::table('club_leagues')->where('league_id', $leagueId);
            if (SchemaFacade::hasColumn('club_leagues', 'deleted_at')) {
                $clubLeagueQuery->whereNull('deleted_at');
            }
            $clubLeagueIds = $clubLeagueQuery->pluck('id')->all();
            if ($clubLeagueIds !== []) {
                $ids = $ids->merge($this->userQuery()->whereIn('club_league_id', $clubLeagueIds)->pluck('id'));
            }
        }

        return $ids->unique()->values();
    }

    protected function visibilityAudienceLabel(array $userIds): string
    {
        return match ($this->visibilityType) {
            'users' => count($userIds) . ' selected user' . (count($userIds) === 1 ? '' : 's'),
            'club' => (string) (Club::query()->whereKey((int) $this->visibilityClubId)->value('name') ?: 'the selected club'),
            'league' => (string) (League::query()->whereKey((int) $this->visibilityLeagueId)->value('name') ?: 'the selected league'),
            default => 'the selected audience',
        };
    }

    public function getRulesProperty(): array
    {
        return app(RecruitingVisibilityService::class)->adminRules();
    }

    protected function schoolOptions(string $search): array
    {
        $search = trim($search);

        return School::query()
            ->when($search !== '', fn (Builder $query): Builder => $query->where(function (Builder $query) use ($search): void {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%")
                    ->orWhere('state', 'like', "%{$search}%");
            }))
            ->orderBy('name')
            ->limit(50)
            ->get(['id', 'name', 'city', 'state'])
            ->mapWithKeys(function (School $school): array {
                $location = collect([$school->city, $school->state])->filter()->implode(', ');
                return [(string) $school->getKey() => $school->name . ($location ? ' — ' . $location : '')];
            })
            ->all();
    }

    protected function schoolLabel(mixed $value): ?string
    {
        $school = School::query()->find((int) $value);
        if (! $school) {
            return null;
        }

        $location = collect([$school->city, $school->state])->filter()->implode(', ');
        return $school->name . ($location ? ' — ' . $location : '');
    }

    protected function coachOptions(string $search): array
    {
        $search = trim($search);

        return Coach::query()
            ->with('school:id,name')
            ->when($search !== '', fn (Builder $query): Builder => $query->where(function (Builder $query) use ($search): void {
                $query->where('display_name', 'like', "%{$search}%")
                    ->orWhere('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhereHas('school', fn (Builder $school): Builder => $school->where('name', 'like', "%{$search}%"));
            }))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->limit(50)
            ->get(['id', 'school_id', 'display_name', 'first_name', 'last_name', 'email', 'sport'])
            ->mapWithKeys(fn (Coach $coach): array => [
                (string) $coach->getKey() => $this->formatCoachLabel($coach),
            ])
            ->all();
    }

    protected function coachLabel(mixed $value): ?string
    {
        $coach = Coach::query()->with('school:id,name')->find((int) $value);
        return $coach ? $this->formatCoachLabel($coach) : null;
    }

    protected function formatCoachLabel(Coach $coach): string
    {
        $name = trim((string) ($coach->display_name ?: ($coach->first_name . ' ' . $coach->last_name)));
        $meta = collect([
            $coach->school?->name,
            $coach->sport ? Str::headline($coach->sport) : null,
            $coach->email,
        ])->filter()->implode(' · ');

        return $name . ($meta ? ' — ' . $meta : '');
    }

    protected function userOptions(string $search): array
    {
        return $this->userQuery($search)
            ->limit(50)
            ->get(['id', 'first_name', 'last_name', 'email', 'sport'])
            ->mapWithKeys(fn (User $user): array => [
                (string) $user->getKey() => $this->formatUserLabel($user),
            ])
            ->all();
    }

    protected function userLabels(array $values): array
    {
        $ids = collect($values)
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values();

        return User::query()
            ->whereIn('id', $ids)
            ->get(['id', 'first_name', 'last_name', 'email', 'sport'])
            ->mapWithKeys(fn (User $user): array => [
                (string) $user->getKey() => $this->formatUserLabel($user),
            ])
            ->all();
    }

    protected function userQuery(string $search = ''): Builder
    {
        $search = trim($search);

        return User::query()
            ->whereDoesntHave('roles', fn (Builder $roles): Builder => $roles->whereIn('name', [
                'Superadmin', 'superadmin', 'Super Admin', 'Admin', 'admin', 'Administrator',
            ]))
            ->when($search !== '', fn (Builder $query): Builder => $query->where(function (Builder $query) use ($search): void {
                $query->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('personal_email', 'like', "%{$search}%");
            }))
            ->orderBy('first_name')
            ->orderBy('last_name');
    }

    protected function formatUserLabel(User $user): string
    {
        $name = trim((string) ($user->first_name . ' ' . $user->last_name));
        $sport = filled($user->sport) ? Str::headline((string) $user->sport) : null;
        $meta = collect([$user->email, $sport])->filter()->implode(' · ');

        return ($name !== '' ? $name : ('User #' . $user->getKey())) . ($meta ? ' — ' . $meta : '');
    }

    protected function canManageExclusivity(): bool
    {
        $user = auth()->user();

        return $user
            && method_exists($user, 'hasAnyRole')
            && $user->hasAnyRole([
                'Superadmin', 'superadmin', 'Super Admin',
                'Admin', 'admin', 'Administrator',
            ]);
    }
}