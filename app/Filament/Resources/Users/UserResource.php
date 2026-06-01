<?php

namespace App\Filament\Resources\Users;

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Pages\ViewUser;
use App\Models\Club;
use App\Models\ClubLeague;
use App\Models\League;
use App\Models\NationalTeam;
use App\Models\School;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
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
use Filament\Tables\Enums\FiltersLayout;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\HtmlString;
use Spatie\Permission\Models\Role;
use STS\FilamentImpersonate\Actions\Impersonate;
use UnitEnum;
use Filament\Actions\EditAction;

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

    protected static function canManagePlayerImages(): bool
    {
        return auth()->user()?->hasRole('Superadmin') ?? false;
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

    protected static function normalizeGender(?string $gender): ?string
    {
        $gender = strtolower(trim((string) $gender));

        return match (true) {
            str_contains($gender, 'female'), str_contains($gender, 'girl'), str_contains($gender, 'women') => 'female',
            str_contains($gender, 'male'), str_contains($gender, 'boy'), str_contains($gender, 'men') => 'male',
            default => filled($gender) ? $gender : null,
        };
    }

    protected static function getLeagueOptions(?string $gender = null, ?string $sport = null, ?string $search = null): array
    {
        $gender = static::normalizeGender($gender);

        $query = League::query()
            ->when(filled($gender), function (Builder $query) use ($gender): Builder {
                return $query->where(function (Builder $query) use ($gender): Builder {
                    return $query
                        ->whereJsonContains('genders', $gender)
                        ->orWhere('gender', $gender)
                        ->orWhere('gender', ucfirst($gender))
                        ->orWhere('gender', $gender === 'female' ? 'Girls' : 'Boys')
                        ->orWhere('gender', $gender === 'female' ? 'Female' : 'Male');
                });
            })
            ->when(filled($sport), fn (Builder $query): Builder => $query->where(function (Builder $query) use ($sport): Builder {
                return $query->whereNull('sport')->orWhere('sport', $sport);
            }))
            ->when(filled($search), fn (Builder $query): Builder => $query->where('name', 'like', '%' . trim($search) . '%'));

        return $query
            ->orderBy('name')
            ->limit(50)
            ->get(['id', 'name', 'genders', 'gender'])
            ->mapWithKeys(function (League $league): array {
                $genders = collect($league->genders ?: [$league->gender])
                    ->filter()
                    ->map(fn ($gender) => str($gender)->title())
                    ->implode('/');

                return [(string) $league->id => $league->name . ($genders ? ' — ' . $genders : '')];
            })
            ->all();
    }

    protected static function getClubOptions(?string $leagueId = null, ?string $gender = null, ?string $sport = null, ?string $search = null): array
    {
        if (blank($leagueId)) {
            return [];
        }

        $gender = static::normalizeGender($gender);

        $query = Club::query()
            ->whereHas('clubLeagues', function (Builder $query) use ($leagueId, $gender, $sport): Builder {
                return $query
                    ->where('league_id', $leagueId)
                    ->where('is_active', true)
                    ->when(filled($gender), fn (Builder $query): Builder => $query->whereJsonContains('genders', $gender))
                    ->when(filled($sport), fn (Builder $query): Builder => $query->where(function (Builder $query) use ($sport): Builder {
                        return $query->whereNull('sport')->orWhere('sport', $sport);
                    }));
            })
            ->when(filled($search), fn (Builder $query): Builder => $query->where('name', 'like', '%' . trim($search) . '%'));

        return $query
            ->orderBy('name')
            ->limit(50)
            ->pluck('name', 'id')
            ->mapWithKeys(fn ($name, $id) => [(string) $id => $name])
            ->all();
    }

    protected static function getAgeGroupOptions(?string $search = null): array
    {
        $configured = config('plyrcard.age_groups', [
            'u13' => 'U13',
            'u14' => 'U14',
            'u15' => 'U15',
            'u16' => 'U16',
            'u17' => 'U17',
            'u18' => 'U18',
            'u19' => 'U19',
        ]);

        return collect($configured)
            ->mapWithKeys(fn ($label) => [(string) $label => (string) $label])
            ->when(filled($search), fn ($items) => $items->filter(fn ($label) => str_contains(strtolower($label), strtolower(trim($search)))))
            ->all();
    }

    protected static function resolveClubLeagueId(?string $clubId, ?string $leagueId, ?string $gender, ?string $sport = null): ?int
    {
        if (blank($clubId) || blank($leagueId)) {
            return null;
        }

        $gender = static::normalizeGender($gender);

        return ClubLeague::query()
            ->where('club_id', $clubId)
            ->where('league_id', $leagueId)
            ->where('is_active', true)
            ->when(filled($gender), fn (Builder $query): Builder => $query->whereJsonContains('genders', $gender))
            ->when(filled($sport), fn (Builder $query): Builder => $query->where(function (Builder $query) use ($sport): Builder {
                return $query->whereNull('sport')->orWhere('sport', $sport);
            }))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->value('id');
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

        $data['club_league_id'] = static::resolveClubLeagueId(
            $data['club_id'] ?? null,
            $data['league_id'] ?? null,
            $data['gender'] ?? null,
            $data['sport'] ?? null,
        );

        if (filled($data['team_name'] ?? null)) {
            $data['team_name'] = strtoupper(trim((string) $data['team_name']));
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
            $data['new_national_team_name'],
            $data['new_national_team_logo'],
        );

        return $data;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Hidden::make('club_league_id'),

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
                            Section::make('Player Details')
                                ->icon('heroicon-m-user-circle')
                                ->description('Core playing information for the athlete.')
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
                                            $set('team_name', null);
                                        }),

                                    DatePicker::make('birth')
                                        ->prefixIcon('heroicon-m-calendar-days')
                                        ->label('Birth Date')
                                        ->native(false)
                                        ->closeOnDateSelection(),
                                ]),

                            Section::make('Experience')
                                ->icon('heroicon-m-flag')
                                ->description('League, club, national team, NCAA, and professional club information.')
                                ->columns(3)
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
                                        ->disabled(fn (Get $get): bool => blank($get('sport')) || blank($get('gender')))
                                        ->helperText('Filtered by Supported Genders on the League record.')
                                        ->afterStateUpdated(function (Set $set) {
                                            $set('club_id', null);
                                            $set('club_league_id', null);
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
                                        ->disabled(fn (Get $get): bool => blank($get('league_id')))
                                        ->helperText('Filtered through Club Program Leagues.')
                                        ->afterStateUpdated(function (Set $set, Get $get, $state) {
                                            $set('team_name', null);
                                            $set('club_league_id', static::resolveClubLeagueId(
                                                $state,
                                                $get('league_id'),
                                                $get('gender'),
                                                $get('sport'),
                                            ));
                                        }),

                                    Select::make('team_name')
                                        ->prefixIcon('heroicon-m-users')
                                        ->label('Age Group')
                                        ->placeholder(fn (Get $get) => blank($get('club_id'))
                                            ? 'Select club first'
                                            : 'Select age group')
                                        ->searchable()
                                        ->live()
                                        ->preload()
                                        ->options(fn (): array => static::getAgeGroupOptions())
                                        ->getSearchResultsUsing(fn (string $search): array => static::getAgeGroupOptions($search))
                                        ->disabled(fn (Get $get): bool => blank($get('club_id')))
                                        ->helperText('Static age group. This replaces the old Team model selection.')
                                        ->afterStateUpdated(function (Set $set, Get $get): void {
                                            $set('club_league_id', static::resolveClubLeagueId(
                                                $get('club_id'),
                                                $get('league_id'),
                                                $get('gender'),
                                                $get('sport'),
                                            ));
                                        }),

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

                                    TextInput::make('ncaa_field_id')
                                        ->prefixIcon('heroicon-m-identification')
                                        ->label('NCAA Field ID')
                                        ->placeholder('Enter NCAA Field ID')
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

                                    TextInput::make('pro_club_name')
                                        ->prefixIcon('heroicon-m-building-office-2')
                                        ->label('Pro Club')
                                        ->placeholder('Enter pro club name')
                                        ->maxLength(255),

                                    FileUpload::make('pro_club_logo')
                                        ->label('Pro Club Logo')
                                        ->image()
                                        ->downloadable()
                                        ->imageEditor()
                                        ->disk('public')
                                        ->directory('pro-club-logos')
                                        ->visibility('public')
                                        ->helperText('Upload the professional club logo.'),
                                ]),

                            Section::make('Academic & Physical Stats')
                                ->columns(3)
                                ->icon('heroicon-m-chart-bar-square')
                                ->schema([
                                    TextInput::make('gpa')
                                        ->prefixIcon('heroicon-m-calculator')
                                        ->label('GPA')
                                        ->placeholder('e.g. 3.8')
                                        ->inputMode('decimal')
                                        ->rules([
                                            'nullable',
                                            'numeric',
                                            'min:0',
                                            'max:5',
                                        ])
                                        ->dehydrateStateUsing(fn ($state) => blank($state) ? null : $state),

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
                            Placeholder::make('compact_raw_upload_styles')
                                ->label('')
                                ->content(new HtmlString(<<<'HTML'
                                    <style>
                                        .plyrcard-compact-upload .filepond--root {
                                            max-height: 34rem !important;
                                            overflow-y: auto !important;
                                            border-radius: 0.75rem;
                                        }

                                        .plyrcard-compact-upload .filepond--list {
                                            display: grid !important;
                                            grid-template-columns: repeat(auto-fill, minmax(132px, 1fr)) !important;
                                            gap: 0.75rem !important;
                                            transform: none !important;
                                            position: relative !important;
                                        }

                                        .plyrcard-compact-upload .filepond--item {
                                            position: relative !important;
                                            transform: none !important;
                                            width: 100% !important;
                                            height: 132px !important;
                                            margin: 0 !important;
                                            left: auto !important;
                                            right: auto !important;
                                            top: auto !important;
                                        }

                                        .plyrcard-compact-upload .filepond--panel-root,
                                        .plyrcard-compact-upload .filepond--image-preview,
                                        .plyrcard-compact-upload .filepond--image-preview-wrapper,
                                        .plyrcard-compact-upload .filepond--file {
                                            height: 132px !important;
                                            min-height: 132px !important;
                                            max-height: 132px !important;
                                        }

                                        .plyrcard-compact-upload .filepond--image-preview img,
                                        .plyrcard-compact-upload .filepond--image-bitmap,
                                        .plyrcard-compact-upload canvas {
                                            object-fit: cover !important;
                                        }

                                        .plyrcard-compact-upload .filepond--drop-label {
                                            min-height: 5rem !important;
                                        }
                                    </style>
                                HTML))
                                ->columnSpanFull(),

                            Section::make('Raw Player Images')
                                ->icon('heroicon-m-photo')
                                ->description('Players can upload only raw image assets here. Curated/profile/template images are managed by Superadmins.')
                                ->schema([
                                    FileUpload::make('raw_player_images')
                                        ->label('Raw Player Images')
                                        ->image()
                                        ->multiple()
                                        ->reorderable()
                                        ->appendFiles()
                                        ->downloadable()
                                        ->openable()
                                        ->maxFiles(20)
                                        ->maxSize(5120)
                                        ->panelLayout('grid')
                                        ->imagePreviewHeight('132px')
                                        ->disk('public')
                                        ->directory('user-player-images/raw')
                                        ->visibility('public')
                                        ->columnSpanFull()
                                        ->extraAttributes([
                                            'class' => 'plyrcard-compact-upload',
                                        ])
                                        ->helperText('Upload up to 20 raw player images. Images are shown as compact thumbnails in a scrollable panel.'),
                                ]),

                            Section::make('Curated Images')
                                ->icon('heroicon-m-sparkles')
                                ->description('View-only for players. Only Superadmins can replace processed images used across cards, websites, thumbnails, and hero layouts.')
                                ->columns(4)
                                ->schema([
                                    FileUpload::make('plyrcard_image')
                                        ->label('PlyrCard')
                                        ->disabled(fn (): bool => ! static::canManagePlayerImages())
                                        ->dehydrated(fn (): bool => static::canManagePlayerImages())
                                        ->image()
                                        ->downloadable()
                                        ->openable()
                                        ->imageEditor(fn (): bool => static::canManagePlayerImages())
                                        ->panelLayout('compact')
                                        ->imagePreviewHeight('120px')
                                        ->disk('public')
                                        ->directory('user-player-images')
                                        ->visibility('public')
                                        ->helperText(fn (): string => static::canManagePlayerImages() ? 'Upload the card-style PNG image used across templates.' : 'View-only. Only Superadmins can replace this processed image.'),

                                    FileUpload::make('player_image')
                                        ->label('Player Image')
                                        ->disabled(fn (): bool => ! static::canManagePlayerImages())
                                        ->dehydrated(fn (): bool => static::canManagePlayerImages())
                                        ->image()
                                        ->downloadable()
                                        ->openable()
                                        ->imageEditor(fn (): bool => static::canManagePlayerImages())
                                        ->panelLayout('compact')
                                        ->imagePreviewHeight('120px')
                                        ->disk('public')
                                        ->directory('user-player-images')
                                        ->visibility('public')
                                        ->helperText(fn (): string => static::canManagePlayerImages() ? 'Upload the half-body player PNG image used across templates.' : 'View-only. Only Superadmins can replace this processed image.'),

                                    FileUpload::make('action_image')
                                        ->label('Action Image')
                                        ->disabled(fn (): bool => ! static::canManagePlayerImages())
                                        ->dehydrated(fn (): bool => static::canManagePlayerImages())
                                        ->image()
                                        ->downloadable()
                                        ->openable()
                                        ->imageEditor(fn (): bool => static::canManagePlayerImages())
                                        ->panelLayout('compact')
                                        ->imagePreviewHeight('120px')
                                        ->disk('public')
                                        ->directory('user-player-images')
                                        ->visibility('public')
                                        ->helperText(fn (): string => static::canManagePlayerImages() ? 'Upload an in-game action shot.' : 'View-only. Only Superadmins can replace this processed image.'),

                                    FileUpload::make('national_team_image')
                                        ->label('National Team Image')
                                        ->disabled(fn (): bool => ! static::canManagePlayerImages())
                                        ->dehydrated(fn (): bool => static::canManagePlayerImages())
                                        ->image()
                                        ->downloadable()
                                        ->openable()
                                        ->imageEditor(fn (): bool => static::canManagePlayerImages())
                                        ->panelLayout('compact')
                                        ->imagePreviewHeight('120px')
                                        ->disk('public')
                                        ->directory('user-player-images')
                                        ->visibility('public')
                                        ->helperText(fn (): string => static::canManagePlayerImages() ? 'Upload the image used for national team sections or layouts.' : 'View-only. Only Superadmins can replace this processed image.'),

                                    FileUpload::make('mobile_hero_image')
                                        ->label('Vertical Hero Image')
                                        ->disabled(fn (): bool => ! static::canManagePlayerImages())
                                        ->dehydrated(fn (): bool => static::canManagePlayerImages())
                                        ->image()
                                        ->downloadable()
                                        ->openable()
                                        ->imageEditor(fn (): bool => static::canManagePlayerImages())
                                        ->panelLayout('compact')
                                        ->imagePreviewHeight('120px')
                                        ->columnSpanFull()
                                        ->disk('public')
                                        ->directory('user-player-images')
                                        ->visibility('public')
                                        ->helperText(fn (): string => static::canManagePlayerImages() ? 'Upload the vertical/mobile hero image used for responsive hero layouts.' : 'View-only. Only Superadmins can replace this processed image.'),

                                    FileUpload::make('youtube_thumbnail')
                                        ->label('YouTube Thumbnail')
                                        ->disabled(fn (): bool => ! static::canManagePlayerImages())
                                        ->dehydrated(fn (): bool => static::canManagePlayerImages())
                                        ->columnSpanFull()
                                        ->image()
                                        ->downloadable()
                                        ->openable()
                                        ->imageEditor(fn (): bool => static::canManagePlayerImages())
                                        ->panelLayout('compact')
                                        ->imagePreviewHeight('120px')
                                        ->disk('public')
                                        ->directory('user-player-images')
                                        ->visibility('public')
                                        ->helperText(fn (): string => static::canManagePlayerImages() ? 'Used for highlights thumbnail, social sharing image, and SEO preview image.' : 'View-only. Only Superadmins can replace this processed image.'),
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
                ])->columnSpan(2),
        ]);
    }


    protected static function profileCoreFields(): array
    {
        return [
            'first_name' => 'First name',
            'last_name' => 'Last name',
            'email' => 'Email',
            'phone' => 'Phone',
            'birth' => 'Birth date',
            'gender' => 'Gender',
            'country' => 'Country',
            'city' => 'City',
            'sport' => 'Sport',
            'height' => 'Height',
            'weight' => 'Weight',
            'player_bio' => 'Player bio',
            'player_image' => 'Profile photo',
            'plyrcard_image' => 'PlyrCard image',
            'school_id' => 'School',
            'club_id' => 'Club',
            'pro_club_name' => 'Pro club',
            'pro_club_logo' => 'Pro club logo',
            'league_id' => 'League',
            'featured_video_url' => 'Featured video',
            'ig_handle' => 'Instagram handle',
        ];
    }

    protected static function profileSportSpecificFields(): array
    {
        return [
            'position' => 'Position',
            'dominant_foot' => 'Dominant foot',
            'jersey_number' => 'Jersey number',
            'max_speed' => 'Max speed',
            'natl_team_exp' => 'National team experience',
            'national_team_id' => 'National team',
            'national_team_period' => 'National team period',
        ];
    }

    protected static function profileSections(): array
    {
        return [
            'basic_information' => [
                'label' => 'Basic Information',
                'fields' => [
                    'first_name',
                    'last_name',
                    'email',
                    'phone',
                    'birth',
                    'gender',
                ],
            ],
            'location' => [
                'label' => 'Location',
                'fields' => [
                    'country',
                    'city',
                ],
            ],
            'athletic_profile' => [
                'label' => 'Athletic Profile',
                'fields' => [
                    'sport',
                    'position',
                    'dominant_foot',
                    'height',
                    'weight',
                    'jersey_number',
                    'ncaa_field_id',
                    'max_speed',
                    'player_bio',
                ],
            ],
            'associations' => [
                'label' => 'Associations',
                'fields' => [
                    'school_id',
                    'club_id',
                    'league_id',
                    'pro_club_name',
                    'pro_club_logo',
                ],
            ],
            'media_branding' => [
                'label' => 'Media & Branding',
                'fields' => [
                    'player_image',
                    'plyrcard_image',
                    'featured_video_url',
                    'ig_handle',
                ],
            ],
            'national_team' => [
                'label' => 'National Team',
                'fields' => [
                    'natl_team_exp',
                    'national_team_id',
                    'national_team_period',
                ],
            ],
        ];
    }

    protected static function profileMilestones(): array
    {
        return [
            'starter' => [
                'label' => 'Starter',
                'threshold' => 25,
            ],
            'rising_talent' => [
                'label' => 'Rising Talent',
                'threshold' => 50,
            ],
            'scouted_ready' => [
                'label' => 'Scouted Ready',
                'threshold' => 75,
            ],
            'plyrcard_complete' => [
                'label' => 'PlyrCard Complete',
                'threshold' => 100,
            ],
        ];
    }

    protected static function profileMilestoneBandOptions(): array
    {
        return [
            'below_starter' => 'Below Starter (0-24%)',
            'starter' => 'Starter (25-49%)',
            'rising_talent' => 'Rising Talent (50-74%)',
            'scouted_ready' => 'Scouted Ready (75-99%)',
            'plyrcard_complete' => 'PlyrCard Complete (100%)',
        ];
    }

    protected static function profileMilestoneOptions(): array
    {
        return collect(static::profileMilestones())
            ->mapWithKeys(fn (array $milestone, string $key): array => [
                $key => $milestone['label'] . ' (' . $milestone['threshold'] . '%+)',
            ])
            ->all();
    }

    protected static function userColumn(string $field): string
    {
        return (new User())->getTable() . '.' . $field;
    }

    protected static function fieldHasValueSql(string $field): string
    {
        $column = static::userColumn($field);

        return "({$column} IS NOT NULL AND {$column} != '' AND {$column} != '[]' AND {$column} != 'null')";
    }

    protected static function profileCompletionSql(): string
    {
        $coreFields = array_keys(static::profileCoreFields());
        $sportFields = array_keys(static::profileSportSpecificFields());

        $coreScore = collect($coreFields)
            ->map(fn (string $field): string => 'CASE WHEN ' . static::fieldHasValueSql($field) . ' THEN 1 ELSE 0 END')
            ->implode(' + ');

        $sportScore = collect($sportFields)
            ->map(fn (string $field): string => 'CASE WHEN ' . static::fieldHasValueSql($field) . ' THEN 1 ELSE 0 END')
            ->implode(' + ');

        return "LEAST(100, ROUND((({$coreScore}) / " . count($coreFields) . ") * 100 + (({$sportScore}) / " . count($sportFields) . ") * 10))";
    }

    protected static function applyProfileCompletionRange(Builder $query, ?int $min = null, ?int $max = null): Builder
    {
        $completionSql = static::profileCompletionSql();

        return $query
            ->when(! is_null($min), fn (Builder $q): Builder => $q->whereRaw("({$completionSql}) >= ?", [$min]))
            ->when(! is_null($max), fn (Builder $q): Builder => $q->whereRaw("({$completionSql}) <= ?", [$max]));
    }

    protected static function applyProfileMilestoneBand(Builder $query, ?string $band): Builder
    {
        return match ($band) {
            'below_starter' => static::applyProfileCompletionRange($query, null, 24),
            'starter' => static::applyProfileCompletionRange($query, 25, 49),
            'rising_talent' => static::applyProfileCompletionRange($query, 50, 74),
            'scouted_ready' => static::applyProfileCompletionRange($query, 75, 99),
            'plyrcard_complete' => static::applyProfileCompletionRange($query, 100, 100),
            default => $query,
        };
    }

    protected static function applyProfileMilestoneReached(Builder $query, ?string $milestone): Builder
    {
        $threshold = static::profileMilestones()[$milestone]['threshold'] ?? null;

        if (is_null($threshold)) {
            return $query;
        }

        return static::applyProfileCompletionRange($query, $threshold);
    }

    protected static function applyExactProfileMilestoneFilter(Builder $query, bool $onMilestone): Builder
    {
        $completionSql = static::profileCompletionSql();
        $milestones = collect(static::profileMilestones())
            ->pluck('threshold')
            ->values()
            ->all();

        $placeholders = implode(', ', array_fill(0, count($milestones), '?'));

        return $onMilestone
            ? $query->whereRaw("({$completionSql}) IN ({$placeholders})", $milestones)
            : $query->whereRaw("({$completionSql}) NOT IN ({$placeholders})", $milestones);
    }

    protected static function applyMissingProfileSection(Builder $query, string $sectionKey): Builder
    {
        $section = static::profileSections()[$sectionKey] ?? null;

        if (! $section) {
            return $query;
        }

        return $query->where(function (Builder $query) use ($section): Builder {
            foreach ($section['fields'] as $field) {
                $query->orWhereRaw('NOT ' . static::fieldHasValueSql($field));
            }

            return $query;
        });
    }

    protected static function calculateProfileCompletion(User $record): int
    {
        $completedCore = collect(static::profileCoreFields())
            ->filter(fn (string $label, string $field): bool => static::hasProfileValue($record->{$field} ?? null))
            ->count();

        $corePercentage = count(static::profileCoreFields())
            ? ($completedCore / count(static::profileCoreFields())) * 100
            : 0;

        $completedSportSpecific = collect(static::profileSportSpecificFields())
            ->filter(fn (string $label, string $field): bool => static::hasProfileValue($record->{$field} ?? null))
            ->count();

        $sportBonus = count(static::profileSportSpecificFields())
            ? ($completedSportSpecific / count(static::profileSportSpecificFields())) * 10
            : 0;

        return (int) min(100, round($corePercentage + $sportBonus));
    }

    protected static function getProfileMilestoneLabel(int $completion): string
    {
        if ($completion >= 100) {
            return 'PlyrCard Complete';
        }

        if ($completion >= 75) {
            return 'Scouted Ready';
        }

        if ($completion >= 50) {
            return 'Rising Talent';
        }

        if ($completion >= 25) {
            return 'Starter';
        }

        return 'Below Starter';
    }

    protected static function hasProfileValue(mixed $value): bool
    {
        if (is_null($value)) {
            return false;
        }

        if (is_string($value)) {
            return trim($value) !== '' && trim($value) !== '[]' && trim($value) !== 'null';
        }

        if (is_array($value)) {
            return count(array_filter(
                $value,
                fn ($item) => ! is_null($item) && $item !== ''
            )) > 0;
        }

        return true;
    }

    public static function table(Table $table): Table
{
    return $table
        ->modifyQueryUsing(fn (Builder $query) => $query->with([
            'roles',
            'websites',
            'school',
            'league',
            'club',
            'nationalTeam',
        ]))
        ->columns([
            TextColumn::make('name')
                ->label('Name')
                ->state(fn (User $record): string => trim($record->first_name . ' ' . $record->last_name) ?: '-')
                ->searchable(['first_name', 'last_name'])
                ->sortable(['first_name', 'last_name']),

            TextColumn::make('email')
                ->label('Email')
                ->searchable()
                ->copyable()
                ->toggleable(),

            TextColumn::make('roles.name')
                ->label('Roles')
                ->badge()
                ->separator(',')
                ->searchable()
                ->toggleable(isToggledHiddenByDefault: true),

            TextColumn::make('profile_completion')
                ->label('Profile Completion')
                ->state(fn (User $record): string => static::calculateProfileCompletion($record) . '%')
                ->badge()
                ->color(function (User $record): string {
                    $completion = static::calculateProfileCompletion($record);

                    return match (true) {
                        $completion >= 100 => 'success',
                        $completion >= 75 => 'info',
                        $completion >= 50 => 'warning',
                        $completion >= 25 => 'gray',
                        default => 'danger',
                    };
                })
                ->toggleable(),

            TextColumn::make('profile_milestone')
                ->label('Profile Milestone')
                ->state(fn (User $record): string => static::getProfileMilestoneLabel(static::calculateProfileCompletion($record)))
                ->badge()
                ->color(function (User $record): string {
                    $completion = static::calculateProfileCompletion($record);

                    return match (true) {
                        $completion >= 100 => 'success',
                        $completion >= 75 => 'info',
                        $completion >= 50 => 'warning',
                        $completion >= 25 => 'gray',
                        default => 'danger',
                    };
                })
                ->toggleable(),

            TextColumn::make('sport')
                ->label('Sport')
                ->badge()
                ->formatStateUsing(fn (?string $state): string => filled($state) ? str($state)->replace('_', ' ')->title() : '-')
                ->sortable()
                ->searchable(),

            TextColumn::make('gender')
                ->label('Gender')
                ->badge()
                ->formatStateUsing(fn (?string $state): string => filled($state) ? str($state)->title() : '-')
                ->sortable()
                ->toggleable(),

            TextColumn::make('team_name')
                ->label('Team')
                ->searchable()
                ->toggleable(isToggledHiddenByDefault: true),

            TextColumn::make('club.name')
                ->label('Club')
                ->searchable()
                ->toggleable(isToggledHiddenByDefault: true),

            TextColumn::make('league.name')
                ->label('League')
                ->searchable()
                ->toggleable(isToggledHiddenByDefault: true),

            TextColumn::make('school.name')
                ->label('School')
                ->searchable()
                ->toggleable(isToggledHiddenByDefault: true),

            TextColumn::make('updated_at')
                ->label('Updated')
                ->since()
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),

            TextColumn::make('personal_email')
                ->label('Personal Email')
                ->searchable()
                ->toggleable(isToggledHiddenByDefault: true),

            TextColumn::make('phone')
                ->label('Phone')
                ->searchable()
                ->toggleable(isToggledHiddenByDefault: true),

            TextColumn::make('nationalTeam.name')
                ->label('National Team')
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

            TextColumn::make('ncaa_field_id')
                ->label('NCAA Field ID')
                ->searchable()
                ->toggleable(isToggledHiddenByDefault: true),

            TextColumn::make('pro_club_name')
                ->label('Pro Club')
                ->searchable()
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

            TextColumn::make('websites.domain')
                ->label('Custom Domain')
                ->state(function (User $record): string {
                    return $record->websites
                        ->pluck('domain')
                        ->filter(fn ($domain) => filled($domain))
                        ->unique()
                        ->implode(', ') ?: '-';
                })
                ->searchable(query: function (Builder $query, string $search): Builder {
                    return $query->orWhereHas('websites', function (Builder $query) use ($search) {
                        $query->where('domain', 'like', '%' . $search . '%');
                    });
                })
                ->toggleable(isToggledHiddenByDefault: true),

            TextColumn::make('created_at')
                ->label('Created')
                ->dateTime()
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),
        ])
        ->filters([
            TrashedFilter::make()
                ->label('Deleted Users'),

            SelectFilter::make('profile_milestone_band')
                ->label('Profile Completion Band')
                ->options(static::profileMilestoneBandOptions())
                ->query(fn (Builder $query, array $data): Builder => static::applyProfileMilestoneBand(
                    $query,
                    $data['value'] ?? null,
                ))
                ->indicator('Profile completion band'),

            SelectFilter::make('profile_milestone_reached')
                ->label('Reached Milestone')
                ->options(static::profileMilestoneOptions())
                ->query(fn (Builder $query, array $data): Builder => static::applyProfileMilestoneReached(
                    $query,
                    $data['value'] ?? null,
                ))
                ->indicator('Reached milestone'),

            TernaryFilter::make('profile_exact_milestone')
                ->label('Exactly On Milestone')
                ->placeholder('All users')
                ->trueLabel('Exactly 25%, 50%, 75%, or 100%')
                ->falseLabel('Not exactly on a milestone')
                ->queries(
                    true: fn (Builder $query): Builder => static::applyExactProfileMilestoneFilter($query, true),
                    false: fn (Builder $query): Builder => static::applyExactProfileMilestoneFilter($query, false),
                    blank: fn (Builder $query): Builder => $query,
                ),

            Filter::make('profile_completion_range')
                ->label('Profile Completion Range')
                ->form([
                    TextInput::make('completion_min')
                        ->label('Minimum %')
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(100),
                    TextInput::make('completion_max')
                        ->label('Maximum %')
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(100),
                ])
                ->query(function (Builder $query, array $data): Builder {
                    return static::applyProfileCompletionRange(
                        $query,
                        filled($data['completion_min'] ?? null) ? (int) $data['completion_min'] : null,
                        filled($data['completion_max'] ?? null) ? (int) $data['completion_max'] : null,
                    );
                })
                ->indicateUsing(function (array $data): array {
                    $indicators = [];

                    if (filled($data['completion_min'] ?? null)) {
                        $indicators[] = 'Completion ≥ ' . $data['completion_min'] . '%';
                    }

                    if (filled($data['completion_max'] ?? null)) {
                        $indicators[] = 'Completion ≤ ' . $data['completion_max'] . '%';
                    }

                    return $indicators;
                }),

            SelectFilter::make('missing_profile_sections')
                ->label('Missing Profile Sections')
                ->multiple()
                ->options(collect(static::profileSections())->mapWithKeys(fn (array $section, string $key): array => [
                    $key => $section['label'],
                ])->all())
                ->query(function (Builder $query, array $data): Builder {
                    $sections = $data['values'] ?? [];

                    foreach ($sections as $section) {
                        static::applyMissingProfileSection($query, $section);
                    }

                    return $query;
                })
                ->indicator('Missing profile sections'),

            TernaryFilter::make('has_complete_core_profile')
                ->label('Core Profile Complete')
                ->queries(
                    true: function (Builder $query): Builder {
                        foreach (array_keys(static::profileCoreFields()) as $field) {
                            $query->whereRaw(static::fieldHasValueSql($field));
                        }

                        return $query;
                    },
                    false: function (Builder $query): Builder {
                        return $query->where(function (Builder $query): Builder {
                            foreach (array_keys(static::profileCoreFields()) as $field) {
                                $query->orWhereRaw('NOT ' . static::fieldHasValueSql($field));
                            }

                            return $query;
                        });
                    },
                    blank: fn (Builder $query): Builder => $query,
                ),

            TernaryFilter::make('has_complete_sport_profile')
                ->label('Sport-Specific Profile Complete')
                ->queries(
                    true: function (Builder $query): Builder {
                        foreach (array_keys(static::profileSportSpecificFields()) as $field) {
                            $query->whereRaw(static::fieldHasValueSql($field));
                        }

                        return $query;
                    },
                    false: function (Builder $query): Builder {
                        return $query->where(function (Builder $query): Builder {
                            foreach (array_keys(static::profileSportSpecificFields()) as $field) {
                                $query->orWhereRaw('NOT ' . static::fieldHasValueSql($field));
                            }

                            return $query;
                        });
                    },
                    blank: fn (Builder $query): Builder => $query,
                ),

            SelectFilter::make('sport')
                ->label('Sport')
                ->options(static::getSportOptions())
                ->multiple()
                ->searchable()
                ->preload(),

            SelectFilter::make('gender')
                ->label('Gender')
                ->options(static::getGenderOptions())
                ->multiple()
                ->searchable(),

            SelectFilter::make('roles')
                ->label('Role')
                ->relationship('roles', 'name')
                ->multiple()
                ->searchable()
                ->preload(),

            SelectFilter::make('school_id')
                ->label('School')
                ->relationship('school', 'name')
                ->searchable()
                ->preload(),

            SelectFilter::make('league_id')
                ->label('League')
                ->relationship('league', 'name')
                ->searchable()
                ->preload(),

            SelectFilter::make('club_id')
                ->label('Club')
                ->relationship('club', 'name')
                ->searchable()
                ->preload(),

            SelectFilter::make('national_team_id')
                ->label('National Team')
                ->relationship('nationalTeam', 'name')
                ->searchable()
                ->preload(),

            SelectFilter::make('year')
                ->label('Graduation Year')
                ->options(
                    User::query()
                        ->whereNotNull('year')
                        ->orderBy('year')
                        ->pluck('year', 'year')
                        ->mapWithKeys(fn ($year) => [(string) $year => (string) $year])
                        ->all()
                )
                ->searchable(),

            TernaryFilter::make('website_published')
                ->label('Website Published')
                ->queries(
                    true: fn (Builder $query) => $query->whereHas('websites', fn (Builder $q) => $q->where('is_published', true)),
                    false: fn (Builder $query) => $query->whereDoesntHave('websites')
                        ->orWhereHas('websites', fn (Builder $q) => $q->where('is_published', false)),
                    blank: fn (Builder $query) => $query,
                ),

            TernaryFilter::make('has_domain')
                ->label('Has Custom Domain')
                ->queries(
                    true: fn (Builder $query) => $query->whereHas('websites', function (Builder $q) {
                        $q->whereNotNull('domain')->where('domain', '!=', '');
                    }),
                    false: fn (Builder $query) => $query->whereDoesntHave('websites', function (Builder $q) {
                        $q->whereNotNull('domain')->where('domain', '!=', '');
                    }),
                    blank: fn (Builder $query) => $query,
                ),

            TernaryFilter::make('has_personal_email')
                ->label('Has Personal Email')
                ->queries(
                    true: fn (Builder $query) => $query->whereNotNull('personal_email')->where('personal_email', '!=', ''),
                    false: fn (Builder $query) => $query->where(function (Builder $q) {
                        $q->whereNull('personal_email')->orWhere('personal_email', '');
                    }),
                    blank: fn (Builder $query) => $query,
                ),

            TernaryFilter::make('has_phone')
                ->label('Has Phone')
                ->queries(
                    true: fn (Builder $query) => $query->whereNotNull('phone')->where('phone', '!=', ''),
                    false: fn (Builder $query) => $query->where(function (Builder $q) {
                        $q->whereNull('phone')->orWhere('phone', '');
                    }),
                    blank: fn (Builder $query) => $query,
                ),

            Filter::make('created_at')
                ->label('Created Date')
                ->form([
                    DatePicker::make('created_from')->label('Created From'),
                    DatePicker::make('created_until')->label('Created Until'),
                ])
                ->query(function (Builder $query, array $data): Builder {
                    return $query
                        ->when(
                            $data['created_from'] ?? null,
                            fn (Builder $q, $date) => $q->whereDate('created_at', '>=', $date)
                        )
                        ->when(
                            $data['created_until'] ?? null,
                            fn (Builder $q, $date) => $q->whereDate('created_at', '<=', $date)
                        );
                })
                ->indicateUsing(function (array $data): array {
                    $indicators = [];

                    if ($data['created_from'] ?? null) {
                        $indicators[] = 'Created from ' . $data['created_from'];
                    }

                    if ($data['created_until'] ?? null) {
                        $indicators[] = 'Created until ' . $data['created_until'];
                    }

                    return $indicators;
                }),

            Filter::make('updated_at')
                ->label('Updated Date')
                ->form([
                    DatePicker::make('updated_from')->label('Updated From'),
                    DatePicker::make('updated_until')->label('Updated Until'),
                ])
                ->query(function (Builder $query, array $data): Builder {
                    return $query
                        ->when(
                            $data['updated_from'] ?? null,
                            fn (Builder $q, $date) => $q->whereDate('updated_at', '>=', $date)
                        )
                        ->when(
                            $data['updated_until'] ?? null,
                            fn (Builder $q, $date) => $q->whereDate('updated_at', '<=', $date)
                        );
                })
                ->indicateUsing(function (array $data): array {
                    $indicators = [];

                    if ($data['updated_from'] ?? null) {
                        $indicators[] = 'Updated from ' . $data['updated_from'];
                    }

                    if ($data['updated_until'] ?? null) {
                        $indicators[] = 'Updated until ' . $data['updated_until'];
                    }

                    return $indicators;
                }),

            Filter::make('gpa_range')
                ->label('GPA Range')
                ->form([
                    TextInput::make('gpa_min')
                        ->label('Min GPA')
                        ->numeric()
                        ->step('0.01'),
                    TextInput::make('gpa_max')
                        ->label('Max GPA')
                        ->numeric()
                        ->step('0.01'),
                ])
                ->query(function (Builder $query, array $data): Builder {
                    return $query
                        ->when(
                            filled($data['gpa_min'] ?? null),
                            fn (Builder $q) => $q->where('gpa', '>=', $data['gpa_min'])
                        )
                        ->when(
                            filled($data['gpa_max'] ?? null),
                            fn (Builder $q) => $q->where('gpa', '<=', $data['gpa_max'])
                        );
                })
                ->indicateUsing(function (array $data): array {
                    $indicators = [];

                    if (filled($data['gpa_min'] ?? null)) {
                        $indicators[] = 'GPA ≥ ' . $data['gpa_min'];
                    }

                    if (filled($data['gpa_max'] ?? null)) {
                        $indicators[] = 'GPA ≤ ' . $data['gpa_max'];
                    }

                    return $indicators;
                }),
        ], layout: FiltersLayout::AboveContentCollapsible)
        ->filtersFormColumns(3)
        ->recordAction('edit')
        ->recordUrl(null)
        ->bulkActions([
            BulkActionGroup::make([
                DeleteBulkAction::make(),
                RestoreBulkAction::make(),
                ForceDeleteBulkAction::make(),
            ]),
        ])
        ->actions([
            EditAction::make()
                ->label('Edit')
                ->icon('heroicon-m-pencil-square')
                ->iconButton()
                ->tooltip('Edit')
                ->url(null)
                ->modalHeading(fn (User $record) => 'Edit ' . $record->first_name . ' ' . $record->last_name)
                ->modalSubmitActionLabel('Save changes')
                ->modalWidth('7xl')
                ->slideOver(),

            Action::make('editAccess')
                ->label('Edit Access')
                ->icon('heroicon-m-shield-check')
                ->iconButton()
                ->tooltip('Edit Access')
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
                ->iconButton()
                ->tooltip('Impersonate')
                ->visible(fn (User $record) => auth()->id() !== $record->id
                    && auth()->user()?->hasRole('Superadmin'))
                ->redirectTo('/admin'),
        ]);
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