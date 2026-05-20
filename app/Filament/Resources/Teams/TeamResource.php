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
        return $schema
            ->columns(1)
            ->components([
                Tabs::make('Team Setup')
                    ->persistTabInQueryString()
                    ->tabs([
                        Tab::make('Basic Information')
                            ->icon(Heroicon::OutlinedInformationCircle)
                            ->schema([
                                Section::make('Team Details')
                                    ->columns(2)
                                    ->schema([
                                        TextInput::make('name')
                                            ->label('Team Name')
                                            ->required()
                                            ->maxLength(255),

                                        Select::make('club_id')
                                            ->label('Club')
                                            ->options(fn (): array => Club::query()
                                                ->orderBy('name')
                                                ->pluck('name', 'id')
                                                ->all())
                                            ->searchable()
                                            ->preload()
                                            ->required(),

                                        Select::make('team_settings.gender')
                                            ->label('Team Category')
                                            ->options([
                                                'mens' => "Men's",
                                                'womens' => "Women's",
                                            ])
                                            ->native(false)
                                            ->required(),

                                        TextInput::make('team_settings.sport')
                                            ->label('Sport')
                                            ->placeholder('Soccer')
                                            ->maxLength(255),

                                        FileUpload::make('logo')
                                            ->label('Team Logo')
                                            ->image()
                                            ->downloadable()
                                            ->imageEditor()
                                            ->disk('public')
                                            ->directory('team-logos')
                                            ->visibility('public'),

                                        FileUpload::make('background_image')
                                            ->label('Featured Background Image')
                                            ->helperText('Optional. If empty, the team page uses the club background image or images/PLYRCARD-SITE.jpg.')
                                            ->image()
                                            ->downloadable()
                                            ->imageEditor()
                                            ->disk('public')
                                            ->directory('team-landing')
                                            ->visibility('public'),

                                        ColorPicker::make('branding.primary_color')
                                            ->label('Primary Color Override')
                                            ->helperText('Optional. Leave blank to use the club primary color.'),

                                        ColorPicker::make('branding.secondary_color')
                                            ->label('Secondary Color Override')
                                            ->helperText('Optional. Leave blank to use the club secondary color.'),
                                    ]),
                            ]),

                        Tab::make('Website Publishing')
                            ->icon(Heroicon::OutlinedGlobeAlt)
                            ->schema([
                                Section::make('Publishing')
                                    ->columns(3)
                                    ->schema([
                                        Toggle::make('has_landing_page')
                                            ->label('Enable Team Page')
                                            ->default(false),

                                        Toggle::make('landing_page_is_published')
                                            ->label('Published')
                                            ->default(false),

                                        TextInput::make('landing_page_slug')
                                            ->label('URI Slug')
                                            ->placeholder('team-name')
                                            ->helperText('Used for /clubs/{club}/teams/{mens|womens}/{slug}. Leave blank to auto-generate from the team name.')
                                            ->maxLength(255),
                                    ]),
                            ]),

                        Tab::make('Coaches')
                            ->icon(Heroicon::OutlinedUserGroup)
                            ->schema([
                                Section::make('Coach Names & Contact')
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

                        Tab::make('Basic Page Info')
                            ->icon(Heroicon::OutlinedDocumentText)
                            ->schema([
                                Section::make('Optional Basic Description')
                                    ->schema([
                                        Textarea::make('landing_page_intro')
                                            ->label('Short Team Tagline')
                                            ->helperText('Optional short line shown under the team name.')
                                            ->rows(2),

                                        Textarea::make('landing_page_content')
                                            ->label('Team Description')
                                            ->helperText('Optional basic description only. No layout manipulation.')
                                            ->rows(4),
                                    ]),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('club'))
            ->columns([
                ImageColumn::make('logo')
                    ->label('')
                    ->disk('public')
                    ->height(34)
                    ->circular(),

                TextColumn::make('name')
                    ->label('Team')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('club.name')
                    ->label('Club')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('team_settings.gender')
                    ->label('Category')
                    ->formatStateUsing(fn (?string $state): string => $state === 'womens' ? "Women's" : "Men's")
                    ->badge(),

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
                SelectFilter::make('club_id')
                    ->label('Club')
                    ->relationship('club', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('team_settings->gender')
                    ->label('Category')
                    ->options([
                        'mens' => "Men's",
                        'womens' => "Women's",
                    ]),

                TernaryFilter::make('landing_page_is_published')
                    ->label('Published'),
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