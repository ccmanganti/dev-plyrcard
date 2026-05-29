<?php

namespace App\Filament\Resources\Leagues;

use App\Filament\Resources\Leagues\Pages\CreateLeague;
use App\Filament\Resources\Leagues\Pages\EditLeague;
use App\Filament\Resources\Leagues\Pages\ListLeagues;
use App\Filament\Resources\Leagues\Pages\ViewLeague;
use App\Models\League;
use BackedEnum;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class LeagueResource extends Resource
{
    protected static ?string $model = League::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTrophy;
    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::Trophy;
    protected static string|UnitEnum|null $navigationGroup = 'Organizations';
    protected static ?string $recordTitleAttribute = 'name';

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
            'male' => 'Male / Boys',
            'female' => 'Female / Girls',
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('League')
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->required()
                        ->maxLength(255),

                    Select::make('genders')
                        ->label('Supported Genders')
                        ->helperText('Choose one or both. This is the new source of truth for league gender support.')
                        ->options(static::getGenderOptions())
                        ->multiple()
                        ->searchable()
                        ->preload()
                        ->required()
                        ->live()
                        ->afterStateHydrated(function (Select $component, mixed $state, ?League $record): void {
                            if (is_array($state) && $state !== []) {
                                return;
                            }

                            $legacyGender = strtolower((string) ($record?->gender ?? ''));
                            $resolved = match (true) {
                                str_contains($legacyGender, 'female'), str_contains($legacyGender, 'girl'), str_contains($legacyGender, 'women') => ['female'],
                                str_contains($legacyGender, 'male'), str_contains($legacyGender, 'boy'), str_contains($legacyGender, 'men') => ['male'],
                                default => [],
                            };

                            if ($resolved !== []) {
                                $component->state($resolved);
                            }
                        })
                        ->afterStateUpdated(function (?array $state, Set $set): void {
                            $set('gender', collect($state ?? [])->first());
                        }),

                    Select::make('gender')
                        ->label('Legacy Gender')
                        ->helperText('Kept for older code while the app moves to Supported Genders.')
                        ->options([
                            'male' => 'Male',
                            'female' => 'Female',
                            'Male' => 'Male (legacy)',
                            'Female' => 'Female (legacy)',
                            'Boys' => 'Boys (legacy)',
                            'Girls' => 'Girls (legacy)',
                        ])
                        ->nullable(),

                    Select::make('sport')
                        ->label('Sport')
                        ->options(static::getSportOptions())
                        ->searchable()
                        ->required(),

                    FileUpload::make('logo')
                        ->label('Logo')
                        ->image()
                        ->imageEditor()
                        ->disk('public')
                        ->directory('league-logos')
                        ->visibility('public')
                        ->helperText('Upload the league logo.')
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('logo')
                    ->label('Logo')
                    ->disk('public')
                    ->square()
                    ->toggleable(),

                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('genders')
                    ->label('Genders')
                    ->state(fn (League $record): string => collect($record->genders ?: [$record->gender])
                        ->filter()
                        ->map(fn ($gender) => str((string) $gender)->title())
                        ->implode(', ') ?: '-'),

                TextColumn::make('gender')
                    ->label('Legacy Gender')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('sport')
                    ->badge()
                    ->formatStateUsing(
                        fn (?string $state): string => filled($state)
                            ? str($state)->replace('_', ' ')->title()
                            : '-'
                    )
                    ->searchable()
                    ->sortable(),

                TextColumn::make('clubLeagues_count')
                    ->counts('clubLeagues')
                    ->label('Club Programs')
                    ->sortable(),

                TextColumn::make('updated_at')
                    ->since()
                    ->label('Updated'),
            ])
            ->filters([
                SelectFilter::make('sport')
                    ->label('Sport')
                    ->options(static::getSportOptions())
                    ->multiple(),

                SelectFilter::make('gender_support')
                    ->label('Gender Support')
                    ->options(static::getGenderOptions())
                    ->query(function (Builder $query, array $data): Builder {
                        $values = $data['values'] ?? [];

                        foreach ($values as $gender) {
                            $query->where(function (Builder $query) use ($gender): Builder {
                                return $query
                                    ->whereJsonContains('genders', $gender)
                                    ->orWhere('gender', $gender)
                                    ->orWhere('gender', ucfirst($gender));
                            });
                        }

                        return $query;
                    })
                    ->multiple(),

                TrashedFilter::make(),
            ])
            ->recordUrl(fn (League $record): string => static::getUrl('edit', ['record' => $record]));
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLeagues::route('/'),
            'create' => CreateLeague::route('/create'),
            'view' => ViewLeague::route('/{record}'),
            'edit' => EditLeague::route('/{record}/edit'),
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