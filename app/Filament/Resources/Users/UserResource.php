<?php

namespace App\Filament\Resources\Users;

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Pages\ViewUser;
use App\Models\Club;
use App\Models\League;
use App\Models\NationalTeam;
use App\Models\School;
use App\Models\Team;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use STS\FilamentImpersonate\Actions\Impersonate;
use UnitEnum;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;
    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::UserGroup;
    protected static string|UnitEnum|null $navigationGroup = 'Users & Permissions';
    protected static ?string $recordTitleAttribute = 'first_name';

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && auth()->user()->hasRole('Superadmin');
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getModel()::count();
    }

    public static function getSportOptions(): array
    {
        return [
            'basketball' => 'Basketball',
            'volleyball' => 'Volleyball',
            'football' => 'Football',
            'baseball' => 'Baseball',
            'softball' => 'Softball',
            'soccer' => 'Soccer',
            'tennis' => 'Tennis',
            'badminton' => 'Badminton',
            'table_tennis' => 'Table Tennis',
            'track_and_field' => 'Track and Field',
            'swimming' => 'Swimming',
            'boxing' => 'Boxing',
            'martial_arts' => 'Martial Arts',
        ];
    }

    public static function getGenderOptions(): array
    {
        return [
            'male' => 'Male',
            'female' => 'Female',
        ];
    }

    protected static function getSchoolOptions(): array
    {
        return ['__new__' => 'Add New'] + School::query()
            ->orderBy('name')
            ->pluck('name', 'id')
            ->mapWithKeys(fn ($name, $id) => [(string) $id => $name])
            ->all();
    }

    protected static function getNationalTeamOptions(): array
    {
        return ['__new__' => 'Add New'] + NationalTeam::query()
            ->orderBy('name')
            ->pluck('name', 'id')
            ->mapWithKeys(fn ($name, $id) => [(string) $id => $name])
            ->all();
    }

    protected static function getPositionOptions(?string $sport): array
    {
        return match ($sport) {
            'basketball' => [
                'point_guard' => 'Point Guard',
                'shooting_guard' => 'Shooting Guard',
                'small_forward' => 'Small Forward',
                'power_forward' => 'Power Forward',
                'center' => 'Center',
            ],
            'volleyball' => [
                'setter' => 'Setter',
                'outside_hitter' => 'Outside Hitter',
                'opposite_hitter' => 'Opposite Hitter',
                'middle_blocker' => 'Middle Blocker',
                'libero' => 'Libero',
                'defensive_specialist' => 'Defensive Specialist',
            ],
            'football' => [
                'quarterback' => 'Quarterback',
                'running_back' => 'Running Back',
                'wide_receiver' => 'Wide Receiver',
                'tight_end' => 'Tight End',
                'offensive_line' => 'Offensive Line',
                'defensive_line' => 'Defensive Line',
                'linebacker' => 'Linebacker',
                'cornerback' => 'Cornerback',
                'safety' => 'Safety',
                'kicker' => 'Kicker',
                'punter' => 'Punter',
            ],
            'baseball' => [
                'pitcher' => 'Pitcher',
                'catcher' => 'Catcher',
                'first_base' => 'First Base',
                'second_base' => 'Second Base',
                'third_base' => 'Third Base',
                'shortstop' => 'Shortstop',
                'left_field' => 'Left Field',
                'center_field' => 'Center Field',
                'right_field' => 'Right Field',
                'designated_hitter' => 'Designated Hitter',
            ],
            'softball' => [
                'pitcher' => 'Pitcher',
                'catcher' => 'Catcher',
                'first_base' => 'First Base',
                'second_base' => 'Second Base',
                'third_base' => 'Third Base',
                'shortstop' => 'Shortstop',
                'left_field' => 'Left Field',
                'center_field' => 'Center Field',
                'right_field' => 'Right Field',
            ],
            'soccer' => [
                'goalkeeper' => 'Goalkeeper',
                'defender' => 'Defender',
                'center_back' => 'Center Back',
                'full_back' => 'Full Back',
                'wing_back' => 'Wing Back',
                'midfielder' => 'Midfielder',
                'defensive_midfielder' => 'Defensive Midfielder',
                'central_midfielder' => 'Central Midfielder',
                'attacking_midfielder' => 'Attacking Midfielder',
                'winger' => 'Winger',
                'forward' => 'Forward',
                'striker' => 'Striker',
            ],
            'tennis' => [
                'singles' => 'Singles',
                'doubles' => 'Doubles',
            ],
            'badminton' => [
                'singles' => 'Singles',
                'doubles' => 'Doubles',
                'mixed_doubles' => 'Mixed Doubles',
            ],
            'table_tennis' => [
                'singles' => 'Singles',
                'doubles' => 'Doubles',
                'mixed_doubles' => 'Mixed Doubles',
            ],
            'track_and_field' => [
                'sprinter' => 'Sprinter',
                'middle_distance' => 'Middle Distance',
                'long_distance' => 'Long Distance',
                'hurdler' => 'Hurdler',
                'jumper' => 'Jumper',
                'thrower' => 'Thrower',
                'relay_runner' => 'Relay Runner',
                'decathlete' => 'Decathlete',
                'heptathlete' => 'Heptathlete',
            ],
            'swimming' => [
                'freestyle' => 'Freestyle',
                'backstroke' => 'Backstroke',
                'breaststroke' => 'Breaststroke',
                'butterfly' => 'Butterfly',
                'individual_medley' => 'Individual Medley',
                'relay' => 'Relay',
            ],
            'boxing' => [
                'flyweight' => 'Flyweight',
                'bantamweight' => 'Bantamweight',
                'featherweight' => 'Featherweight',
                'lightweight' => 'Lightweight',
                'welterweight' => 'Welterweight',
                'middleweight' => 'Middleweight',
                'light_heavyweight' => 'Light Heavyweight',
                'heavyweight' => 'Heavyweight',
            ],
            'martial_arts' => [
                'lightweight' => 'Lightweight',
                'welterweight' => 'Welterweight',
                'middleweight' => 'Middleweight',
                'heavyweight' => 'Heavyweight',
                'striker' => 'Striker',
                'grappler' => 'Grappler',
                'all_rounder' => 'All-Rounder',
            ],
            default => [],
        };
    }

    protected static function applyGenderAndSportFilter(
        Builder $query,
        ?string $gender,
        ?string $sport,
        string $genderColumn = 'gender',
        string $sportColumn = 'sport',
    ): Builder {
        return $query
            ->when(filled($gender), fn (Builder $q) => $q->where($genderColumn, $gender))
            ->when(filled($sport), fn (Builder $q) => $q->where($sportColumn, $sport));
    }

    protected static function getLeagueOptions(?string $gender = null, ?string $sport = null, ?string $search = null): array
    {
        $query = League::query();

        static::applyGenderAndSportFilter($query, $gender, $sport);

        $query->when(
            filled($search),
            fn (Builder $q) => $q->where('name', 'like', '%' . trim($search) . '%')
        );

        return $query
            ->orderBy('name')
            ->limit(50)
            ->pluck('name', 'id')
            ->mapWithKeys(fn ($name, $id) => [(string) $id => $name])
            ->all();
    }

    protected static function getClubOptions(?string $leagueId = null, ?string $gender = null, ?string $sport = null, ?string $search = null): array
    {
        $query = Club::query();

        if (filled($leagueId)) {
            $query->where('league_id', $leagueId);
        } else {
            return [];
        }

        $query->when(
            filled($search),
            fn (Builder $q) => $q->where('name', 'like', '%' . trim($search) . '%')
        );

        return $query
            ->orderBy('name')
            ->limit(50)
            ->pluck('name', 'id')
            ->mapWithKeys(fn ($name, $id) => [(string) $id => $name])
            ->all();
    }

    protected static function getTeamOptions(?string $clubId = null, ?string $gender = null, ?string $sport = null, ?string $search = null): array
    {
        if (blank($clubId)) {
            return ['__new__' => 'Add New'];
        }

        $query = Team::query()->where('club_id', $clubId);

        $query->when(
            filled($search),
            fn (Builder $q) => $q->where('name', 'like', '%' . trim($search) . '%')
        );

        return ['__new__' => 'Add New'] + $query
            ->orderBy('name')
            ->limit(50)
            ->pluck('name', 'id')
            ->mapWithKeys(fn ($name, $id) => [(string) $id => $name])
            ->all();
    }

    public static function mutateUserFormData(array $data): array
    {
        if (filled($data['password'] ?? null)) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        if (($data['school_id'] ?? null) === '__new__' && filled($data['new_school_name'] ?? null)) {
            $school = School::create([
                'name' => trim($data['new_school_name']),
            ]);

            $data['school_id'] = $school->id;
        } elseif (($data['school_id'] ?? null) === '__new__') {
            $data['school_id'] = null;
        }

        if (($data['team_id'] ?? null) === '__new__') {
            $newLeagueName = trim((string) ($data['new_league_name'] ?? ''));
            $newClubName = trim((string) ($data['new_club_name'] ?? ''));
            $newTeamName = trim((string) ($data['new_team_name'] ?? ''));

            $league = null;
            $club = null;
            $team = null;

            if ($newLeagueName !== '') {
                $league = League::firstOrCreate(
                    ['name' => $newLeagueName],
                    ['logo' => $data['new_league_logo'] ?? null]
                );

                if (blank($league->logo) && filled($data['new_league_logo'] ?? null)) {
                    $league->logo = $data['new_league_logo'];
                    $league->save();
                }
            }

            if ($newClubName !== '') {
                $club = Club::firstOrCreate(
                    [
                        'name' => $newClubName,
                        'league_id' => $league?->id,
                    ],
                    [
                        'logo' => $data['new_club_logo'] ?? null,
                    ]
                );

                $clubNeedsSave = false;

                if ($league && $club->league_id !== $league->id) {
                    $club->league_id = $league->id;
                    $clubNeedsSave = true;
                }

                if (blank($club->logo) && filled($data['new_club_logo'] ?? null)) {
                    $club->logo = $data['new_club_logo'];
                    $clubNeedsSave = true;
                }

                if ($clubNeedsSave) {
                    $club->save();
                }
            }

            if ($newTeamName !== '') {
                $team = Team::firstOrCreate(
                    [
                        'name' => $newTeamName,
                        'club_id' => $club?->id,
                    ]
                );

                if ($club && $team->club_id !== $club->id) {
                    $team->club_id = $club->id;
                    $team->save();
                }
            }

            $data['team_name'] = $team?->name ?? null;
            $data['club_id'] = $club?->id;
            $data['league_id'] = $league?->id;
        } else {
            $selectedTeam = null;

            if (filled($data['team_id'] ?? null)) {
                $selectedTeam = Team::with('club.league')->find($data['team_id']);
            }

            $data['team_name'] = $selectedTeam?->name ?? null;
            $data['club_id'] = $selectedTeam?->club?->id ?? null;
            $data['league_id'] = $selectedTeam?->club?->league?->id ?? null;
        }

        if (($data['national_team_id'] ?? null) === '__new__' && filled($data['new_national_team_name'] ?? null)) {
            $nationalTeam = NationalTeam::create([
                'name' => trim($data['new_national_team_name']),
                'logo' => $data['new_national_team_logo'] ?? null,
            ]);

            $data['national_team_id'] = $nationalTeam->id;
        } elseif (($data['national_team_id'] ?? null) === '__new__') {
            $data['national_team_id'] = null;
        }

        unset(
            $data['password_confirmation'],
            $data['new_school_name'],
            $data['team_id'],
            $data['new_team_name'],
            $data['new_club_name'],
            $data['new_club_logo'],
            $data['new_league_name'],
            $data['new_league_logo'],
            $data['new_national_team_name'],
            $data['new_national_team_logo'],
        );

        return $data;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Hidden::make('club_id'),
            Hidden::make('league_id'),
            Hidden::make('team_name'),

            Tabs::make('user_tabs')
                ->persistTab()
                ->id('user-resource-tabs')
                ->contained(true)
                ->tabs([
                    Tab::make('Basic Info')
                        ->icon('heroicon-m-user')
                        ->schema([
                            Section::make('Personal Information')
                                ->icon('heroicon-m-user')
                                ->columns(6)
                                ->schema([
                                    TextInput::make('first_name')
                                        ->label('First Name')
                                        ->prefixIcon('heroicon-m-user')
                                        ->placeholder('Enter first name')
                                        ->required()
                                        ->columnSpan(2)
                                        ->maxLength(255),

                                    TextInput::make('last_name')
                                        ->label('Last Name')
                                        ->prefixIcon('heroicon-m-user')
                                        ->placeholder('Enter last name')
                                        ->columnSpan(2)
                                        ->required()
                                        ->maxLength(255),

                                    TextInput::make('personal_email')
                                        ->prefixIcon('heroicon-m-envelope')
                                        ->label('Personal Email')
                                        ->placeholder('name@example.com')
                                        ->columnSpan(2)
                                        ->email()
                                        ->unique(ignoreRecord: true)
                                        ->maxLength(255),

                                    TextInput::make('email')
                                        ->prefixIcon('heroicon-m-envelope')
                                        ->label('PlyrCard Email')
                                        ->placeholder('plyrcard login email')
                                        ->email()
                                        ->columnSpan(2)
                                        ->required()
                                        ->unique(ignoreRecord: true)
                                        ->maxLength(255),

                                    TextInput::make('phone')
                                        ->label('Phone')
                                        ->prefixIcon('heroicon-m-phone')
                                        ->placeholder('+1 (555) 000-0000')
                                        ->columnSpan(2)
                                        ->tel()
                                        ->maxLength(255),

                                    Select::make('school_id')
                                        ->prefixIcon('heroicon-m-building-library')
                                        ->label('School')
                                        ->placeholder('Select school')
                                        ->options(fn () => static::getSchoolOptions())
                                        ->searchable()
                                        ->preload()
                                        ->live()
                                        ->nullable()
                                        ->columnSpan(2),

                                    TextInput::make('new_school_name')
                                        ->prefixIcon('heroicon-m-plus-circle')
                                        ->label('New School Name')
                                        ->placeholder('Enter school name')
                                        ->maxLength(255)
                                        ->columnSpan(2)
                                        ->visible(fn (Get $get) => $get('school_id') === '__new__')
                                        ->required(fn (Get $get) => $get('school_id') === '__new__'),

                                    TextInput::make('password')
                                        ->label('Password')
                                        ->prefixIcon('heroicon-m-lock-closed')
                                        ->placeholder('Leave blank to keep current password')
                                        ->columnSpan(1)
                                        ->password()
                                        ->revealable()
                                        ->dehydrated(fn ($state) => filled($state))
                                        ->same('password_confirmation')
                                        ->nullable()
                                        ->helperText('Leave blank to keep the current password.'),

                                    TextInput::make('password_confirmation')
                                        ->label('Confirm Password')
                                        ->prefixIcon('heroicon-m-lock-closed')
                                        ->placeholder('Re-enter new password')
                                        ->columnSpan(1)
                                        ->password()
                                        ->revealable()
                                        ->dehydrated(false)
                                        ->nullable(),

                                    CheckboxList::make('roles')
                                        ->label('Roles')
                                        ->relationship('roles', 'name')
                                        ->columns(2)
                                        ->searchable()
                                        ->columnSpanFull(),
                                ]),

                            Section::make('Address')
                                ->icon('heroicon-m-map-pin')
                                ->columns(2)
                                ->schema([
                                    TextInput::make('street')
                                        ->prefixIcon('heroicon-m-map-pin')
                                        ->label('Street Address')
                                        ->placeholder('123 Main Street')
                                        ->maxLength(255),

                                    TextInput::make('city')
                                        ->prefixIcon('heroicon-m-building-office-2')
                                        ->label('City')
                                        ->placeholder('City')
                                        ->maxLength(255),

                                    TextInput::make('state')
                                        ->prefixIcon('heroicon-m-map')
                                        ->label('State / Province')
                                        ->placeholder('State / Province')
                                        ->maxLength(255),

                                    TextInput::make('country')
                                        ->prefixIcon('heroicon-m-globe-alt')
                                        ->label('Country')
                                        ->placeholder('Country')
                                        ->maxLength(255),
                                ]),
                        ]),

                    Tab::make('Athlete Info')
                        ->icon('heroicon-m-user-circle')
                        ->schema([
                            Section::make('Sport Details')
                                ->icon('heroicon-m-cog-6-tooth')
                                ->columns(3)
                                ->schema([
                                    Select::make('sport')
                                        ->prefixIcon('heroicon-m-trophy')
                                        ->label('Sport')
                                        ->placeholder('Select sport')
                                        ->options(static::getSportOptions())
                                        ->required()
                                        ->searchable()
                                        ->live()
                                        ->afterStateUpdated(function (Set $set) {
                                            $set('league_id', null);
                                            $set('club_id', null);
                                            $set('team_id', null);
                                            $set('team_name', null);
                                        }),

                                    Select::make('position')
                                        ->prefixIcon('heroicon-m-rectangle-group')
                                        ->label('Position')
                                        ->placeholder('Select position')
                                        ->multiple()
                                        ->searchable()
                                        ->preload()
                                        ->required()
                                        ->options(fn (Get $get): array => static::getPositionOptions($get('sport')))
                                        ->disabled(fn (Get $get): bool => blank($get('sport')))
                                        ->helperText('Select one or more positions based on the chosen sport.'),

                                    TextInput::make('jersey_number')
                                        ->prefixIcon('heroicon-m-hashtag')
                                        ->label('Roster Number')
                                        ->placeholder('e.g. 19')
                                        ->numeric(),

                                    TextInput::make('year')
                                        ->prefixIcon('heroicon-m-academic-cap')
                                        ->label('Graduation Year')
                                        ->placeholder('e.g. 2026')
                                        ->numeric()
                                        ->minValue(2000)
                                        ->maxValue(2100),

                                    Select::make('gender')
                                        ->prefixIcon('heroicon-m-user')
                                        ->label('Sex')
                                        ->placeholder('Select sex')
                                        ->options(static::getGenderOptions())
                                        ->searchable()
                                        ->nullable()
                                        ->live()
                                        ->afterStateUpdated(function (Set $set) {
                                            $set('league_id', null);
                                            $set('club_id', null);
                                            $set('team_id', null);
                                            $set('team_name', null);
                                        }),

                                    DatePicker::make('birth')
                                        ->prefixIcon('heroicon-m-calendar-days')
                                        ->label('Birth Date')
                                        ->native(false)
                                        ->closeOnDateSelection(),

                                    TextInput::make('gpa')
                                        ->prefixIcon('heroicon-m-calculator')
                                        ->label('GPA')
                                        ->placeholder('e.g. 3.8')
                                        ->numeric()
                                        ->step('0.01'),

                                    Select::make('national_team_id')
                                        ->prefixIcon('heroicon-m-flag')
                                        ->label('National Team Experience')
                                        ->placeholder('Select national team')
                                        ->options(fn () => static::getNationalTeamOptions())
                                        ->searchable()
                                        ->preload()
                                        ->live(),

                                    TextInput::make('national_team_period')
                                        ->prefixIcon('heroicon-m-calendar')
                                        ->label('National Team Period')
                                        ->placeholder('e.g. 2025-2026')
                                        ->maxLength(255),

                                    TextInput::make('new_national_team_name')
                                        ->prefixIcon('heroicon-m-plus-circle')
                                        ->label('New National Team Name')
                                        ->placeholder('Enter national team name')
                                        ->maxLength(255)
                                        ->visible(fn (Get $get) => $get('national_team_id') === '__new__')
                                        ->required(fn (Get $get) => $get('national_team_id') === '__new__'),

                                    FileUpload::make('new_national_team_logo')
                                        ->label('New National Team Logo')
                                        ->image()
                                        ->downloadable()
                                        ->imageEditor()
                                        ->disk('public')
                                        ->directory('national-team-logos')
                                        ->visibility('public')
                                        ->helperText('Optional.')
                                        ->visible(fn (Get $get) => $get('national_team_id') === '__new__'),
                                ]),

                            Section::make('Physical Stats')
                                ->columns(2)
                                ->icon('heroicon-m-chart-bar-square')
                                ->schema([
                                    TextInput::make('height')
                                        ->prefixIcon('heroicon-m-arrows-up-down')
                                        ->label('Height')
                                        ->maxLength(255)
                                        ->placeholder('e.g. 6\'2" or 188 cm'),

                                    TextInput::make('weight')
                                        ->prefixIcon('heroicon-m-scale')
                                        ->label('Weight')
                                        ->maxLength(255)
                                        ->placeholder('e.g. 185 lbs or 84 kg'),

                                    Select::make('dominant_foot')
                                        ->prefixIcon('heroicon-m-hand-raised')
                                        ->label('Dominant Foot')
                                        ->placeholder('Select dominant foot')
                                        ->options([
                                            'left' => 'Left',
                                            'right' => 'Right',
                                            'both' => 'Both',
                                        ])
                                        ->visible(fn (Get $get) => $get('sport') === 'soccer')
                                        ->required(fn (Get $get) => $get('sport') === 'soccer'),
                                ]),

                            Section::make('Experience')
                                ->icon('heroicon-m-flag')
                                ->columns(2)
                                ->schema([
                                    Select::make('league_id')
                                        ->prefixIcon('heroicon-m-squares-2x2')
                                        ->label('League')
                                        ->placeholder(fn (Get $get) => blank($get('sport')) || blank($get('gender'))
                                            ? 'Select sport and sex first'
                                            : 'Search league')
                                        ->searchable()
                                        ->live()
                                        ->preload(false)
                                        ->dehydrated(fn (Get $get) => $get('team_id') !== '__new__')
                                        ->options(fn (Get $get): array => static::getLeagueOptions(
                                            $get('gender'),
                                            $get('sport'),
                                        ))
                                        ->getSearchResultsUsing(fn (string $search, Get $get): array => static::getLeagueOptions(
                                            $get('gender'),
                                            $get('sport'),
                                            $search,
                                        ))
                                        ->getOptionLabelUsing(function ($value): ?string {
                                            if (blank($value)) {
                                                return null;
                                            }

                                            return League::query()->whereKey($value)->value('name');
                                        })
                                        ->disabled(fn (Get $get): bool => (blank($get('sport')) || blank($get('gender'))) || $get('team_id') === '__new__')
                                        ->helperText('Filtered by the selected sport and sex.')
                                        ->afterStateUpdated(function (Set $set) {
                                            $set('club_id', null);
                                            $set('team_id', null);
                                            $set('team_name', null);
                                        }),

                                    Select::make('club_id')
                                        ->prefixIcon('heroicon-m-shield-check')
                                        ->label('Club')
                                        ->placeholder(fn (Get $get) => blank($get('league_id'))
                                            ? 'Select league first'
                                            : 'Search club')
                                        ->searchable()
                                        ->live()
                                        ->preload(false)
                                        ->dehydrated(fn (Get $get) => $get('team_id') !== '__new__')
                                        ->options(fn (Get $get): array => static::getClubOptions(
                                            $get('league_id'),
                                            $get('gender'),
                                            $get('sport'),
                                        ))
                                        ->getSearchResultsUsing(fn (string $search, Get $get): array => static::getClubOptions(
                                            $get('league_id'),
                                            $get('gender'),
                                            $get('sport'),
                                            $search,
                                        ))
                                        ->getOptionLabelUsing(function ($value): ?string {
                                            if (blank($value)) {
                                                return null;
                                            }

                                            return Club::query()->whereKey($value)->value('name');
                                        })
                                        ->disabled(fn (Get $get): bool => blank($get('league_id')) || $get('team_id') === '__new__')
                                        ->helperText('Filtered by the selected league.')
                                        ->afterStateUpdated(function (Set $set) {
                                            $set('team_id', null);
                                            $set('team_name', null);
                                        }),

                                    Select::make('team_id')
                                        ->prefixIcon('heroicon-m-users')
                                        ->label('Team')
                                        ->placeholder(fn (Get $get) => blank($get('club_id'))
                                            ? 'Select club first'
                                            : 'Search team')
                                        ->searchable()
                                        ->live()
                                        ->preload(false)
                                        ->options(fn (Get $get): array => static::getTeamOptions(
                                            $get('club_id'),
                                            $get('gender'),
                                            $get('sport'),
                                        ))
                                        ->getSearchResultsUsing(fn (string $search, Get $get): array => static::getTeamOptions(
                                            $get('club_id'),
                                            $get('gender'),
                                            $get('sport'),
                                            $search,
                                        ))
                                        ->getOptionLabelUsing(function ($value): ?string {
                                            if ($value === '__new__') {
                                                return 'Add New';
                                            }

                                            if (blank($value)) {
                                                return null;
                                            }

                                            return Team::query()->whereKey($value)->value('name');
                                        })
                                        ->disabled(fn (Get $get): bool => blank($get('club_id')) && $get('team_id') !== '__new__')
                                        ->helperText('Filtered by the selected club, or choose Add New.')
                                        ->afterStateHydrated(function ($state, Set $set, $record) {
                                            if ($state || ! $record || blank($record->team_name)) {
                                                return;
                                            }

                                            $team = Team::query()
                                                ->with('club.league')
                                                ->where('name', $record->team_name)
                                                ->when($record->club_id, fn ($query) => $query->where('club_id', $record->club_id))
                                                ->first();

                                            if ($team) {
                                                $set('team_id', (string) $team->id);
                                            }
                                        })
                                        ->afterStateUpdated(function (Set $set, Get $get, $state) {
                                            if ($state === '__new__') {
                                                $set('team_name', null);
                                                return;
                                            }

                                            if (blank($state)) {
                                                $set('team_name', null);
                                                return;
                                            }

                                            $team = Team::with('club.league')->find($state);

                                            $set('team_name', $team?->name);
                                            $set('club_id', $team?->club?->id ? (string) $team->club->id : $get('club_id'));
                                            $set('league_id', $team?->club?->league?->id ? (string) $team->club->league->id : $get('league_id'));
                                        }),

                                    TextInput::make('team_name')
                                        ->prefixIcon('heroicon-m-tag')
                                        ->label('Selected Team')
                                        ->disabled()
                                        ->dehydrated()
                                        ->visible(fn (Get $get) => $get('team_id') !== '__new__' && filled($get('team_id')))
                                        ->helperText('Auto-filled from the selected team.'),

                                    TextInput::make('new_team_name')
                                        ->label('New Team Name')
                                        ->placeholder('Enter team name')
                                        ->maxLength(255)
                                        ->visible(fn (Get $get) => $get('team_id') === '__new__')
                                        ->required(fn (Get $get) => $get('team_id') === '__new__')
                                        ->prefixIcon('heroicon-m-plus-circle'),

                                    TextInput::make('new_club_name')
                                        ->label('New Club Name')
                                        ->placeholder('Enter club name')
                                        ->maxLength(255)
                                        ->visible(fn (Get $get) => $get('team_id') === '__new__')
                                        ->required(fn (Get $get) => $get('team_id') === '__new__')
                                        ->prefixIcon('heroicon-m-building-office-2'),

                                    FileUpload::make('new_club_logo')
                                        ->label('New Club Logo')
                                        ->image()
                                        ->downloadable()
                                        ->imageEditor()
                                        ->disk('public')
                                        ->directory('club-logos')
                                        ->visibility('public')
                                        ->visible(fn (Get $get) => $get('team_id') === '__new__'),

                                    TextInput::make('new_league_name')
                                        ->label('New League Name')
                                        ->placeholder('Enter league name')
                                        ->maxLength(255)
                                        ->visible(fn (Get $get) => $get('team_id') === '__new__')
                                        ->required(fn (Get $get) => $get('team_id') === '__new__')
                                        ->prefixIcon('heroicon-m-sparkles'),

                                    FileUpload::make('new_league_logo')
                                        ->label('New League Logo')
                                        ->image()
                                        ->downloadable()
                                        ->imageEditor()
                                        ->disk('public')
                                        ->directory('league-logos')
                                        ->visibility('public')
                                        ->visible(fn (Get $get) => $get('team_id') === '__new__'),
                                ]),
                        ]),

                    Tab::make('Bio & Accolades')
                        ->icon('heroicon-m-list-bullet')
                        ->schema([
                            Section::make('Player Bio')
                                ->icon('heroicon-m-pencil-square')
                                ->description('Write your athlete story — who you are, your playing style, and what drives you.')
                                ->schema([
                                    Textarea::make('player_bio')
                                        ->label('Player Bio')
                                        ->placeholder('Write your athlete story here...')
                                        ->rows(8)
                                        ->columnSpanFull(),
                                ]),

                            Section::make('Academic Accolades')
                                ->icon('heroicon-m-academic-cap')
                                ->description('Honors, dean’s list, certifications — anything that showcases your academic excellence.')
                                ->schema([
                                    Textarea::make('academic_accolades')
                                        ->label('Academic Accolades')
                                        ->placeholder("Dean's List\nHonor Roll\nAP Scholar")
                                        ->rows(6)
                                        ->helperText('Enter one accolade per line.')
                                        ->columnSpanFull(),
                                ]),

                            Section::make('Sports Accolades')
                                ->icon('heroicon-m-trophy')
                                ->schema([
                                    Textarea::make('sports_accolades')
                                        ->label('Sports Accolades')
                                        ->placeholder("Team Captain\nAll-State Selection\nTournament MVP")
                                        ->rows(5)
                                        ->helperText('Enter one accolade per line.')
                                        ->columnSpanFull(),
                                ]),
                        ]),

                    Tab::make('Media')
                        ->icon('heroicon-m-photo')
                        ->schema([
                            Section::make('Profile & Hero Images')
                                ->icon('heroicon-m-photo')
                                ->description('Shared player images used across your card and website.')
                                ->columns(3)
                                ->schema([
                                    FileUpload::make('plyrcard_image')
                                        ->label('PlyrCard')
                                        ->image()
                                        ->downloadable()
                                        ->imageEditor()
                                        ->disk('public')
                                        ->directory('user-player-images')
                                        ->visibility('public')
                                        ->helperText('Upload the card-style PNG image used across templates.'),

                                    FileUpload::make('player_image')
                                        ->label('Player Image')
                                        ->image()
                                        ->downloadable()
                                        ->imageEditor()
                                        ->disk('public')
                                        ->directory('user-player-images')
                                        ->visibility('public')
                                        ->helperText('Upload the half-body player PNG image used across templates.'),

                                    FileUpload::make('mobile_hero_image')
                                        ->label('Vertical Hero Image')
                                        ->image()
                                        ->downloadable()
                                        ->imageEditor()
                                        ->disk('public')
                                        ->directory('user-player-images')
                                        ->visibility('public')
                                        ->helperText('Upload the vertical/mobile hero image used for responsive hero layouts.'),

                                    FileUpload::make('youtube_thumbnail')
                                        ->label('YouTube Thumbnail')
                                        ->columnSpan(3)
                                        ->image()
                                        ->downloadable()
                                        ->imageEditor()
                                        ->disk('public')
                                        ->directory('user-player-images')
                                        ->visibility('public')
                                        ->helperText('Used for highlights thumbnail, social sharing image, and SEO preview image.'),

                                    FileUpload::make('raw_player_images')
                                        ->label('Raw Player Images')
                                        ->image()
                                        ->downloadable()
                                        ->multiple()
                                        ->reorderable()
                                        ->appendFiles()
                                        ->maxFiles(20)
                                        ->disk('public')
                                        ->directory('user-player-images/raw')
                                        ->visibility('public')
                                        ->columnSpanFull()
                                        ->helperText('Upload up to 20 raw player images from the intake form. These are stored separately from the main Player Image.'),
                                ]),
                        ]),

                    Tab::make('Social')
                        ->icon('heroicon-m-share')
                        ->schema([
                            Section::make('Social Profiles')
                                ->icon('heroicon-m-share')
                                ->description('Add social links, YouTube URLs, featured videos, and press links.')
                                ->columns(2)
                                ->schema([
                                    TextInput::make('ig_handle')
                                        ->label('Instagram Handle')
                                        ->prefixIcon('heroicon-m-camera')
                                        ->prefix('@')
                                        ->placeholder('yourhandle')
                                        ->maxLength(255),

                                    TextInput::make('x_handle')
                                        ->label('X Handle')
                                        ->prefixIcon('heroicon-m-chat-bubble-left-right')
                                        ->prefix('@')
                                        ->placeholder('yourhandle')
                                        ->maxLength(255),

                                    TextInput::make('yt_url')
                                        ->label('YouTube URL')
                                        ->prefixIcon('heroicon-m-link')
                                        ->placeholder('https://youtube.com/@yourchannel')
                                        ->url()
                                        ->columnSpanFull(),

                                    TextInput::make('featured_video_url')
                                        ->label('Featured Video URL')
                                        ->prefixIcon('heroicon-m-play-circle')
                                        ->placeholder('https://youtube.com/watch?v=...')
                                        ->url()
                                        ->columnSpanFull(),

                                    Textarea::make('featured_video_urls')
                                        ->label('Featured Video URLs')
                                        ->placeholder("https://youtube.com/watch?v=...\nhttps://youtube.com/watch?v=...")
                                        ->rows(5)
                                        ->helperText('Enter one video URL per line.')
                                        ->columnSpanFull(),

                                    Textarea::make('press')
                                        ->label('Press')
                                        ->placeholder("Article links\nInterviews\nMedia coverage")
                                        ->rows(5)
                                        ->columnSpanFull(),
                                ]),
                        ]),

                    Tab::make('People')
                        ->icon('heroicon-m-user-group')
                        ->schema([
                            Section::make('Parents / Guardians')
                                ->icon('heroicon-m-heart')
                                ->columns(3)
                                ->schema([
                                    TextInput::make('parent')
                                        ->label('Primary Parent')
                                        ->prefixIcon('heroicon-m-user')
                                        ->placeholder('Full name')
                                        ->maxLength(255),

                                    TextInput::make('parent_email')
                                        ->label('Primary Parent Email')
                                        ->prefixIcon('heroicon-m-envelope')
                                        ->placeholder('parent@example.com')
                                        ->email()
                                        ->maxLength(255),

                                    TextInput::make('parent_phone')
                                        ->label('Primary Parent Phone')
                                        ->prefixIcon('heroicon-m-phone')
                                        ->placeholder('+1 (555) 000-0000')
                                        ->tel()
                                        ->maxLength(255),

                                    TextInput::make('sec_parent')
                                        ->label('Secondary Parent')
                                        ->prefixIcon('heroicon-m-user')
                                        ->placeholder('Full name')
                                        ->maxLength(255),

                                    TextInput::make('sec_parent_email')
                                        ->label('Secondary Parent Email')
                                        ->prefixIcon('heroicon-m-envelope')
                                        ->placeholder('parent2@example.com')
                                        ->email()
                                        ->maxLength(255),

                                    TextInput::make('sec_parent_phone')
                                        ->label('Secondary Parent Phone')
                                        ->prefixIcon('heroicon-m-phone')
                                        ->placeholder('+1 (555) 000-0000')
                                        ->tel()
                                        ->maxLength(255),
                                ]),

                            Section::make('Coaches')
                                ->icon('heroicon-m-megaphone')
                                ->columns(3)
                                ->schema([
                                    TextInput::make('club_coach')
                                        ->label('Club Coach')
                                        ->prefixIcon('heroicon-m-user')
                                        ->placeholder('Coach name')
                                        ->maxLength(255),

                                    TextInput::make('club_coach_email')
                                        ->label('Club Coach Email')
                                        ->prefixIcon('heroicon-m-envelope')
                                        ->placeholder('coach@example.com')
                                        ->email()
                                        ->maxLength(255),

                                    TextInput::make('club_coach_phone')
                                        ->label('Club Coach Phone')
                                        ->prefixIcon('heroicon-m-phone')
                                        ->placeholder('+1 (555) 000-0000')
                                        ->tel()
                                        ->maxLength(255),

                                    TextInput::make('natl_coach')
                                        ->label('National Team Coach')
                                        ->prefixIcon('heroicon-m-user')
                                        ->placeholder('Coach name')
                                        ->maxLength(255),

                                    TextInput::make('natl_coach_email')
                                        ->label('National Team Coach Email')
                                        ->prefixIcon('heroicon-m-envelope')
                                        ->placeholder('coach@example.com')
                                        ->email()
                                        ->maxLength(255),

                                    TextInput::make('natl_coach_phone')
                                        ->label('National Team Coach Phone')
                                        ->prefixIcon('heroicon-m-phone')
                                        ->placeholder('+1 (555) 000-0000')
                                        ->tel()
                                        ->maxLength(255),
                                ]),

                            Section::make('Trainers')
                                ->icon('heroicon-m-bolt')
                                ->columns(3)
                                ->schema([
                                    TextInput::make('tech_trainer')
                                        ->label('Technical Trainer')
                                        ->prefixIcon('heroicon-m-user')
                                        ->placeholder('Trainer name')
                                        ->maxLength(255),

                                    TextInput::make('tech_trainer_email')
                                        ->label('Technical Trainer Email')
                                        ->prefixIcon('heroicon-m-envelope')
                                        ->placeholder('trainer@example.com')
                                        ->email()
                                        ->maxLength(255),

                                    TextInput::make('tech_trainer_phone')
                                        ->label('Technical Trainer Phone')
                                        ->prefixIcon('heroicon-m-phone')
                                        ->placeholder('+1 (555) 000-0000')
                                        ->tel()
                                        ->maxLength(255),

                                    TextInput::make('snc_trainer')
                                        ->label('Strength & Conditioning Trainer')
                                        ->prefixIcon('heroicon-m-user')
                                        ->placeholder('Trainer name')
                                        ->maxLength(255),

                                    TextInput::make('snc_trainer_email')
                                        ->label('Strength & Conditioning Trainer Email')
                                        ->prefixIcon('heroicon-m-envelope')
                                        ->placeholder('trainer@example.com')
                                        ->email()
                                        ->maxLength(255),

                                    TextInput::make('snc_trainer_phone')
                                        ->label('Strength & Conditioning Trainer Phone')
                                        ->prefixIcon('heroicon-m-phone')
                                        ->placeholder('+1 (555) 000-0000')
                                        ->tel()
                                        ->maxLength(255),
                                ]),
                        ]),

                    Tab::make('Website')
                        ->icon('heroicon-m-globe-alt')
                        ->schema([
                            Section::make('Website Settings')
                                ->icon('heroicon-m-globe-alt')
                                ->description('Configure website access and publishing settings.')
                                ->columns(2)
                                ->schema([
                                    TextInput::make('domain')
                                        ->label('Custom Domain')
                                        ->prefixIcon('heroicon-m-link')
                                        ->helperText('Enter without https://')
                                        ->placeholder('yourdomain.com')
                                        ->columnSpan(2)
                                        ->maxLength(255)
                                        ->unique(ignoreRecord: true)
                                        ->nullable(),

                                    Toggle::make('website_is_published')
                                        ->label('Website Published')
                                        ->default(false)
                                        ->formatStateUsing(function ($state, $record) {
                                            return (bool) ($record?->websites()->first()?->is_published ?? false);
                                        })
                                        ->dehydrated(false),
                                ]),
                        ]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['roles', 'websites']))
            ->columns([
                TextColumn::make('first_name')
                    ->label('First Name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('last_name')
                    ->label('Last Name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('email')
                    ->label('PlyrCard Email')
                    ->searchable()
                    ->copyable(),

                TextColumn::make('roles.name')
                    ->label('Roles')
                    ->badge()
                    ->separator(',')
                    ->searchable(),

                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->since()
                    ->sortable(),

                TextColumn::make('personal_email')
                    ->label('Personal Email')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('phone')
                    ->label('Phone')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('gender')
                    ->label('Gender')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => filled($state) ? str($state)->title() : '-')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('school.name')
                    ->label('School')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('league.name')
                    ->label('League')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('club.name')
                    ->label('Club')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('team_name')
                    ->label('Team')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('nationalTeam.name')
                    ->label('National Team')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('sport')
                    ->label('Sport')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => filled($state) ? str($state)->replace('_', ' ')->title() : '-')
                    ->sortable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('dominant_foot')
                    ->label('Dominant Foot')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => filled($state) ? str($state)->title() : '-')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('position')
                    ->label('Positions')
                    ->state(function ($record): array {
                        return collect($record->position ?? [])
                            ->map(fn ($item) => str($item)->replace('_', ' ')->title())
                            ->values()
                            ->all();
                    })
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('year')
                    ->label('Graduation Year')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('gpa')
                    ->label('GPA')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('jersey_number')
                    ->label('Jersey #')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('height')
                    ->label('Height')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('weight')
                    ->label('Weight')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('domain')
                    ->label('Custom Domain')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                Action::make('editAccess')
                    ->label('Edit Access')
                    ->icon('heroicon-m-pencil-square')
                    ->modalHeading('Edit Roles & Website Publishing')
                    ->fillForm(function (User $record): array {
                        return [
                            'roles' => $record->roles->pluck('name')->all(),
                            'website_is_published' => (bool) $record->websites->first()?->is_published,
                        ];
                    })
                    ->form([
                        Select::make('roles')
                            ->label('Roles')
                            ->multiple()
                            ->options(Role::query()->orderBy('name')->pluck('name', 'name')->toArray())
                            ->searchable()
                            ->preload()
                            ->required(),

                        Toggle::make('website_is_published')
                            ->label('Website Published'),
                    ])
                    ->action(function (User $record, array $data): void {
                        $record->syncRoles($data['roles'] ?? []);

                        $website = $record->websites()->first();

                        if ($website) {
                            $website->update([
                                'is_published' => (bool) ($data['website_is_published'] ?? false),
                            ]);
                        }
                    })
                    ->successNotificationTitle('User access updated.'),

                Impersonate::make()
                    ->visible(fn (User $record) => auth()->id() !== $record->id
                        && auth()->user()?->hasRole('Superadmin'))
                    ->redirectTo('/admin'),
            ])
            ->filtersFormColumns(3)
            ->filters([
                SelectFilter::make('school_id')
                    ->label('School')
                    ->relationship('school', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('club_id')
                    ->label('Club')
                    ->relationship('club', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('league_id')
                    ->label('League')
                    ->relationship('league', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('national_team_id')
                    ->label('National Team')
                    ->relationship('nationalTeam', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('roles')
                    ->label('Roles')
                    ->relationship('roles', 'name')
                    ->multiple()
                    ->searchable()
                    ->preload(),

                SelectFilter::make('sport')
                    ->label('Sport')
                    ->options(static::getSportOptions())
                    ->multiple(),

                SelectFilter::make('gender')
                    ->label('Gender')
                    ->options(static::getGenderOptions())
                    ->multiple(),

                SelectFilter::make('year')
                    ->label('Graduation Year')
                    ->options(
                        User::query()
                            ->whereNotNull('year')
                            ->distinct()
                            ->orderBy('year')
                            ->pluck('year', 'year')
                            ->mapWithKeys(fn ($year) => [$year => (string) $year])
                            ->all()
                    )
                    ->multiple(),

                TernaryFilter::make('has_website')
                    ->label('Has Website')
                    ->placeholder('All users')
                    ->trueLabel('With website')
                    ->falseLabel('Without website')
                    ->queries(
                        true: fn (Builder $query) => $query->where(function (Builder $q) {
                            $q->whereNotNull('domain')
                                ->where('domain', '!=', '')
                                ->orWhereHas('websites');
                        }),
                        false: fn (Builder $query) => $query->where(function (Builder $q) {
                            $q->whereNull('domain')
                                ->orWhere('domain', '=', '');
                        })->whereDoesntHave('websites'),
                        blank: fn (Builder $query) => $query,
                    ),

                TernaryFilter::make('has_school')
                    ->label('Assigned School')
                    ->placeholder('All users')
                    ->trueLabel('With school')
                    ->falseLabel('Without school')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('school_id'),
                        false: fn (Builder $query) => $query->whereNull('school_id'),
                        blank: fn (Builder $query) => $query,
                    ),

                TernaryFilter::make('has_club')
                    ->label('Assigned Club')
                    ->placeholder('All users')
                    ->trueLabel('With club')
                    ->falseLabel('Without club')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('club_id'),
                        false: fn (Builder $query) => $query->whereNull('club_id'),
                        blank: fn (Builder $query) => $query,
                    ),

                TernaryFilter::make('has_league')
                    ->label('Assigned League')
                    ->placeholder('All users')
                    ->trueLabel('With league')
                    ->falseLabel('Without league')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('league_id'),
                        false: fn (Builder $query) => $query->whereNull('league_id'),
                        blank: fn (Builder $query) => $query,
                    ),

                TernaryFilter::make('has_national_team')
                    ->label('Assigned National Team')
                    ->placeholder('All users')
                    ->trueLabel('With national team')
                    ->falseLabel('Without national team')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('national_team_id'),
                        false: fn (Builder $query) => $query->whereNull('national_team_id'),
                        blank: fn (Builder $query) => $query,
                    ),

                TernaryFilter::make('has_phone')
                    ->label('Has Phone')
                    ->placeholder('All users')
                    ->trueLabel('With phone')
                    ->falseLabel('Without phone')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('phone')->where('phone', '!=', ''),
                        false: fn (Builder $query) => $query->where(function (Builder $q) {
                            $q->whereNull('phone')->orWhere('phone', '=', '');
                        }),
                        blank: fn (Builder $query) => $query,
                    ),

                TernaryFilter::make('has_parent_email')
                    ->label('Has Parent Email')
                    ->placeholder('All users')
                    ->trueLabel('With parent email')
                    ->falseLabel('Without parent email')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('parent_email')->where('parent_email', '!=', ''),
                        false: fn (Builder $query) => $query->where(function (Builder $q) {
                            $q->whereNull('parent_email')->orWhere('parent_email', '=', '');
                        }),
                        blank: fn (Builder $query) => $query,
                    ),

                TernaryFilter::make('has_socials')
                    ->label('Has Social Profiles')
                    ->placeholder('All users')
                    ->trueLabel('With socials')
                    ->falseLabel('Without socials')
                    ->queries(
                        true: fn (Builder $query) => $query->where(function (Builder $q) {
                            $q->whereNotNull('ig_handle')->where('ig_handle', '!=', '')
                                ->orWhereNotNull('x_handle')->where('x_handle', '!=', '')
                                ->orWhereNotNull('yt_url')->where('yt_url', '!=', '');
                        }),
                        false: fn (Builder $query) => $query->where(function (Builder $q) {
                            $q->whereNull('ig_handle')->orWhere('ig_handle', '=', '');
                        })->where(function (Builder $q) {
                            $q->whereNull('x_handle')->orWhere('x_handle', '=', '');
                        })->where(function (Builder $q) {
                            $q->whereNull('yt_url')->orWhere('yt_url', '=', '');
                        }),
                        blank: fn (Builder $query) => $query,
                    ),

                Filter::make('missing_core_profile')
                    ->label('Missing Core Profile Info')
                    ->query(fn (Builder $query): Builder => $query->where(function (Builder $q) {
                        $q->whereNull('school_id')
                            ->orWhereNull('club_id')
                            ->orWhereNull('sport')
                            ->orWhere(function (Builder $inner) {
                                $inner->whereNull('phone')->orWhere('phone', '=', '');
                            });
                    })),

                TrashedFilter::make(),
            ])
            ->recordUrl(fn (User $record): string => static::getUrl('edit', ['record' => $record]));
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'view' => ViewUser::route('/{record}'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}