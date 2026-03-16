<?php

namespace App\Filament\Resources\Users;

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Pages\ViewUser;
use App\Models\User;
use BackedEnum;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
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
use UnitEnum;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;
    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::UserGroup;
    protected static string|UnitEnum|null $navigationGroup = 'Users & Permissions';
    protected static ?string $recordTitleAttribute = 'first_name';

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

                    TextInput::make('phone')
                        ->tel()
                        ->maxLength(255),

                    Select::make('school_id')
                        ->relationship('school', 'name')
                        ->searchable()
                        ->preload()
                        ->nullable(),

                    Select::make('club_id')
                        ->relationship('club', 'name')
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

                    TextInput::make('team_name')
                        ->maxLength(255),

                    Select::make('sport')
                        ->label('Sport')
                        ->options(static::getSportOptions())
                        ->required()
                        ->searchable()
                        ->live(),

                    Select::make('position')
                        ->label('Position')
                        ->multiple()
                        ->searchable()
                        ->preload()
                        ->required()
                        ->options(fn (callable $get): array => match ($get('sport')) {
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
                        })
                        ->disabled(fn (callable $get): bool => blank($get('sport')))
                        ->helperText('Select one or more positions based on the chosen sport.'),

                    Toggle::make('natl_team_exp')
                        ->label('National Team Experience'),

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
                        ->imageEditor()
                        ->disk('public')
                        ->directory('user-player-images')
                        ->visibility('public')
                        ->helperText('Upload the card-style PNG image used across templates.'),

                    FileUpload::make('player_image')
                        ->label('Player Image')
                        ->image()
                        ->imageEditor()
                        ->disk('public')
                        ->directory('user-player-images')
                        ->visibility('public')
                        ->helperText('Upload the half-body player PNG image used across templates.'),

                    FileUpload::make('mobile_hero_image')
                        ->label('Vertical Hero Image')
                        ->image()
                        ->imageEditor()
                        ->disk('public')
                        ->directory('user-player-images')
                        ->visibility('public')
                        ->helperText('Upload the vertical/mobile hero image used for responsive hero layouts.'),

                    FileUpload::make('youtube_thumbnail')
                        ->label('YouTube Thumbnail')
                        ->image()
                        ->imageEditor()
                        ->disk('public')
                        ->directory('user-player-images')
                        ->visibility('public')
                        ->helperText('Used for highlights thumbnail, social sharing image, and SEO preview image.'),

                    FileUpload::make('logos_image')
                        ->label('Logos')
                        ->image()
                        ->imageEditor()
                        ->disk('public')
                        ->directory('user-player-images')
                        ->visibility('public')
                        ->helperText('Upload the logos image used in the footer or logo area of the website.'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('first_name')->searchable()->sortable(),
                TextColumn::make('last_name')->searchable()->sortable(),
                TextColumn::make('email')->searchable(),
                TextColumn::make('school.name')->label('School')->toggleable(),
                TextColumn::make('club.name')->label('Club')->toggleable(),
                TextColumn::make('roles.name')->badge(),
                TextColumn::make('updated_at')->since()->label('Updated'),
                TextColumn::make('sport')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => str($state)->replace('_', ' ')->title())
                    ->sortable()
                    ->searchable(),

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

                SelectFilter::make('league')
                    ->label('League')
                    ->options(function (): array {
                        return User::query()
                            ->whereHas('club.league')
                            ->with('club.league')
                            ->get()
                            ->filter(fn ($user) => $user->club?->league?->id && $user->club?->league?->name)
                            ->mapWithKeys(fn ($user) => [
                                $user->club->league->id => $user->club->league->name,
                            ])
                            ->sort()
                            ->all();
                    })
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;

                        if (blank($value)) {
                            return $query;
                        }

                        return $query->whereHas('club.league', fn (Builder $q) => $q->whereKey($value));
                    }),

                TernaryFilter::make('natl_team_exp')
                    ->label('National Team Experience')
                    ->nullable(),

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