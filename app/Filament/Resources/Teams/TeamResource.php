<?php

namespace App\Filament\Resources\Teams;

use App\Filament\Resources\Teams\Pages\CreateTeam;
use App\Filament\Resources\Teams\Pages\EditTeam;
use App\Filament\Resources\Teams\Pages\ListTeams;
use App\Filament\Resources\Teams\Pages\ViewTeam;
use App\Models\Club;
use App\Models\Team;
use BackedEnum;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
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
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class TeamResource extends Resource
{
    protected static ?string $model = Team::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;
    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::Users;
    protected static string|UnitEnum|null $navigationGroup = 'Organizations';
    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make('team_tabs')
                ->persistTab()
                ->id('team-resource-tabs')
                ->contained(true)
                ->tabs([
                    Tab::make('Team Info')
                        ->icon('heroicon-m-users')
                        ->schema([
                            Section::make('Team')
                                ->columns(2)
                                ->schema([
                                    TextInput::make('name')
                                        ->label('Team Name')
                                        ->required()
                                        ->maxLength(255),

                                    Select::make('club_id')
                                        ->label('Club')
                                        ->options(fn (): array => Club::query()
                                            ->with('league')
                                            ->orderBy('name')
                                            ->get()
                                            ->mapWithKeys(function (Club $club) {
                                                $label = $club->name;

                                                if ($club->league?->name) {
                                                    $label .= ' — ' . $club->league->name;
                                                }

                                                return [$club->id => $label];
                                            })
                                            ->all())
                                        ->searchable()
                                        ->preload()
                                        ->required(),

                                    FileUpload::make('logo')
                                        ->label('Logo')
                                        ->image()
                                        ->downloadable()
                                        ->imageEditor()
                                        ->disk('public')
                                        ->directory('team-logos')
                                        ->visibility('public')
                                        ->columnSpanFull(),
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
                                        ->placeholder('example-team')
                                        ->helperText('Used for the public team landing page URL.')
                                        ->unique(ignoreRecord: true)
                                        ->maxLength(255),
                                ]),

                            Section::make('Landing Page Content')
                                ->schema([
                                    Textarea::make('landing_page_intro')
                                        ->label('Intro')
                                        ->placeholder('Short intro shown near the top of the team page.')
                                        ->rows(3),

                                    Textarea::make('landing_page_content')
                                        ->label('Main Content')
                                        ->placeholder('Team information, season notes, player composition, or team story.')
                                        ->rows(8),
                                ]),
                        ]),

                    Tab::make('Coaching Staff')
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
                        ]),

                    Tab::make('Team Settings')
                        ->icon('heroicon-m-cog-6-tooth')
                        ->schema([
                            Section::make('Display / Admin Settings')
                                ->description('Flexible settings for games, clickable player cards, staff access, schedule creation, and player management.')
                                ->schema([
                                    KeyValue::make('team_settings')
                                        ->label('Team Settings')
                                        ->keyLabel('Setting')
                                        ->valueLabel('Value')
                                        ->addActionLabel('Add setting')
                                        ->reorderable()
                                        ->helperText('Example keys: show_games, show_roster, show_player_stats, schedule_creation_enabled, staff_management_enabled.'),
                                ]),

                            Section::make('Branding')
                                ->description('Optional team branding overrides. If blank, the team page can inherit from the club.')
                                ->schema([
                                    KeyValue::make('branding')
                                        ->label('Branding')
                                        ->keyLabel('Setting')
                                        ->valueLabel('Value')
                                        ->addActionLabel('Add branding setting')
                                        ->reorderable()
                                        ->helperText('Example keys: heading_font, body_font, primary_color, logo_url, emblem_url.'),
                                ]),
                        ]),
                ])
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['club.league']))
            ->columns([
                ImageColumn::make('logo')
                    ->label('Logo')
                    ->disk('public')
                    ->height(36)
                    ->circular(),

                TextColumn::make('name')
                    ->label('Team')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('club.name')
                    ->label('Club')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('club.league.name')
                    ->label('League')
                    ->searchable()
                    ->toggleable(),

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
                SelectFilter::make('club_id')
                    ->label('Club')
                    ->relationship('club', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('league')
                    ->label('League')
                    ->relationship('club.league', 'name')
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
                ]),
            ])
            ->recordUrl(fn (Team $record): string => static::getUrl('edit', ['record' => $record]));
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTeams::route('/'),
            'create' => CreateTeam::route('/create'),
            'view' => ViewTeam::route('/{record}'),
            'edit' => EditTeam::route('/{record}/edit'),
        ];
    }
}