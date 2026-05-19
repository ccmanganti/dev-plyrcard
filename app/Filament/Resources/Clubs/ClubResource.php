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
use Filament\Forms\Components\KeyValue;
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
use UnitEnum;

class ClubResource extends Resource
{
    protected static ?string $model = Club::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;
    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::ShieldCheck;
    protected static string|UnitEnum|null $navigationGroup = 'Organizations';
    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make('club_tabs')
                ->persistTab()
                ->id('club-resource-tabs')
                ->contained(true)
                ->tabs([
                    Tab::make('Club Info')
                        ->icon('heroicon-m-shield-check')
                        ->schema([
                            Section::make('Club')
                                ->columnSpanFull()
                                ->columns(2)
                                ->schema([
                                    TextInput::make('name')
                                        ->label('Club Name')
                                        ->required()
                                        ->maxLength(255),

                                    Select::make('league_id')
                                        ->label('League')
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

                                    FileUpload::make('logo')
                                        ->label('Logo')
                                        ->image()
                                        ->downloadable()
                                        ->imageEditor()
                                        ->disk('public')
                                        ->directory('club-logos')
                                        ->visibility('public'),

                                    TextInput::make('city')
                                        ->label('City')
                                        ->maxLength(255),

                                    TextInput::make('state')
                                        ->label('State')
                                        ->maxLength(255),
                                ]),

                            Section::make('Club Colors')
                                ->columns(2)
                                ->schema([
                                    ColorPicker::make('primary_color')
                                        ->label('Primary Color'),

                                    ColorPicker::make('secondary_color')
                                        ->label('Secondary Color'),
                                ]),
                        ]),

                    Tab::make('Landing Page')
                        ->icon('heroicon-m-globe-alt')
                        ->schema([
                            Section::make('Landing Page Controls')
                                ->columns(3)
                                ->schema([
                                    Toggle::make('has_landing_page')
                                        ->label('Enable Landing Page')
                                        ->default(false)
                                        ->live(),

                                    Toggle::make('landing_page_is_published')
                                        ->label('Published')
                                        ->default(false),

                                    TextInput::make('landing_page_slug')
                                        ->label('Landing Page Slug')
                                        ->placeholder('example-club')
                                        ->helperText('Used for the public club landing page URL.')
                                        ->unique(ignoreRecord: true)
                                        ->maxLength(255),
                                ]),

                            Section::make('Landing Page Content')
                                ->columns(1)
                                ->schema([
                                    Textarea::make('landing_page_intro')
                                        ->label('Intro')
                                        ->placeholder('Short intro shown near the top of the club page.')
                                        ->rows(3),

                                    Textarea::make('landing_page_content')
                                        ->label('Main Content')
                                        ->placeholder('Club story, mission, recruitment information, or general text content.')
                                        ->rows(8),
                                ]),

                            Section::make('Contact / Maps')
                                ->description('Flexible contact information for address, maps, phone, and email.')
                                ->columns(1)
                                ->schema([
                                    KeyValue::make('contact_info')
                                        ->label('Contact Info')
                                        ->keyLabel('Field')
                                        ->valueLabel('Value')
                                        ->addActionLabel('Add contact item')
                                        ->reorderable()
                                        ->helperText('Example keys: address, maps_url, phone, email, website.'),
                                ]),
                        ]),

                    Tab::make('People & Partners')
                        ->icon('heroicon-m-user-group')
                        ->schema([
                            Section::make('Coaching Staff')
                                ->schema([
                                    Repeater::make('coaching_staff')
                                        ->label('Coaches')
                                        ->addActionLabel('Add Coach')
                                        ->reorderable()
                                        ->collapsed()
                                        ->itemLabel(fn (array $state): ?string => $state['name'] ?? 'Coach')
                                        ->schema([
                                            TextInput::make('name')
                                                ->label('Name')
                                                ->maxLength(255),

                                            TextInput::make('role')
                                                ->label('Role / Title')
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

                                            Textarea::make('bio')
                                                ->label('Bio')
                                                ->rows(3)
                                                ->columnSpanFull(),
                                        ])
                                        ->columns(2),
                                ]),

                            Section::make('Sponsors / Partners')
                                ->schema([
                                    Repeater::make('sponsors_partners')
                                        ->label('Sponsors & Partners')
                                        ->addActionLabel('Add Sponsor / Partner')
                                        ->reorderable()
                                        ->collapsed()
                                        ->itemLabel(fn (array $state): ?string => $state['name'] ?? 'Sponsor / Partner')
                                        ->schema([
                                            TextInput::make('name')
                                                ->label('Name')
                                                ->maxLength(255),

                                            TextInput::make('url')
                                                ->label('URL')
                                                ->url()
                                                ->maxLength(255),

                                            FileUpload::make('logo')
                                                ->label('Logo')
                                                ->image()
                                                ->downloadable()
                                                ->imageEditor()
                                                ->disk('public')
                                                ->directory('club-sponsors')
                                                ->visibility('public'),

                                            Textarea::make('description')
                                                ->label('Description')
                                                ->rows(3)
                                                ->columnSpanFull(),
                                        ])
                                        ->columns(2),
                                ]),

                            Section::make('Social Links')
                                ->schema([
                                    KeyValue::make('social_links')
                                        ->label('Social Links')
                                        ->keyLabel('Platform')
                                        ->valueLabel('URL / Handle')
                                        ->addActionLabel('Add social link')
                                        ->reorderable()
                                        ->helperText('Example keys: instagram, facebook, x, youtube, tiktok.'),
                                ]),
                        ]),

                    Tab::make('Branding')
                        ->icon('heroicon-m-swatch')
                        ->schema([
                            Section::make('Branding Settings')
                                ->description('Flexible branding settings for fonts, colors, logos, jerseys, and future card/emblem options.')
                                ->schema([
                                    KeyValue::make('branding')
                                        ->label('Branding')
                                        ->keyLabel('Setting')
                                        ->valueLabel('Value')
                                        ->addActionLabel('Add branding setting')
                                        ->reorderable()
                                        ->helperText('Example keys: heading_font, body_font, logo_url, emblem_url, jersey_colors.'),
                                ]),
                        ]),
                ])
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['league', 'conference', 'teams']))
            ->columns([
                ImageColumn::make('logo')
                    ->label('Logo')
                    ->disk('public')
                    ->height(36)
                    ->circular(),

                TextColumn::make('name')
                    ->label('Club')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('league.name')
                    ->label('League')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('conference.name')
                    ->label('Conference')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('city')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('state')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('teams_count')
                    ->label('Teams')
                    ->counts('teams')
                    ->sortable(),

                IconColumn::make('has_landing_page')
                    ->label('Landing Page')
                    ->boolean()
                    ->toggleable(),

                IconColumn::make('landing_page_is_published')
                    ->label('Published')
                    ->boolean()
                    ->toggleable(),

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

                SelectFilter::make('league_id')
                    ->label('League')
                    ->relationship('league', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('conference_id')
                    ->label('Conference')
                    ->relationship('conference', 'name')
                    ->searchable()
                    ->preload(),

                TernaryFilter::make('has_landing_page')
                    ->label('Has Landing Page'),

                TernaryFilter::make('landing_page_is_published')
                    ->label('Landing Page Published'),
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