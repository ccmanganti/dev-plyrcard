<?php

namespace App\Filament\Resources\Users;

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Pages\ViewUser;
use App\Models\User;
use BackedEnum;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;
use Filament\Forms\Components\DatePicker;

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
                        ->maxLength(255)
                        ->unique(ignoreRecord: true),

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

                    Textarea::make('accolades')
                        ->rows(5)
                        ->columnSpanFull(),
                ]),

            Section::make('Social & Media')
                ->columns(2)
                ->schema([
                    TextInput::make('ig_handle')->prefix('@')->maxLength(255),
                    TextInput::make('x_handle')->prefix('@')->maxLength(255),
                    TextInput::make('yt_url')->url()->columnSpanFull(),
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
            ->filters([
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