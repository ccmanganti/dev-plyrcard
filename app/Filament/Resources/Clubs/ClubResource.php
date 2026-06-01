<?php

namespace App\Filament\Resources\Clubs;

use App\Filament\Resources\Clubs\Pages\CreateClub;
use App\Filament\Resources\Clubs\Pages\EditClub;
use App\Filament\Resources\Clubs\Pages\ListClubs;
use App\Filament\Resources\Clubs\Pages\ViewClub;
use App\Models\Club;
use App\Models\Conference;
use App\Models\League;
use BackedEnum;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
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
use Illuminate\Support\Str;
use UnitEnum;

class ClubResource extends Resource
{
    protected static ?string $model = Club::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;
    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::ShieldCheck;
    protected static string|UnitEnum|null $navigationGroup = 'Organizations';
    protected static ?string $recordTitleAttribute = 'name';

    public static function genderOptions(): array
    {
        return [
            'male' => 'Male',
            'female' => 'Female',
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
            default => null,
        };
    }

    protected static function labelGender(?string $gender): string
    {
        $gender = static::normalizeGender($gender);

        return match ($gender) {
            'male' => 'Male',
            'female' => 'Female',
            default => '-',
        };
    }

    protected static function genderShortLabel(?string $gender): string
    {
        $gender = static::normalizeGender($gender);

        return match ($gender) {
            'male' => 'Boys',
            'female' => 'Girls',
            default => '',
        };
    }

    protected static function programGenders($program): array
    {
        $programGenders = collect($program->genders ?? [])
            ->map(fn ($gender) => static::normalizeGender($gender))
            ->filter()
            ->unique()
            ->values();

        if ($programGenders->isNotEmpty()) {
            return $programGenders->all();
        }

        return collect($program->league?->genders ?? [])
            ->map(fn ($gender) => static::normalizeGender($gender))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    protected static function programBadgeIcon(?string $state): string|BackedEnum|null
    {
        $state = strtolower((string) $state);

        return match (true) {
            str_contains($state, 'girls') => Heroicon::OutlinedUserCircle,
            str_contains($state, 'boys') => Heroicon::OutlinedUser,
            default => Heroicon::OutlinedTrophy,
        };
    }

    protected static function programBadgeColor(?string $state): string
    {
        $state = strtolower((string) $state);

        return match (true) {
            str_contains($state, 'girls') => 'danger',
            str_contains($state, 'boys') => 'info',
            default => 'gray',
        };
    }

    protected static function labelSport(?string $sport): string
    {
        return filled($sport)
            ? Str::of($sport)->replace('_', ' ')->title()->toString()
            : '-';
    }

    protected static function applyCanonicalClubFilter($query)
    {
        if (DatabaseSchema::hasColumn('clubs', 'canonical_club_id')) {
            $query->whereNull('canonical_club_id');
        }

        return $query;
    }

    protected static function applyCanonicalLeagueFilter($query)
    {
        if (DatabaseSchema::hasColumn('leagues', 'canonical_league_id')) {
            $query->whereNull('canonical_league_id');
        }

        return $query;
    }

    protected static function applyActiveProgramFilter($query)
    {
        if (DatabaseSchema::hasColumn('club_leagues', 'canonical_club_league_id')) {
            $query->whereNull('canonical_club_league_id');
        }

        if (DatabaseSchema::hasColumn('club_leagues', 'is_active')) {
            $query->where('is_active', true);
        }

        return $query;
    }

    protected static function canonicalLeagueOptions(): array
    {
        return static::applyCanonicalLeagueFilter(League::query())
            ->orderBy('name')
            ->get()
            ->mapWithKeys(function (League $league): array {
                $genders = collect($league->genders ?? [])
                    ->map(fn ($gender) => static::genderShortLabel($gender))
                    ->filter()
                    ->unique()
                    ->implode('/');

                $label = $league->name;

                if ($genders !== '') {
                    $label .= " ({$genders})";
                }

                if (filled($league->sport)) {
                    $label .= ' - ' . static::labelSport($league->sport);
                }

                return [(string) $league->id => $label];
            })
            ->all();
    }

    protected static function getActiveProgramRows(Club $record)
    {
        return $record->clubLeagues()
            ->with('league')
            ->tap(fn ($query) => static::applyActiveProgramFilter($query))
            ->whereHas('league', fn ($query) => static::applyCanonicalLeagueFilter($query))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->filter(fn ($program) => filled($program->league?->name))
            ->unique(fn ($program) => implode('|', [
                $program->league_id,
                collect(static::programGenders($program))
                    ->sort()
                    ->implode(','),
                $program->sport ?: $program->league?->sport,
            ]))
            ->values();
    }

    protected static function programBadgeStates(Club $record): array
    {
        return static::getActiveProgramRows($record)
            ->flatMap(function ($program) {
                $leagueName = $program->league?->name;

                if (blank($leagueName)) {
                    return [];
                }

                $sport = static::labelSport($program->sport ?: $program->league?->sport);

                $genders = collect(static::programGenders($program))
                    ->map(fn ($gender) => static::genderShortLabel($gender))
                    ->filter()
                    ->unique()
                    ->values();

                if ($genders->isEmpty()) {
                    return [
                        collect([
                            $leagueName,
                            $sport !== '-' ? $sport : null,
                        ])
                            ->filter()
                            ->implode(' • '),
                    ];
                }

                return $genders
                    ->map(fn (string $genderLabel): string => collect([
                        $leagueName,
                        $genderLabel,
                        $sport !== '-' ? $sport : null,
                    ])
                        ->filter()
                        ->implode(' • '))
                    ->all();
            })
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    protected static function programSummaryText(Club $record): string
    {
        $programs = static::programBadgeStates($record);

        return filled($programs) ? implode(', ', $programs) : '-';
    }

    protected static function sportsSummary(Club $record): string
    {
        $sports = static::getActiveProgramRows($record)
            ->map(fn ($program) => $program->sport ?: $program->league?->sport)
            ->filter()
            ->map(fn ($sport) => static::labelSport($sport))
            ->unique()
            ->values();

        return $sports->isNotEmpty() ? $sports->implode(', ') : '-';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Tabs::make('Club Setup')
                    ->persistTabInQueryString()
                    ->tabs([
                        Tab::make('Basic Information')
                            ->icon(Heroicon::OutlinedInformationCircle)
                            ->schema([
                                Section::make('Club Details')
                                    ->columns(2)
                                    ->schema([
                                        TextInput::make('name')
                                            ->label('Club Name')
                                            ->required()
                                            ->maxLength(255),

                                        Select::make('conference_id')
                                            ->label('Conference')
                                            ->options(fn (): array => Conference::query()
                                                ->orderBy('name')
                                                ->pluck('name', 'id')
                                                ->all())
                                            ->searchable()
                                            ->preload()
                                            ->nullable(),

                                        TextInput::make('city')
                                            ->label('City')
                                            ->maxLength(255),

                                        TextInput::make('state')
                                            ->label('State')
                                            ->maxLength(255),

                                        FileUpload::make('logo')
                                            ->label('Club Logo')
                                            ->image()
                                            ->downloadable()
                                            ->imageEditor()
                                            ->disk('public')
                                            ->directory('club-logos')
                                            ->visibility('public'),

                                        FileUpload::make('background_image')
                                            ->label('Featured Background Image')
                                            ->helperText('Optional. If empty, the club landing page uses images/PLYRCARD-SITE.jpg.')
                                            ->image()
                                            ->downloadable()
                                            ->imageEditor()
                                            ->disk('public')
                                            ->directory('club-landing')
                                            ->visibility('public'),

                                        ColorPicker::make('primary_color')
                                            ->label('Primary Color'),

                                        ColorPicker::make('secondary_color')
                                            ->label('Secondary Color'),
                                    ]),
                            ]),

                        Tab::make('Program Leagues')
                            ->icon(Heroicon::OutlinedSquares2x2)
                            ->schema([
                                Section::make('Active Club Programs')
                                    ->description('Manage only the active canonical programs for this club. Legacy duplicate programs are intentionally hidden.')
                                    ->schema([
                                        Repeater::make('clubLeagues')
                                            ->label('Program Leagues')
                                            ->relationship(
                                                name: 'clubLeagues',
                                                modifyQueryUsing: fn ($query) => static::applyActiveProgramFilter($query)
                                                    ->whereHas('league', fn ($leagueQuery) => static::applyCanonicalLeagueFilter($leagueQuery))
                                                    ->orderBy('sort_order')
                                                    ->orderBy('id')
                                            )
                                            ->addActionLabel('Add Program League')
                                            ->reorderable()
                                            ->collapsed()
                                            ->itemLabel(function (array $state): ?string {
                                                $leagueName = filled($state['league_id'] ?? null)
                                                    ? League::query()->whereKey($state['league_id'])->value('name')
                                                    : 'Program League';

                                                $genders = collect($state['genders'] ?? [])
                                                    ->map(fn ($gender) => static::genderShortLabel($gender))
                                                    ->filter()
                                                    ->unique()
                                                    ->values();

                                                $sport = static::labelSport($state['sport'] ?? null);

                                                return collect([
                                                    $leagueName,
                                                    $genders->isNotEmpty() ? $genders->implode('/') : null,
                                                    $sport !== '-' ? $sport : null,
                                                ])
                                                    ->filter()
                                                    ->implode(' • ');
                                            })
                                            ->schema([
                                                Select::make('league_id')
                                                    ->label('League')
                                                    ->options(fn (): array => static::canonicalLeagueOptions())
                                                    ->searchable()
                                                    ->preload()
                                                    ->required(),

                                                Select::make('genders')
                                                    ->label('Genders Offered')
                                                    ->options(static::genderOptions())
                                                    ->multiple()
                                                    ->searchable()
                                                    ->preload()
                                                    ->required(),

                                                Select::make('sport')
                                                    ->label('Sport')
                                                    ->options(static::sportOptions())
                                                    ->searchable()
                                                    ->helperText('Optional. Leave empty to use the selected league sport.'),

                                                TextInput::make('sort_order')
                                                    ->label('Sort')
                                                    ->numeric()
                                                    ->default(0),

                                                Toggle::make('is_active')
                                                    ->label('Active')
                                                    ->default(true),
                                            ])
                                            ->columns(2),
                                    ]),
                            ]),

                        Tab::make('Website Publishing')
                            ->icon(Heroicon::OutlinedGlobeAlt)
                            ->schema([
                                Section::make('Publishing')
                                    ->columns(3)
                                    ->schema([
                                        Toggle::make('has_landing_page')
                                            ->label('Enable Club Page')
                                            ->default(false),

                                        Toggle::make('landing_page_is_published')
                                            ->label('Published')
                                            ->default(false),

                                        TextInput::make('landing_page_slug')
                                            ->label('URI Slug')
                                            ->placeholder('club-name')
                                            ->helperText('Used for /clubs/{slug}. Leave blank to auto-generate from the club name.')
                                            ->unique(ignoreRecord: true)
                                            ->maxLength(255),
                                    ]),
                            ]),

                        Tab::make('Contact / Address')
                            ->icon(Heroicon::OutlinedMapPin)
                            ->schema([
                                Section::make('Basic Address & Contact')
                                    ->columns(2)
                                    ->schema([
                                        TextInput::make('contact_info.address')
                                            ->label('Address')
                                            ->maxLength(255),

                                        TextInput::make('contact_info.maps_url')
                                            ->label('Google Maps URL')
                                            ->url()
                                            ->maxLength(255),

                                        TextInput::make('contact_info.phone')
                                            ->label('Phone')
                                            ->tel()
                                            ->maxLength(255),

                                        TextInput::make('contact_info.email')
                                            ->label('Email')
                                            ->email()
                                            ->maxLength(255),

                                        Textarea::make('landing_page_content')
                                            ->label('Club Description')
                                            ->helperText('Shown in the footer and basic club description areas.')
                                            ->rows(4)
                                            ->columnSpanFull(),
                                    ]),
                            ]),

                        Tab::make('Coaches')
                            ->icon(Heroicon::OutlinedUserGroup)
                            ->schema([
                                Section::make('Coach Names')
                                    ->schema([
                                        Repeater::make('coaching_staff')
                                            ->label('Coaches')
                                            ->addActionLabel('Add Coach')
                                            ->reorderable()
                                            ->collapsed()
                                            ->itemLabel(fn (array $state): ?string => $state['name'] ?? 'Coach')
                                            ->schema([
                                                TextInput::make('name')
                                                    ->label('Coach Name')
                                                    ->maxLength(255),

                                                TextInput::make('role')
                                                    ->label('Role')
                                                    ->placeholder('Head Coach')
                                                    ->maxLength(255),

                                                TextInput::make('email')
                                                    ->label('Email')
                                                    ->email()
                                                    ->maxLength(255),

                                                TextInput::make('phone')
                                                    ->label('Phone')
                                                    ->tel()
                                                    ->maxLength(255),
                                            ])
                                            ->columns(2),
                                    ]),
                            ]),

                        Tab::make('Sponsors')
                            ->icon(Heroicon::OutlinedStar)
                            ->schema([
                                Section::make('Sponsors / Partners')
                                    ->schema([
                                        Repeater::make('sponsors_partners')
                                            ->label('Sponsors / Partners')
                                            ->addActionLabel('Add Sponsor')
                                            ->reorderable()
                                            ->collapsed()
                                            ->itemLabel(fn (array $state): ?string => $state['name'] ?? 'Sponsor')
                                            ->schema([
                                                TextInput::make('name')
                                                    ->label('Sponsor Name')
                                                    ->maxLength(255),

                                                TextInput::make('url')
                                                    ->label('Sponsor URL')
                                                    ->url()
                                                    ->maxLength(255),
                                            ])
                                            ->columns(2),
                                    ]),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => static::applyCanonicalClubFilter($query)
                ->with([
                    'clubLeagues' => fn ($programQuery) => static::applyActiveProgramFilter($programQuery)
                        ->whereHas('league', fn ($leagueQuery) => static::applyCanonicalLeagueFilter($leagueQuery))
                        ->with('league')
                        ->orderBy('sort_order')
                        ->orderBy('id'),
                ]))
            ->columns([
                ImageColumn::make('logo')
                    ->label('Logo')
                    ->disk('public')
                    ->height(42)
                    ->circular(),

                TextColumn::make('name')
                    ->label('Club')
                    ->searchable()
                    ->sortable()
                    ->description(fn (Club $record): ?string => trim(collect([$record->city, $record->state])->filter()->implode(', ')) ?: null),

                TextColumn::make('sports_summary')
                    ->label('Sports')
                    ->badge()
                    ->state(fn (Club $record): string => static::sportsSummary($record))
                    ->toggleable(),

                IconColumn::make('has_landing_page')
                    ->label('Page')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('landing_page_is_published')
                    ->label('Live')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->since()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),

                SelectFilter::make('program_league')
                    ->label('Program League')
                    ->options(fn (): array => static::canonicalLeagueOptions())
                    ->searchable()
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;

                        if (blank($value)) {
                            return $query;
                        }

                        return $query->whereHas('clubLeagues', function (Builder $programQuery) use ($value) {
                            static::applyActiveProgramFilter($programQuery)
                                ->where('league_id', $value);
                        });
                    }),

                SelectFilter::make('program_gender')
                    ->label('Program Gender')
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

                        return $query->whereHas('clubLeagues', function (Builder $programQuery) use ($values) {
                            static::applyActiveProgramFilter($programQuery);

                            $programQuery->where(function (Builder $nested) use ($values) {
                                foreach ($values as $gender) {
                                    $nested->orWhereJsonContains('genders', $gender);
                                }
                            });
                        });
                    }),

                SelectFilter::make('program_sport')
                    ->label('Program Sport')
                    ->options(static::sportOptions())
                    ->multiple()
                    ->query(function (Builder $query, array $data): Builder {
                        $values = collect($data['values'] ?? [])->filter()->values();

                        if ($values->isEmpty()) {
                            return $query;
                        }

                        return $query->whereHas('clubLeagues', function (Builder $programQuery) use ($values) {
                            static::applyActiveProgramFilter($programQuery);

                            $programQuery->where(function (Builder $nested) use ($values) {
                                $nested->whereIn('sport', $values)
                                    ->orWhereHas('league', fn (Builder $leagueQuery) => $leagueQuery->whereIn('sport', $values));
                            });
                        });
                    }),

                TernaryFilter::make('has_programs')
                    ->label('Has Active Programs')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereHas('clubLeagues', fn ($programQuery) => static::applyActiveProgramFilter($programQuery)),
                        false: fn (Builder $query): Builder => $query->whereDoesntHave('clubLeagues', fn ($programQuery) => static::applyActiveProgramFilter($programQuery)),
                        blank: fn (Builder $query): Builder => $query,
                    ),

                TernaryFilter::make('landing_page_is_published')
                    ->label('Published'),
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
            ->recordUrl(fn (Club $record): string => static::getUrl('edit', ['record' => $record]));
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListClubs::route('/'),
            'create' => CreateClub::route('/create'),
            'view' => ViewClub::route('/{record}'),
            'edit' => EditClub::route('/{record}/edit'),
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