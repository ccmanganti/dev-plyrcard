<?php

namespace App\Filament\Resources\Clubs;

use App\Filament\Clusters\Organizations;
use App\Filament\Resources\Clubs\Pages\CreateClub;
use App\Filament\Resources\Clubs\Pages\EditClub;
use App\Filament\Resources\Clubs\Pages\ListClubs;
use App\Filament\Resources\Clubs\Pages\ViewClub;
use App\Mail\CoachAccountCreatedMail;
use App\Models\Club;
use App\Models\ClubLeague;
use App\Models\Conference;
use App\Models\League;
use App\Models\TeamManagerAssignment;
use App\Models\User;
use App\Support\PhoneFormatter;
use BackedEnum;
use Filament\Actions\ActionGroup;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Schema as DatabaseSchema;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use STS\FilamentImpersonate\Actions\Impersonate as ImpersonateCoachAction;
use UnitEnum;

class ClubResource extends Resource
{
    protected static ?string $model = Club::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;
    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::ShieldCheck;
    protected static ?string $cluster = Organizations::class;
    protected static ?int $navigationSort = 1;
    protected static ?string $recordTitleAttribute = 'name';

    public static function genderOptions(): array
    {
        return ['male' => 'Male', 'female' => 'Female'];
    }

    public static function sportOptions(): array
    {
        return [
            'basketball' => 'Basketball', 'volleyball' => 'Volleyball', 'football' => 'Football', 'baseball' => 'Baseball',
            'softball' => 'Softball', 'soccer' => 'Soccer', 'tennis' => 'Tennis', 'badminton' => 'Badminton',
            'table_tennis' => 'Table Tennis', 'track_and_field' => 'Track and Field', 'swimming' => 'Swimming',
            'boxing' => 'Boxing', 'martial_arts' => 'Martial Arts',
        ];
    }

    protected static function normalizeGender(?string $gender): ?string
    {
        $gender = strtolower(trim((string) $gender));
        return match ($gender) {
            'female', 'girls', 'girl', 'women', 'woman', 'womens' => 'female',
            'male', 'boys', 'boy', 'men', 'man', 'mens' => 'male',
            default => null,
        };
    }

    protected static function genderShortLabel(?string $gender): string
    {
        return match (static::normalizeGender($gender)) {
            'male' => 'Boys', 'female' => 'Girls', default => '',
        };
    }

    protected static function programGenders($program): array
    {
        $programGenders = collect($program->genders ?? [])->map(fn ($gender) => static::normalizeGender($gender))->filter()->unique()->values();
        if ($programGenders->isNotEmpty()) return $programGenders->all();
        return collect($program->league?->genders ?? [])->map(fn ($gender) => static::normalizeGender($gender))->filter()->unique()->values()->all();
    }

    protected static function labelSport(?string $sport): string
    {
        return filled($sport) ? Str::of($sport)->replace('_', ' ')->title()->toString() : '-';
    }

    protected static function applyCanonicalClubFilter($query)
    {
        if (DatabaseSchema::hasColumn('clubs', 'canonical_club_id')) $query->whereNull('canonical_club_id');
        return $query;
    }

    protected static function applyCanonicalLeagueFilter($query)
    {
        if (DatabaseSchema::hasColumn('leagues', 'canonical_league_id')) $query->whereNull('canonical_league_id');
        return $query;
    }

    protected static function applyActiveProgramFilter($query)
    {
        if (DatabaseSchema::hasColumn('club_leagues', 'canonical_club_league_id')) $query->whereNull('canonical_club_league_id');
        if (DatabaseSchema::hasColumn('club_leagues', 'is_active')) $query->where('is_active', true);
        return $query;
    }

    protected static function canonicalLeagueOptions(): array
    {
        return static::applyCanonicalLeagueFilter(League::query())->orderBy('name')->get()->mapWithKeys(function (League $league): array {
            $genders = collect($league->genders ?? [])->map(fn ($gender) => static::genderShortLabel($gender))->filter()->unique()->implode('/');
            $label = $league->name;
            if ($genders !== '') $label .= " ({$genders})";
            if (filled($league->sport)) $label .= ' - ' . static::labelSport($league->sport);
            return [(string) $league->id => $label];
        })->all();
    }

    protected static function getActiveProgramRows(Club $record)
    {
        return $record->clubLeagues()->with('league')->tap(fn ($query) => static::applyActiveProgramFilter($query))->whereHas('league', fn ($query) => static::applyCanonicalLeagueFilter($query))->orderBy('sort_order')->orderBy('id')->get()->filter(fn ($program) => filled($program->league?->name))->unique(fn ($program) => implode('|', [Str::of((string) $program->league?->name)->lower()->squish()->toString(), collect(static::programGenders($program))->sort()->implode(','), $program->sport ?: $program->league?->sport]))->values();
    }

    protected static function sportsSummary(Club $record): string
    {
        $sports = static::getActiveProgramRows($record)->map(fn ($program) => $program->sport ?: $program->league?->sport)->filter()->map(fn ($sport) => static::labelSport($sport))->unique()->values();
        return $sports->isNotEmpty() ? $sports->implode(', ') : '-';
    }

    protected static function isSuperadminNavigationUser(): bool
    {
        $user = auth()->user();
        return $user && method_exists($user, 'hasRole') && ($user->hasRole('Superadmin') || $user->hasRole('superadmin') || $user->hasRole('Super Admin'));
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::isSuperadminNavigationUser();
    }


    public static function ageGroupOptions(): array
    {
        return collect(config('plyrcard.age_groups', [
            'u13' => 'U13',
            'u14' => 'U14',
            'u15' => 'U15',
            'u16' => 'U16',
            'u17' => 'U17',
            'u18' => 'U18',
            'u19' => 'U19',
        ]))->mapWithKeys(fn ($label) => [(string) $label => (string) $label])->all();
    }

    protected static function coachProgramLogicalKey(ClubLeague $program): string
    {
        $leagueName = str((string) ($program->league?->name ?: ''))
            ->lower()
            ->squish()
            ->toString();

        $sport = str((string) ($program->sport ?: $program->league?->sport ?: ''))
            ->lower()
            ->replace('_', ' ')
            ->squish()
            ->toString();

        $genders = collect($program->genders ?? $program->league?->genders ?? [])
            ->map(fn ($gender) => strtolower(trim((string) $gender)))
            ->map(fn ($gender) => match ($gender) {
                'girls', 'girl', 'women', 'woman', 'female' => 'female',
                'boys', 'boy', 'men', 'man', 'male' => 'male',
                default => $gender,
            })
            ->filter()
            ->unique()
            ->sort()
            ->implode('/');

        return implode('|', [
            $leagueName,
            $sport,
            $genders,
        ]);
    }

    protected static function coachProgramLabel(ClubLeague $program): string
    {
        $sport = str((string) ($program->sport ?: $program->league?->sport ?: ''))
            ->replace('_', ' ')
            ->lower()
            ->squish()
            ->toString();

        $genders = collect($program->genders ?? $program->league?->genders ?? [])
            ->map(fn ($gender) => strtolower(trim((string) $gender)))
            ->map(fn ($gender) => match ($gender) {
                'girls', 'girl', 'women', 'woman', 'female' => 'female',
                'boys', 'boy', 'men', 'man', 'male' => 'male',
                default => $gender,
            })
            ->filter()
            ->unique()
            ->sort()
            ->implode('/');

        return collect([
            $program->league?->name,
            filled($sport) ? $sport : null,
            filled($genders) ? $genders : null,
        ])->filter()->implode(' • ');
    }

    protected static function coachProgramOptions(?Club $record): array
    {
        if (! $record?->exists) {
            return [];
        }

        return ClubLeague::query()
            ->with('league')
            ->where('club_id', $record->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->filter(fn (ClubLeague $program): bool => filled($program->league?->name))
            ->unique(fn (ClubLeague $program): string => static::coachProgramLogicalKey($program))
            ->values()
            ->mapWithKeys(fn (ClubLeague $program): array => [
                (string) $program->id => static::coachProgramLabel($program),
            ])
            ->all();
    }

    protected static function coachTeamAssignmentOptions(?Club $record, array $selectedClubLeagueIds): array
    {
        if (! $record?->exists) {
            return [];
        }

        $selectedClubLeagueIds = collect($selectedClubLeagueIds)
            ->filter()
            ->map(fn ($value) => (string) $value)
            ->values();

        if ($selectedClubLeagueIds->isEmpty()) {
            return [];
        }

        $programs = ClubLeague::query()
            ->with('league')
            ->where('club_id', $record->id)
            ->where('is_active', true)
            ->whereIn('id', $selectedClubLeagueIds->all())
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->filter(fn (ClubLeague $program): bool => filled($program->league?->name))
            ->unique(fn (ClubLeague $program): string => static::coachProgramLogicalKey($program))
            ->values();

        return $programs
            ->mapWithKeys(function (ClubLeague $program): array {
                return [
                    static::coachProgramLabel($program) => collect(static::ageGroupOptions())
                        ->mapWithKeys(fn (string $ageGroup): array => [
                            $program->id . '|' . strtoupper($ageGroup) => strtoupper($ageGroup),
                        ])
                        ->all(),
                ];
            })
            ->all();
    }

    protected static function createCoachAccountFromClub(Club $record, array $data): void
    {
        $role = $data['manager_role'] ?? 'Club Manager';
        Role::findOrCreate($role);

        $plainPassword = (string) $data['password'];

        $coach = User::create([
            'first_name' => trim((string) ($data['first_name'] ?? '')),
            'last_name' => trim((string) ($data['last_name'] ?? '')),
            'title' => $data['title'] ?? null,
            'email' => strtolower(trim((string) $data['email'])),
            'phone' => PhoneFormatter::normalize($data['phone'] ?? null),
            'club_id' => $record->id,
            'password' => Hash::make($plainPassword),
            'club_manager_created_at' => now(),
        ]);

        $coach->syncRoles([$role]);

        if ($role === 'Team Manager') {
            foreach (collect($data['assigned_team_keys'] ?? [])->filter()->unique() as $assignmentKey) {
                [$clubLeagueId, $teamName] = array_pad(explode('|', (string) $assignmentKey, 2), 2, null);

                if (blank($clubLeagueId) || blank($teamName)) {
                    continue;
                }

                $clubLeague = ClubLeague::query()
                    ->where('club_id', $record->id)
                    ->whereKey($clubLeagueId)
                    ->where('is_active', true)
                    ->first();

                if (! $clubLeague) {
                    continue;
                }

                TeamManagerAssignment::updateOrCreate([
                    'user_id' => $coach->id,
                    'club_id' => $record->id,
                    'club_league_id' => $clubLeague->id,
                    'team_name' => strtoupper(trim($teamName)),
                ], [
                    'league_id' => $clubLeague->league_id,
                ]);
            }
        }

        Mail::to($coach->email)->send(new CoachAccountCreatedMail(
            coach: $coach,
            plainPassword: $plainPassword,
            loginUrl: url('/admin/login'),
            accessTitle: $role,
        ));

        $coach->forceFill([
            'coach_account_credentials_sent_at' => now(),
        ])->save();
    }


    protected static function coachSelectOptions(?Club $record): array
    {
        if (! $record?->exists) {
            return [];
        }

        return User::query()
            ->where('club_id', $record->id)
            ->whereHas('roles', fn (Builder $query) => $query->whereIn('name', ['Club Manager', 'Team Manager']))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get()
            ->mapWithKeys(fn (User $coach): array => [
                $coach->id => trim(($coach->first_name ?? '') . ' ' . ($coach->last_name ?? '')) ?: $coach->email,
            ])
            ->all();
    }

    protected static function updateCoachAccountFromClub(?Club $record, array $data): void
    {
        if (! $record?->exists) {
            return;
        }

        $coach = User::query()
            ->where('club_id', $record->id)
            ->whereKey($data['coach_id'] ?? null)
            ->first();

        if (! $coach) {
            return;
        }

        $coach->forceFill([
            'first_name' => trim((string) ($data['first_name'] ?? '')),
            'last_name' => trim((string) ($data['last_name'] ?? '')),
            'title' => $data['title'] ?? null,
            'email' => strtolower(trim((string) ($data['email'] ?? ''))),
            'phone' => PhoneFormatter::normalize($data['phone'] ?? null),
        ])->save();

        $role = $data['manager_role'] ?? 'Club Manager';
        Role::findOrCreate($role);
        $coach->syncRoles([$role]);

        if ($role === 'Club Manager') {
            TeamManagerAssignment::query()->where('user_id', $coach->id)->delete();
        }
    }

    protected static function deleteCoachAccountFromClub(?Club $record, array $data): void
    {
        if (! $record?->exists) {
            return;
        }

        $coach = User::query()
            ->where('club_id', $record->id)
            ->whereKey($data['coach_id'] ?? null)
            ->first();

        if (! $coach) {
            return;
        }

        TeamManagerAssignment::query()->where('user_id', $coach->id)->delete();
        $coach->delete();
    }

    protected static function sendCoachPasswordFromClub(?Club $record, array $data): void
    {
        if (! $record?->exists) {
            return;
        }

        $coach = User::query()
            ->with('roles')
            ->where('club_id', $record->id)
            ->whereKey($data['coach_id'] ?? null)
            ->first();

        if (! $coach) {
            return;
        }

        $coach->forceFill([
            'password' => Hash::make($data['password']),
        ])->save();

        Mail::to($coach->email)->send(new CoachAccountCreatedMail(
            coach: $coach,
            plainPassword: $data['password'],
            loginUrl: url('/admin/login'),
            accessTitle: $coach->roles->pluck('name')->implode(', ') ?: 'Coach',
        ));

        $coach->forceFill([
            'coach_account_credentials_sent_at' => now(),
        ])->save();
    }


    public static function coachAccountsFormState(Club $record): array
    {
        return User::query()
            ->with(['roles'])
            ->where('club_id', $record->id)
            ->whereHas('roles', fn (Builder $query) => $query->whereIn('name', [
                'Club Manager',
                'Team Manager',
            ]))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get()
            ->map(function (User $coach) use ($record): array {
                $assignmentKeys = TeamManagerAssignment::query()
                    ->where('user_id', $coach->id)
                    ->where('club_id', $record->id)
                    ->get()
                    ->map(fn (TeamManagerAssignment $assignment): string => $assignment->club_league_id . '|' . strtoupper((string) $assignment->team_name))
                    ->unique()
                    ->values()
                    ->all();

                $programIds = collect($assignmentKeys)
                    ->map(fn (string $key): ?string => explode('|', $key, 2)[0] ?? null)
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();

                return [
                    'id' => $coach->id,
                    'first_name' => $coach->first_name,
                    'last_name' => $coach->last_name,
                    'title' => $coach->title,
                    'email' => $coach->email,
                    'password' => null,
                    'phone' => $coach->phone,
                    'manager_role' => $coach->hasRole('Team Manager') ? 'Team Manager' : 'Club Manager',
                    'assigned_club_league_ids' => $programIds,
                    'assigned_team_keys' => $assignmentKeys,
                ];
            })
            ->values()
            ->all();
    }

    public static function syncCoachAccountsFromForm(Club $record, array $coachRows): void
    {
        $submittedIds = collect($coachRows)
            ->pluck('id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        User::query()
            ->where('club_id', $record->id)
            ->whereHas('roles', fn (Builder $query) => $query->whereIn('name', [
                'Club Manager',
                'Team Manager',
            ]))
            ->whereNotIn('id', $submittedIds)
            ->get()
            ->each(function (User $coach): void {
                if ($coach->hasRole('Superadmin')) {
                    return;
                }

                TeamManagerAssignment::query()->where('user_id', $coach->id)->delete();
                $coach->delete();
            });

        foreach ($coachRows as $row) {
            $role = $row['manager_role'] ?? 'Club Manager';

            if (! in_array($role, ['Club Manager', 'Team Manager'], true)) {
                $role = 'Club Manager';
            }

            Role::findOrCreate($role);

            $coach = filled($row['id'] ?? null)
                ? User::query()->where('club_id', $record->id)->whereKey($row['id'])->first()
                : null;

            $isNew = ! $coach;
            $plainPassword = filled($row['password'] ?? null)
                ? (string) $row['password']
                : null;

            if (! $coach) {
                $plainPassword ??= Str::password(12);

                $coach = new User([
                    'password' => Hash::make($plainPassword),
                    'club_manager_created_at' => now(),
                ]);
            } elseif ($plainPassword) {
                $coach->password = Hash::make($plainPassword);
            }

            $coach->forceFill([
                'first_name' => trim((string) ($row['first_name'] ?? '')),
                'last_name' => trim((string) ($row['last_name'] ?? '')),
                'title' => $row['title'] ?? null,
                'email' => strtolower(trim((string) ($row['email'] ?? ''))),
                'phone' => PhoneFormatter::normalize($row['phone'] ?? null),
                'club_id' => $record->id,
            ])->save();

            $coach->syncRoles([$role]);

            TeamManagerAssignment::query()->where('user_id', $coach->id)->delete();

            if ($role === 'Team Manager') {
                foreach (collect($row['assigned_team_keys'] ?? [])->filter()->unique() as $assignmentKey) {
                    [$clubLeagueId, $teamName] = array_pad(explode('|', (string) $assignmentKey, 2), 2, null);

                    if (blank($clubLeagueId) || blank($teamName)) {
                        continue;
                    }

                    $clubLeague = ClubLeague::query()
                        ->where('club_id', $record->id)
                        ->whereKey($clubLeagueId)
                        ->where('is_active', true)
                        ->first();

                    if (! $clubLeague) {
                        continue;
                    }

                    TeamManagerAssignment::updateOrCreate([
                        'user_id' => $coach->id,
                        'club_id' => $record->id,
                        'club_league_id' => $clubLeague->id,
                        'team_name' => strtoupper(trim($teamName)),
                    ], [
                        'league_id' => $clubLeague->league_id,
                    ]);
                }
            }

            if ($plainPassword) {
                Mail::to($coach->email)->send(new CoachAccountCreatedMail(
                    coach: $coach,
                    plainPassword: $plainPassword,
                    loginUrl: url('/admin/login'),
                    accessTitle: $role,
                ));

                $coach->forceFill([
                    'coach_account_credentials_sent_at' => now(),
                ])->save();
            }
        }
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Tabs::make('Club Setup')->persistTabInQueryString()->tabs([
                Tab::make('Basic Information')->icon(Heroicon::OutlinedInformationCircle)->schema([
                    Section::make('Club Details')->columns(2)->schema([
                        TextInput::make('name')->label('Club Name')->required()->maxLength(255),
                        Select::make('conference_id')->label('Conference')->options(fn (): array => Conference::query()->orderBy('name')->pluck('name', 'id')->all())->searchable()->preload()->nullable(),
                        TextInput::make('city')->label('City')->maxLength(255),
                        TextInput::make('state')->label('State')->maxLength(255),
                        FileUpload::make('logo')->label('Club Logo')->image()->downloadable()->imageEditor()->disk('public')->directory('club-logos')->visibility('public'),
                        FileUpload::make('background_image')->label('Featured Background Image')->helperText('Optional. If empty, the club landing page uses images/PLYRCARD-SITE.jpg.')->image()->downloadable()->imageEditor()->disk('public')->directory('club-landing')->visibility('public'),
                        ColorPicker::make('primary_color')->label('Primary Color'),
                        ColorPicker::make('secondary_color')->label('Secondary Color'),
                    ]),
                ]),
                Tab::make('Program Leagues')->icon(Heroicon::OutlinedSquares2x2)->schema([
                    Section::make('Active Club Programs')->description('Manage only the active canonical programs for this club. Legacy duplicate programs are intentionally hidden.')->schema([
                        Repeater::make('clubLeagues')->label('Program Leagues')->relationship(name: 'clubLeagues', modifyQueryUsing: fn ($query) => static::applyActiveProgramFilter($query)->whereHas('league', fn ($leagueQuery) => static::applyCanonicalLeagueFilter($leagueQuery))->orderBy('sort_order')->orderBy('id'))->addActionLabel('Add Program League')->reorderable()->collapsed()->itemLabel(function (array $state): ?string {
                            $leagueName = filled($state['league_id'] ?? null) ? League::query()->whereKey($state['league_id'])->value('name') : 'Program League';
                            $genders = collect($state['genders'] ?? [])->map(fn ($gender) => static::genderShortLabel($gender))->filter()->unique()->values();
                            $sport = static::labelSport($state['sport'] ?? null);
                            return collect([$leagueName, $genders->isNotEmpty() ? $genders->implode('/') : null, $sport !== '-' ? $sport : null])->filter()->implode(' • ');
                        })->schema([
                            Select::make('league_id')->label('League')->options(fn (): array => static::canonicalLeagueOptions())->searchable()->preload()->required(),
                            Select::make('genders')->label('Genders Offered')->options(static::genderOptions())->multiple()->searchable()->preload()->required(),
                            Select::make('sport')->label('Sport')->options(static::sportOptions())->searchable()->helperText('Optional. Leave empty to use the selected league sport.'),
                            TextInput::make('sort_order')->label('Sort')->numeric()->default(0),
                            Toggle::make('is_active')->label('Active')->default(true),
                        ])->columns(2),
                    ]),
                ]),
                Tab::make('Website Publishing')->icon(Heroicon::OutlinedGlobeAlt)->schema([
                    Section::make('Publishing')->columns(3)->schema([
                        Toggle::make('has_landing_page')->label('Enable Club Page')->default(false),
                        Toggle::make('landing_page_is_published')->label('Published')->default(false),
                        TextInput::make('landing_page_slug')->label('URI Slug')->placeholder('club-name')->helperText('Used for /clubs/{slug}. Leave blank to auto-generate from the club name.')->unique(ignoreRecord: true)->maxLength(255),
                    ]),
                ]),
                Tab::make('Contact / Address')->icon(Heroicon::OutlinedMapPin)->schema([
                    Section::make('Basic Address & Contact')->columns(2)->schema([
                        TextInput::make('contact_info.address')->label('Address')->maxLength(255),
                        TextInput::make('contact_info.maps_url')->label('Google Maps URL')->url()->maxLength(255),
                        TextInput::make('contact_info.phone')->label('Phone')->tel()->maxLength(255),
                        TextInput::make('contact_info.email')->label('Email')->email()->maxLength(255),
                        Textarea::make('landing_page_content')->label('Club Description')->helperText('Shown in the footer and basic club description areas.')->rows(4)->columnSpanFull(),
                    ]),
                ]),
                        Tab::make('Coaches')
                            ->icon(Heroicon::OutlinedUserGroup)
                            ->schema([
                                Section::make('Coach Accounts')
                                    ->schema([
                                        Repeater::make('coach_accounts')
                                            ->label('')
                                            ->addActionLabel('Add Coach')
                                            ->reorderable(false)
                                            ->collapsible()
                                            ->collapsed(false)
                                            ->itemLabel(fn (array $state): ?string => trim(($state['first_name'] ?? '') . ' ' . ($state['last_name'] ?? '')) ?: 'Coach')
                                            ->extraItemActions([
                                                ImpersonateCoachAction::make('impersonateCoach')
                                                    ->label('Impersonate')
                                                    ->icon('heroicon-m-arrow-right-on-rectangle')
                                                    ->tooltip('Impersonate')
                                                    ->record(function (array $arguments, Repeater $component): ?User {
                                                        $state = method_exists($component, 'getRawItemState')
                                                            ? $component->getRawItemState($arguments['item'])
                                                            : ($component->getState()[$arguments['item']] ?? []);

                                                        if (blank($state['id'] ?? null)) {
                                                            return null;
                                                        }

                                                        return User::query()
                                                            ->whereKey($state['id'])
                                                            ->where('club_id', $component->getContainer()->getLivewire()->record?->id)
                                                            ->first();
                                                    })
                                                    ->visible(function (array $arguments, Repeater $component): bool {
                                                        $state = method_exists($component, 'getRawItemState')
                                                            ? $component->getRawItemState($arguments['item'])
                                                            : ($component->getState()[$arguments['item']] ?? []);

                                                        return filled($state['id'] ?? null)
                                                            && auth()->id() !== (int) $state['id']
                                                            && (auth()->user()?->hasRole('Superadmin') ?? false);
                                                    })
                                                    ->redirectTo('/admin'),
                                            ])
                                            ->schema([
                                                Hidden::make('id'),

                                                Section::make('Coach')
                                                    ->columns(2)
                                                    ->schema([
                                                        TextInput::make('first_name')
                                                            ->label('First Name')
                                                            ->required()
                                                            ->maxLength(255),

                                                        TextInput::make('last_name')
                                                            ->label('Last Name')
                                                            ->required()
                                                            ->maxLength(255),

                                                        TextInput::make('title')
                                                            ->label('Title')
                                                            ->placeholder('Head Coach, Assistant Coach, Director, etc.')
                                                            ->maxLength(255),

                                                        TextInput::make('email')
                                                            ->label('Email')
                                                            ->email()
                                                            ->required()
                                                            ->maxLength(255),


                                                        TextInput::make('password')
                                                            ->label(fn (Get $get): string => filled($get('id')) ? 'New Password' : 'Password')
                                                            ->password()
                                                            ->revealable()
                                                            ->required(fn (Get $get): bool => blank($get('id')))
                                                            ->minLength(8)
                                                            ->maxLength(255)
                                                            ->helperText(fn (Get $get): string => filled($get('id'))
                                                                ? 'Leave blank to keep the existing password. Enter a new password to reset and email credentials.'
                                                                : 'Required for the coach login account. This password will be emailed to the coach.'),

                                                        TextInput::make('phone')
                                                            ->label('Phone')
                                                            ->tel()
                                                            ->placeholder('(555) 000-0000')
                                                            ->regex('/^([0-9\-\+\(\)\s]{7,20})$/')
                                                            ->validationMessages([
                                                                'regex' => 'Enter a valid phone number.',
                                                            ])
                                                            ->maxLength(255),
                                                    ]),

                                                Section::make('Assignments')
                                                    ->columns(2)
                                                    ->schema([
                                                        Select::make('manager_role')
                                                            ->label('Access Level')
                                                            ->options([
                                                                'Club Manager' => 'Club Manager',
                                                                'Team Manager' => 'Team Manager',
                                                            ])
                                                            ->default('Club Manager')
                                                            ->required()
                                                            ->live()
                                                            ->afterStateUpdated(function (Set $set, ?string $state): void {
                                                                if ($state !== 'Team Manager') {
                                                                    $set('assigned_club_league_ids', []);
                                                                    $set('assigned_team_keys', []);
                                                                }
                                                            }),

                                                        Select::make('assigned_club_league_ids')
                                                            ->label('Program / League')
                                                            ->multiple()
                                                            ->options(fn (?Club $record): array => static::coachProgramOptions($record))
                                                            ->searchable()
                                                            ->preload()
                                                            ->live()
                                                            ->visible(fn (Get $get): bool => $get('manager_role') === 'Team Manager')
                                                            ->required(fn (Get $get): bool => $get('manager_role') === 'Team Manager')
                                                            ->helperText('Pick each program this Team Manager can access. Duplicate programs are hidden.')
                                                            ->afterStateUpdated(fn (Set $set): mixed => $set('assigned_team_keys', [])),

                                                        Select::make('assigned_team_keys')
                                                            ->label('Teams / Age Groups')
                                                            ->multiple()
                                                            ->options(fn (Get $get, ?Club $record): array => static::coachTeamAssignmentOptions($record, $get('assigned_club_league_ids') ?? []))
                                                            ->searchable()
                                                            ->preload()
                                                            ->visible(fn (Get $get): bool => $get('manager_role') === 'Team Manager')
                                                            ->required(fn (Get $get): bool => $get('manager_role') === 'Team Manager')
                                                            ->disabled(fn (Get $get): bool => blank($get('assigned_club_league_ids')))
                                                            ->placeholder(fn (Get $get): string => blank($get('assigned_club_league_ids')) ? 'Select programs first' : 'Select teams under each program')
                                                            ->helperText('Teams are grouped by program, so U13 can be selected separately for each selected league.'),
                                                    ]),
                                            ])
                                            ->columns(1),
                                    ]),
                            ]),

Tab::make('Sponsors')->icon(Heroicon::OutlinedStar)->schema([
                    Section::make('Sponsors / Partners')->schema([
                        Repeater::make('sponsors_partners')->label('Sponsors / Partners')->addActionLabel('Add Sponsor')->reorderable()->collapsed()->itemLabel(fn (array $state): ?string => $state['name'] ?? 'Sponsor')->schema([
                            TextInput::make('name')->label('Sponsor Name')->maxLength(255),
                            TextInput::make('url')->label('Sponsor URL')->url()->maxLength(255),
                        ])->columns(2),
                    ]),
                ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->modifyQueryUsing(fn (Builder $query) => static::applyCanonicalClubFilter($query)->with(['clubLeagues' => fn ($programQuery) => static::applyActiveProgramFilter($programQuery)->whereHas('league', fn ($leagueQuery) => static::applyCanonicalLeagueFilter($leagueQuery))->with('league')->orderBy('sort_order')->orderBy('id')]))->columns([
            ImageColumn::make('logo')->label('Logo')->disk('public')->height(42)->circular(),
            TextColumn::make('name')->label('Club')->searchable()->sortable()->description(fn (Club $record): ?string => trim(collect([$record->city, $record->state])->filter()->implode(', ')) ?: null),
            TextColumn::make('sports_summary')->label('Sports')->badge()->state(fn (Club $record): string => static::sportsSummary($record))->toggleable(),
            TextColumn::make('coach_accounts_count')->label('Coaches')->state(fn (Club $record): int => User::query()->where('club_id', $record->id)->whereHas('roles', fn (Builder $query) => $query->whereIn('name', ['Club Manager', 'Team Manager']))->count())->badge(),
            IconColumn::make('has_landing_page')->label('Page')->boolean()->toggleable(isToggledHiddenByDefault: true),
            IconColumn::make('landing_page_is_published')->label('Live')->boolean()->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('updated_at')->label('Updated')->since()->sortable()->toggleable(isToggledHiddenByDefault: true),
        ])->filters([
            TrashedFilter::make(),
            SelectFilter::make('program_league')->label('Program League')->options(fn (): array => static::canonicalLeagueOptions())->searchable()->query(function (Builder $query, array $data): Builder { $value = $data['value'] ?? null; return blank($value) ? $query : $query->whereHas('clubLeagues', function (Builder $programQuery) use ($value) { static::applyActiveProgramFilter($programQuery)->where('league_id', $value); }); }),
            SelectFilter::make('program_gender')->label('Program Gender')->options(static::genderOptions())->multiple()->query(function (Builder $query, array $data): Builder { $values = collect($data['values'] ?? [])->map(fn ($gender) => static::normalizeGender($gender))->filter()->values(); if ($values->isEmpty()) return $query; return $query->whereHas('clubLeagues', function (Builder $programQuery) use ($values) { static::applyActiveProgramFilter($programQuery); $programQuery->where(function (Builder $nested) use ($values) { foreach ($values as $gender) $nested->orWhereJsonContains('genders', $gender); }); }); }),
            TernaryFilter::make('landing_page_is_published')->label('Published'),
        ])->actions([
            ActionGroup::make([ViewAction::make(), EditAction::make(), DeleteAction::make(), RestoreAction::make(), ForceDeleteAction::make()]),
        ])->recordUrl(fn (Club $record): string => static::getUrl('edit', ['record' => $record]));
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return ['index' => ListClubs::route('/'), 'create' => CreateClub::route('/create'), 'view' => ViewClub::route('/{record}'), 'edit' => EditClub::route('/{record}/edit')];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}   