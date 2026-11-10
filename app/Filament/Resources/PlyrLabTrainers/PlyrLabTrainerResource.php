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

        return (bool) ($user
            && method_exists($user, 'hasRole')
            && (
                $user->hasRole('Superadmin')
                || $user->hasRole('superadmin')
                || $user->hasRole('Super Admin')
            ));
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
                ->description('Only the Plyomat API key is required. PLYRCARD uses Plyomat’s documented API automatically to create athletes and read their data.')
                ->schema([
                    TextInput::make('plyomat_api_key')
                        ->label('API key')
                        ->password()
                        ->revealable()
                        ->dehydrated(fn (?string $state): bool => filled($state))
                        ->helperText('Optional at first. Without a key, players can still book this trainer but remain in Awaiting Trainer Setup until a valid Athlete Read + Athlete Write token is connected. Leave blank when editing to keep the current encrypted key.'),
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
