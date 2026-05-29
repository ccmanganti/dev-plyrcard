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
use Illuminate\Support\Str;
use UnitEnum;

class ClubResource extends Resource
{
    protected static ?string $model = Club::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;
    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::ShieldCheck;
    protected static string|UnitEnum|null $navigationGroup = 'Organizations';
    protected static ?string $recordTitleAttribute = 'name';

    public static function getGenderOptions(): array
    {
        return [
            'male' => 'Male / Boys',
            'female' => 'Female / Girls',
        ];
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

                                        Select::make('league_id')
                                            ->label('Legacy / Default League')
                                            ->helperText('Kept for older records only. Use Program Leagues below for the new structure.')
                                            ->options(fn (): array => League::query()
                                                ->orderBy('name')
                                                ->pluck('name', 'id')
                                                ->all())
                                            ->searchable()
                                            ->preload()
                                            ->nullable(),

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
                                Section::make('Club League Programs')
                                    ->description('Use this to define which leagues this club participates in and which genders are offered for each league.')
                                    ->schema([
                                        Repeater::make('clubLeagues')
                                            ->relationship()
                                            ->label('Program Leagues')
                                            ->addActionLabel('Add Program League')
                                            ->reorderable()
                                            ->orderColumn('sort_order')
                                            ->collapsed()
                                            ->itemLabel(function (array $state): string {
                                                $league = filled($state['league_id'] ?? null)
                                                    ? League::query()->whereKey($state['league_id'])->value('name')
                                                    : 'Program League';

                                                $genders = collect($state['genders'] ?? [])
                                                    ->map(fn ($gender) => Str::of((string) $gender)->title())
                                                    ->implode(', ');

                                                return trim($league . ($genders ? ' — ' . $genders : ''));
                                            })
                                            ->schema([
                                                Select::make('league_id')
                                                    ->label('League')
                                                    ->options(fn (): array => League::query()
                                                        ->orderBy('name')
                                                        ->pluck('name', 'id')
                                                        ->all())
                                                    ->searchable()
                                                    ->preload()
                                                    ->required(),

                                                Select::make('genders')
                                                    ->label('Genders Offered')
                                                    ->options(static::getGenderOptions())
                                                    ->multiple()
                                                    ->searchable()
                                                    ->preload()
                                                    ->required()
                                                    ->helperText('Choose one or both genders for this club in this league.'),

                                                TextInput::make('sport')
                                                    ->label('Sport Override')
                                                    ->helperText('Optional. Usually inherited from the league.')
                                                    ->maxLength(255),

                                                Toggle::make('is_active')
                                                    ->label('Active')
                                                    ->default(true),
                                            ])
                                            ->columns(2)
                                            ->columnSpanFull(),
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
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['league', 'conference', 'clubLeagues.league'])->withCount('teams'))
            ->columns([
                ImageColumn::make('logo')
                    ->label('')
                    ->disk('public')
                    ->height(34)
                    ->circular(),

                TextColumn::make('name')
                    ->label('Club')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('program_leagues')
                    ->label('Program Leagues')
                    ->state(function (Club $record): string {
                        return $record->clubLeagues
                            ->map(function ($program): string {
                                $genders = collect($program->genders ?? [])
                                    ->map(fn ($gender) => Str::of((string) $gender)->title())
                                    ->implode('/');

                                return trim(($program->league?->name ?? 'League') . ($genders ? ' (' . $genders . ')' : ''));
                            })
                            ->filter()
                            ->implode(', ') ?: '-';
                    })
                    ->wrap()
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->orWhereHas('clubLeagues.league', function (Builder $query) use ($search): void {
                            $query->where('name', 'like', '%' . $search . '%');
                        });
                    }),

                TextColumn::make('league.name')
                    ->label('Legacy League')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('teams_count')
                    ->label('Legacy Teams')
                    ->sortable()
                    ->toggleable(),

                IconColumn::make('has_landing_page')
                    ->label('Page')
                    ->boolean(),

                IconColumn::make('landing_page_is_published')
                    ->label('Live')
                    ->boolean(),

                TextColumn::make('landing_page_slug')
                    ->label('Slug')
                    ->searchable()
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->since()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                TrashedFilter::make(),

                SelectFilter::make('program_league')
                    ->label('Program League')
                    ->options(fn (): array => League::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->whereHas('clubLeagues', fn (Builder $query) => $query->where('league_id', $data['value']))
                        : $query)
                    ->searchable()
                    ->preload(),

                SelectFilter::make('legacy_league_id')
                    ->label('Legacy League')
                    ->relationship('league', 'name')
                    ->searchable()
                    ->preload(),

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