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
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
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
use STS\FilamentImpersonate\Actions\Impersonate;
use UnitEnum;
use Filament\Forms\Components\Hidden;
use Filament\Tables\Columns\ToggleColumn;
// use Filament\Tables\Actions\Action;
use Filament\Forms\Components\Toggle;
use Spatie\Permission\Models\Role;
use Filament\Actions\Action;


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

    protected static function getTeamOptions(): array
    {
        return ['__new__' => 'Add New'] + Team::query()
            ->with(['club.league'])
            ->orderBy('name')
            ->get()
            ->mapWithKeys(function (Team $team) {
                $clubName = $team->club?->name;
                $leagueName = $team->club?->league?->name;

                $label = $team->name;

                if ($clubName || $leagueName) {
                    $suffix = collect([$clubName, $leagueName])->filter()->implode(' • ');
                    $label .= ' (' . $suffix . ')';
                }

                return [(string) $team->id => $label];
            })
            ->all();
    }

    protected static function getClubOptions(): array
    {
        return Club::query()
            ->orderBy('name')
            ->pluck('name', 'id')
            ->mapWithKeys(fn ($name, $id) => [(string) $id => $name])
            ->all();
    }

    protected static function getLeagueOptions(): array
    {
        return League::query()
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
    public static function mutateUserFormData(array $data): array
    {
        if (filled($data['password'] ?? null)) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
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
            Section::make('Basic Information')
                ->columns(2)
                ->schema([
                    TextInput::make('first_name')
                        ->required()
                        ->maxLength(255),

                    TextInput::make('last_name')
                        ->required()
                        ->maxLength(255),

                    TextInput::make('personal_email')
                        ->email()
                        ->maxLength(255)
                        ->unique(ignoreRecord: true),

                    TextInput::make('email')
                        ->label('PlyrCard Email')
                        ->email()
                        ->required()
                        ->maxLength(255)
                        ->unique(ignoreRecord: true),

                    TextInput::make('password')
                        ->label('Password')
                        ->password()
                        ->revealable()
                        ->dehydrated(fn ($state) => filled($state))
                        ->same('password_confirmation')
                        ->nullable()
                        ->helperText('Leave blank to keep the current password.'),

                    TextInput::make('password_confirmation')
                        ->label('Confirm Password')
                        ->password()
                        ->revealable()
                        ->dehydrated(false)
                        ->nullable(),

                    TextInput::make('phone')
                        ->tel()
                        ->maxLength(255),

                    Select::make('school_id')
                        ->relationship('school', 'name')
                        ->searchable()
                        ->preload()
                        ->nullable(),

                    CheckboxList::make('roles')
                        ->relationship('roles', 'name')
                        ->columns(2)
                        ->searchable()
                        ->columnSpanFull(),

                    TextInput::make('street')->maxLength(255),
                    TextInput::make('city')->maxLength(255),
                    TextInput::make('state')->maxLength(255),
                    TextInput::make('country')->maxLength(255),
                ]),

            Section::make('Organization Details')
                ->columns(2)
                ->schema([
                    Select::make('team_id')
                        ->label('Team')
                        ->options(fn () => static::getTeamOptions())
                        ->searchable()
                        ->preload()
                        ->live()
                        ->required(false)
                        ->afterStateHydrated(function (Select $component, $state, $record) {
                            if (! $record || blank($record->team_name)) {
                                return;
                            }

                            $team = Team::query()
                                ->with('club.league')
                                ->where('name', $record->team_name)
                                ->when($record->club_id, fn ($query) => $query->where('club_id', $record->club_id))
                                ->first();

                            if ($team) {
                                $component->state((string) $team->id);
                            }
                        })
                        ->afterStateUpdated(function (callable $set, $state) {
                            if ($state === '__new__') {
                                $set('club_id', null);
                                $set('league_id', null);
                                $set('team_name', null);
                                return;
                            }

                            if (blank($state)) {
                                $set('club_id', null);
                                $set('league_id', null);
                                $set('team_name', null);
                                return;
                            }

                            $team = Team::with('club.league')->find($state);

                            $set('team_name', $team?->name);
                            $set('club_id', $team?->club?->id ? (string) $team->club->id : null);
                            $set('league_id', $team?->club?->league?->id ? (string) $team->club->league->id : null);
                        })
                        ->helperText('Select a team to auto-fill club and league, or choose Add New.'),

                    Select::make('league_id')
                        ->label('League')
                        ->options(fn () => static::getLeagueOptions())
                        ->searchable()
                        ->preload()
                        ->disabled()
                        ->dehydrated()
                        ->helperText('Auto-filled from the selected team.'),

                    Select::make('club_id')
                        ->label('Club')
                        ->options(fn () => static::getClubOptions())
                        ->searchable()
                        ->preload()
                        ->disabled()
                        ->dehydrated()
                        ->helperText('Auto-filled from the selected team.'),

                    TextInput::make('team_name')
                        ->label('Team Name')
                        ->disabled()
                        ->dehydrated()
                        ->visible(fn (callable $get) => $get('team_id') !== '__new__' && filled($get('team_id')))
                        ->helperText('Auto-filled from the selected team.'),

                    TextInput::make('new_team_name')
                        ->label('New Team Name')
                        ->maxLength(255)
                        ->visible(fn (callable $get) => $get('team_id') === '__new__')
                        ->required(fn (callable $get) => $get('team_id') === '__new__'),

                    TextInput::make('new_club_name')
                        ->label('New Club Name')
                        ->maxLength(255)
                        ->visible(fn (callable $get) => $get('team_id') === '__new__')
                        ->required(fn (callable $get) => $get('team_id') === '__new__'),

                    FileUpload::make('new_club_logo')
                        ->label('New Club Logo')
                        ->image()
                        ->downloadable()
                        ->imageEditor()
                        ->disk('public')
                        ->directory('club-logos')
                        ->visibility('public')
                        ->visible(fn (callable $get) => $get('team_id') === '__new__'),

                    TextInput::make('new_league_name')
                        ->label('New League Name')
                        ->maxLength(255)
                        ->visible(fn (callable $get) => $get('team_id') === '__new__')
                        ->required(fn (callable $get) => $get('team_id') === '__new__'),

                    FileUpload::make('new_league_logo')
                        ->label('New League Logo')
                        ->image()
                        ->downloadable()
                        ->imageEditor()
                        ->disk('public')
                        ->directory('league-logos')
                        ->visibility('public')
                        ->visible(fn (callable $get) => $get('team_id') === '__new__'),

                    Select::make('national_team_id')
                        ->label('National Team')
                        ->options(fn () => static::getNationalTeamOptions())
                        ->searchable()
                        ->preload()
                        ->live(),

                    TextInput::make('new_national_team_name')
                        ->label('New National Team Name')
                        ->maxLength(255)
                        ->visible(fn (callable $get) => $get('national_team_id') === '__new__')
                        ->required(fn (callable $get) => $get('national_team_id') === '__new__'),

                    FileUpload::make('new_national_team_logo')
                        ->label('New National Team Logo')
                        ->image()
                        ->downloadable()
                        ->imageEditor()
                        ->disk('public')
                        ->directory('national-team-logos')
                        ->visibility('public')
                        ->visible(fn (callable $get) => $get('national_team_id') === '__new__'),
                ]),

            Section::make('Athletic & Academic Info')
                ->columns(2)
                ->schema([
                    TextInput::make('gpa')
                        ->numeric()
                        ->step('0.01'),

                    TextInput::make('year')
                        ->label('Graduation Year')
                        ->numeric()
                        ->minValue(2000)
                        ->maxValue(2100),

                    DatePicker::make('birth')
                        ->label('Birth Year'),

                    Select::make('gender')
                        ->label('Gender')
                        ->options(static::getGenderOptions())
                        ->searchable()
                        ->nullable(),

                    TextInput::make('jersey_number')
                        ->numeric(),

                    TextInput::make('height')
                        ->label('Height')
                        ->maxLength(255)
                        ->placeholder('e.g. 6\'2" or 188 cm'),

                    TextInput::make('weight')
                        ->label('Weight')
                        ->maxLength(255)
                        ->placeholder('e.g. 185 lbs or 84 kg'),

                    Select::make('sport')
                        ->label('Sport')
                        ->options(static::getSportOptions())
                        ->required()
                        ->searchable()
                        ->live(),

                    Select::make('dominant_foot')
                        ->label('Dominant Foot')
                        ->options([
                            'left' => 'Left',
                            'right' => 'Right',
                            'both' => 'Both',
                        ])
                        ->visible(fn (callable $get) => $get('sport') === 'soccer')
                        ->required(fn (callable $get) => $get('sport') === 'soccer'),

                    Select::make('position')
                        ->label('Position')
                        ->multiple()
                        ->searchable()
                        ->preload()
                        ->required()
                        ->options(fn (callable $get): array => static::getPositionOptions($get('sport')))
                        ->disabled(fn (callable $get): bool => blank($get('sport')))
                        ->helperText('Select one or more positions based on the chosen sport.'),

                    Textarea::make('player_bio')
                        ->label('Player Bio')
                        ->rows(6)
                        ->columnSpanFull(),

                    Textarea::make('academic_accolades')
                        ->label('Academic Accolades')
                        ->rows(5)
                        ->helperText('Enter one accolade per line.')
                        ->columnSpanFull(),

                    Textarea::make('sports_accolades')
                        ->label('Sports Accolades')
                        ->rows(5)
                        ->helperText('Enter one accolade per line.')
                        ->columnSpanFull(),
                ]),

            Section::make('Social & Media')
                ->columns(2)
                ->schema([
                    TextInput::make('ig_handle')->prefix('@')->maxLength(255),
                    TextInput::make('x_handle')->prefix('@')->maxLength(255),
                    TextInput::make('yt_url')->url()->columnSpanFull(),
                    TextInput::make('featured_video_url')
                        ->label('Featured Video URL')
                        ->url()
                        ->columnSpanFull(),
                    Textarea::make('featured_video_urls')
                        ->label('Featured Video URLs')
                        ->rows(5)
                        ->helperText('Enter one video URL per line.')
                        ->columnSpanFull(),
                    Textarea::make('press')->rows(5)->columnSpanFull(),
                ]),

            Section::make('Parents / Guardians')
                ->columns(3)
                ->schema([
                    TextInput::make('parent')->label('Primary Parent')->maxLength(255),
                    TextInput::make('parent_email')->email()->maxLength(255),
                    TextInput::make('parent_phone')->tel()->maxLength(255),

                    TextInput::make('sec_parent')->label('Secondary Parent')->maxLength(255),
                    TextInput::make('sec_parent_email')->email()->maxLength(255),
                    TextInput::make('sec_parent_phone')->tel()->maxLength(255),
                ]),

            Section::make('Coaches')
                ->columns(3)
                ->schema([
                    TextInput::make('club_coach')->maxLength(255),
                    TextInput::make('club_coach_email')->email()->maxLength(255),
                    TextInput::make('club_coach_phone')->tel()->maxLength(255),

                    TextInput::make('natl_coach')->maxLength(255),
                    TextInput::make('natl_coach_email')->email()->maxLength(255),
                    TextInput::make('natl_coach_phone')->tel()->maxLength(255),
                ]),

            Section::make('Trainers')
                ->columns(3)
                ->schema([
                    TextInput::make('tech_trainer')->label('Technical Trainer')->maxLength(255),
                    TextInput::make('tech_trainer_email')->email()->maxLength(255),
                    TextInput::make('tech_trainer_phone')->tel()->maxLength(255),

                    TextInput::make('snc_trainer')->label('Strength & Conditioning Trainer')->maxLength(255),
                    TextInput::make('snc_trainer_email')->email()->maxLength(255),
                    TextInput::make('snc_trainer_phone')->tel()->maxLength(255),
                ]),

            Section::make('Website')
                ->schema([
                    TextInput::make('domain')
                        ->label('Custom Domain')
                        ->helperText('Enter without https://')
                        ->maxLength(255)
                        ->unique(ignoreRecord: true)
                        ->nullable(),
                ]),

            Section::make('Hero Images')
                ->description('Shared player images used across hero templates.')
                ->columns(5)
                ->schema([
                    FileUpload::make('plyrcard_image')
                        ->label('PlyrCard Image')
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
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['roles', 'websites']))
            ->columns([                TextColumn::make('first_name')->searchable()->sortable(),
                TextColumn::make('last_name')->searchable()->sortable(),
                TextColumn::make('email')->searchable(),

                TextColumn::make('gender')
                    ->label('Gender')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => filled($state) ? str($state)->title() : '-')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('school.name')->label('School')->toggleable(),
                TextColumn::make('league.name')->label('League')->toggleable(),
                TextColumn::make('club.name')->label('Club')->toggleable(),
                TextColumn::make('team_name')->label('Team')->toggleable(),
                TextColumn::make('nationalTeam.name')->label('National Team')->toggleable(),
                TextColumn::make('roles.name')
                    ->label('Roles')
                    ->badge()
                    ->separator(','),
                TextColumn::make('updated_at')->since()->label('Updated'),

                TextColumn::make('sport')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => filled($state) ? str($state)->replace('_', ' ')->title() : '-')
                    ->sortable()
                    ->searchable(),

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
                    ->badge(),
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