<?php

namespace App\Filament\Resources\Leagues;

use App\Filament\Clusters\Organizations;
use App\Filament\Resources\Leagues\Pages\CreateLeague;
use App\Filament\Resources\Leagues\Pages\EditLeague;
use App\Filament\Resources\Leagues\Pages\ListLeagues;
use App\Filament\Resources\Leagues\Pages\ViewLeague;
use App\Models\League;
use BackedEnum;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Schema as DatabaseSchema;
use Illuminate\Support\Str;
use UnitEnum;

class LeagueResource extends Resource
{
    protected static ?string $model = League::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTrophy;
    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::Trophy;
    protected static ?string $cluster = Organizations::class;
    protected static ?int $navigationSort = 2;
    protected static ?string $recordTitleAttribute = 'name';

    public static function genderOptions(): array
    {
        return [
            'male' => 'Male',
            'female' => 'Female',
            'coed' => 'Coed',
        ];
    }

    public static function sportOptions(): array
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

    protected static function normalizeGender(?string $gender): ?string
    {
        $gender = strtolower(trim((string) $gender));

        return match ($gender) {
            'female', 'girls', 'girl', 'women', 'woman', 'womens' => 'female',
            'male', 'boys', 'boy', 'men', 'man', 'mens' => 'male',
            'coed', 'both' => 'coed',
            default => $gender ?: null,
        };
    }

    protected static function applyCanonicalLeagueFilter(Builder $query): Builder
    {
        if (DatabaseSchema::hasColumn('leagues', 'canonical_league_id')) {
            $query->whereNull('canonical_league_id');
        }

        return $query;
    }

    protected static function applyActiveProgramFilter(Builder $query): Builder
    {
        if (DatabaseSchema::hasColumn('club_leagues', 'canonical_club_league_id')) {
            $query->whereNull('canonical_club_league_id');
        }

        if (DatabaseSchema::hasColumn('club_leagues', 'is_active')) {
            $query->where('is_active', true);
        }

        return $query;
    }

    protected static function formatGenders(?array $genders, ?string $legacyGender = null): string
    {
        $values = collect($genders ?? [])
            ->merge([$legacyGender])
            ->map(fn ($gender) => static::normalizeGender($gender))
            ->filter()
            ->unique()
            ->map(fn ($gender) => Str::of((string) $gender)->title()->toString())
            ->values();

        return $values->isNotEmpty() ? $values->implode(', ') : '-';
    }

    protected static function isSuperadminNavigationUser(): bool
    {
        $user = auth()->user();

        return $user
            && method_exists($user, 'hasRole')
            && (
                $user->hasRole('Superadmin')
                || $user->hasRole('superadmin')
                || $user->hasRole('Super Admin')
            );
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::isSuperadminNavigationUser();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('League')
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->label('League Name')
                        ->required()
                        ->maxLength(255),

                    Select::make('genders')
                        ->label('Supported Genders')
                        ->options(static::genderOptions())
                        ->multiple()
                        ->searchable()
                        ->preload()
                        ->required()
                        ->helperText('This replaces the old single legacy gender field.'),

                    Select::make('sport')
                        ->label('Sport')
                        ->options(static::sportOptions())
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
            ->modifyQueryUsing(fn (Builder $query) => static::applyCanonicalLeagueFilter($query)
                ->withCount([
                    'clubLeagues as active_club_programs_count' => fn (Builder $programQuery) => static::applyActiveProgramFilter($programQuery),
                ]))
            ->columns([
                ImageColumn::make('logo')
                    ->label('Logo')
                    ->disk('public')
                    ->square()
                    ->height(42)
                    ->toggleable(),

                TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('genders_display')
                    ->label('Genders')
                    ->state(fn (League $record): string => static::formatGenders($record->genders, $record->gender))
                    ->badge()
                    ->separator(',')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        $gender = static::normalizeGender($search);

                        if (! $gender) {
                            return $query;
                        }

                        return $query->whereJsonContains('genders', $gender);
                    }),

                TextColumn::make('sport')
                    ->label('Sport')
                    ->badge()
                    ->formatStateUsing(
                        fn (?string $state): string => filled($state)
                            ? Str::of($state)->replace('_', ' ')->title()->toString()
                            : '-'
                    )
                    ->searchable()
                    ->sortable(),

                TextColumn::make('active_club_programs_count')
                    ->label('Club Programs')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->since()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),

                SelectFilter::make('gender')
                    ->label('Gender')
                    ->options(static::genderOptions())
                    ->multiple()
                    ->query(function (Builder $query, array $data): Builder {
                        $values = collect($data['values'] ?? [])
                            ->map(fn ($gender) => static::normalizeGender($gender))
                            ->filter()
                            ->values();

                        if ($values->isEmpty()) {
                            return $query;
                        }

                        return $query->where(function (Builder $nested) use ($values) {
                            foreach ($values as $gender) {
                                $nested->orWhereJsonContains('genders', $gender);
                            }
                        });
                    }),

                SelectFilter::make('sport')
                    ->label('Sport')
                    ->options(static::sportOptions())
                    ->multiple()
                    ->searchable(),

                TernaryFilter::make('has_club_programs')
                    ->label('Has Active Club Programs')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereHas('clubLeagues', fn (Builder $programQuery) => static::applyActiveProgramFilter($programQuery)),
                        false: fn (Builder $query): Builder => $query->whereDoesntHave('clubLeagues', fn (Builder $programQuery) => static::applyActiveProgramFilter($programQuery)),
                        blank: fn (Builder $query): Builder => $query,
                    ),
            ])
            ->actions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                    DeleteAction::make(),
                    RestoreAction::make(),
                    ForceDeleteAction::make(),
                ]),
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