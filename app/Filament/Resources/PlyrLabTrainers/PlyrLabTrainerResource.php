<?php

namespace App\Filament\Resources\PlyrLabTrainers;

use App\Filament\Resources\PlyrLabTrainers\Pages\CreatePlyrLabTrainer;
use App\Filament\Resources\PlyrLabTrainers\Pages\EditPlyrLabTrainer;
use App\Filament\Resources\PlyrLabTrainers\Pages\ListPlyrLabTrainers;
use App\Models\PlyrLabTrainer;
use App\Models\User;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class PlyrLabTrainerResource extends Resource
{
    protected static ?string $model = PlyrLabTrainer::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedBeaker;
    protected static ?string $navigationLabel = 'PLYRLab Trainers';
    protected static ?string $modelLabel = 'PLYRLab Trainer';
    protected static ?string $pluralModelLabel = 'PLYRLab Trainers';
    protected static string | UnitEnum | null $navigationGroup = 'Operations & Support';
    protected static ?int $navigationSort = 15;

    protected static function canManagePlyrLab(): bool
    {
        $user = auth()->user();

        return $user
            && method_exists($user, 'isSuperadminOrImpersonating')
            && $user->isSuperadminOrImpersonating();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canManagePlyrLab();
    }

    public static function canViewAny(): bool
    {
        return static::canManagePlyrLab();
    }

    public static function canCreate(): bool
    {
        return static::canViewAny();
    }

    public static function canEdit(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function canDelete(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Trainer profile')
                ->columns(2)
                ->schema([
                    Select::make('user_id')
                        ->label('Registered PLYRCARD user')
                        ->relationship('user', 'email')
                        ->getOptionLabelFromRecordUsing(
                            fn (User $record): string => trim($record->first_name . ' ' . $record->last_name) . ' · ' . $record->email
                        )
                        ->searchable(['first_name', 'last_name', 'email'])
                        ->preload()
                        ->required()
                        ->unique(ignoreRecord: true),
                    TextInput::make('display_name')
                        ->label('Public trainer name')
                        ->maxLength(255)
                        ->helperText('Leave blank to use the registered user name.'),
                    TextInput::make('headline')
                        ->maxLength(255)
                        ->placeholder('Speed & performance trainer'),
                    TextInput::make('location')
                        ->maxLength(255),
                    TagsInput::make('specialties')
                        ->placeholder('Speed, jumping, strength')
                        ->columnSpanFull(),
                    Textarea::make('bio')
                        ->rows(4)
                        ->maxLength(2000)
                        ->columnSpanFull(),
                    TextInput::make('booking_duration_minutes')
                        ->label('Default booking duration')
                        ->numeric()
                        ->minValue(15)
                        ->maxValue(480)
                        ->suffix('minutes')
                        ->default(60)
                        ->required(),
                    Toggle::make('is_active')
                        ->label('Available for player booking')
                        ->default(true),
                ]),

            Section::make('Plyomat connection')
                ->description('Use a Plyomat developer API key with Athlete Read and Athlete Write access. Credentials are encrypted at rest and never exposed in Locker Room.')
                ->columns(2)
                ->schema([
                    TextInput::make('plyomat_base_url')
                        ->label('API base URL')
                        ->url()
                        ->maxLength(500)
                        ->placeholder('https://...'),
                    TextInput::make('plyomat_api_key')
                        ->label('API key')
                        ->password()
                        ->revealable()
                        ->dehydrated(fn (?string $state): bool => filled($state))
                        ->helperText('The key needs Athlete Read + Athlete Write access. Leave blank when editing to keep the current encrypted key.'),
                    TextInput::make('plyomat_auth_header')
                        ->label('Auth header')
                        ->default('Authorization')
                        ->maxLength(100),
                    TextInput::make('plyomat_auth_prefix')
                        ->label('Auth prefix')
                        ->default('Bearer')
                        ->maxLength(100)
                        ->helperText('For example Bearer. Leave blank if the API expects the raw key.'),
                    TextInput::make('plyomat_account_id')
                        ->label('Trainer Plyomat account ID')
                        ->maxLength(255),
                    TextInput::make('plyomat_create_athlete_path')
                        ->label('Athlete Write endpoint')
                        ->maxLength(500)
                        ->placeholder('/api/athletes')
                        ->helperText('Used when a player books this trainer. Requires Athlete Write access.'),
                    TextInput::make('plyomat_stats_path')
                        ->label('Athlete Read endpoint')
                        ->maxLength(500)
                        ->placeholder('/api/athletes/{athlete_id}')
                        ->helperText('Used to refresh the player in PLYRLab. Requires Athlete Read access and supports {athlete}, {athlete_id}, or {id}.'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('display_name')
                    ->label('Trainer')
                    ->formatStateUsing(fn (?string $state, PlyrLabTrainer $record): string => $record->displayName())
                    ->searchable(),
                TextColumn::make('user.email')
                    ->label('PLYRCARD account')
                    ->searchable(),
                TextColumn::make('headline')
                    ->placeholder('PLYRLab Performance Trainer')
                    ->toggleable(),
                IconColumn::make('is_active')
                    ->label('Bookable')
                    ->boolean(),
                IconColumn::make('api_connected')
                    ->label('Plyomat')
                    ->state(fn (PlyrLabTrainer $record): bool => $record->hasPlyomatConnection())
                    ->boolean(),
                TextColumn::make('bookings_count')
                    ->label('Bookings')
                    ->counts('bookings'),
                TextColumn::make('updated_at')
                    ->since()
                    ->label('Updated'),
            ])
            ->defaultSort('display_name');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPlyrLabTrainers::route('/'),
            'create' => CreatePlyrLabTrainer::route('/create'),
            'edit' => EditPlyrLabTrainer::route('/{record}/edit'),
        ];
    }
}
