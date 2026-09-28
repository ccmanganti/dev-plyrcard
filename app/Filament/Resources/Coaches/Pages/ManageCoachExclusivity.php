<?php

namespace App\Filament\Resources\Coaches\Pages;

use App\Filament\Resources\Coaches\CoachResource;
use App\Models\Coach;
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
use Illuminate\Support\Str;

class ManageCoachExclusivity extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $resource = CoachResource::class;
    protected string $view = 'filament.resources.coaches.pages.manage-coach-exclusivity';

    public ?array $data = [];

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
            'user_ids' => [],
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
                                $set('user_ids', []);
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

                        Select::make('user_ids')
                            ->label('Users who can see it')
                            ->placeholder('Search users by name or email')
                            ->multiple()
                            ->searchable()
                            ->preload(false)
                            ->required()
                            ->minItems(1)
                            ->helperText('If a target has an exclusivity rule, every user not selected here will not see it in Recruiting Center.')
                            ->getSearchResultsUsing(fn (string $search): array => $this->userOptions($search))
                            ->getOptionLabelsUsing(fn (array $values): array => $this->userLabels($values))
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public function save(): void
    {
        abort_unless($this->canManageExclusivity(), 403);

        $data = $this->form->getState();
        $type = (string) ($data['target_type'] ?? 'school');
        $userIds = (array) ($data['user_ids'] ?? []);
        $service = app(RecruitingVisibilityService::class);

        try {
            if ($type === 'coach') {
                $coachId = (int) ($data['coach_id'] ?? 0);
                if ($coachId <= 0) {
                    $this->addError('data.coach_id', 'Choose a coach.');
                    return;
                }

                $service->syncCoach($coachId, $userIds);
                $targetLabel = $this->coachLabel($coachId) ?: 'Coach';
            } else {
                $schoolId = (int) ($data['school_id'] ?? 0);
                if ($schoolId <= 0) {
                    $this->addError('data.school_id', 'Choose a school.');
                    return;
                }

                $service->syncSchool($schoolId, $userIds);
                $targetLabel = $this->schoolLabel($schoolId) ?: 'School';
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
            ->title('Exclusivity saved')
            ->body($targetLabel . ' is now only visible to the selected users.')
            ->success()
            ->send();

        $this->form->fill([
            'target_type' => $type,
            'school_id' => null,
            'coach_id' => null,
            'user_ids' => [],
        ]);
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
            'user_ids' => $service->assignedUserIds($type, $targetId),
        ]);

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
