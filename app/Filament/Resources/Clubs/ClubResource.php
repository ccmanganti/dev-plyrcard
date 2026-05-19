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
        return $schema
            ->columns(1)
            ->components([
                Section::make('Club Information')
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

                        FileUpload::make('hero_image')
                            ->label('Hero Image')
                            ->image()
                            ->downloadable()
                            ->imageEditor()
                            ->disk('public')
                            ->directory('club-landing')
                            ->visibility('public'),

                        FileUpload::make('background_image')
                            ->label('Background Image')
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

                Section::make('Landing Page')
                    ->columns(3)
                    ->schema([
                        Toggle::make('has_landing_page')
                            ->label('Enable')
                            ->default(false),

                        Toggle::make('landing_page_is_published')
                            ->label('Published')
                            ->default(false),

                        TextInput::make('landing_page_slug')
                            ->label('URI Slug')
                            ->placeholder('club-name')
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),

                        Textarea::make('landing_page_intro')
                            ->label('Intro / Tagline')
                            ->rows(2)
                            ->columnSpanFull(),

                        Textarea::make('landing_page_content')
                            ->label('Description')
                            ->rows(5)
                            ->columnSpanFull(),
                    ]),

                Section::make('Footer Contact Information')
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
                    ]),

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
                                    ->label('Name')
                                    ->maxLength(255),

                                TextInput::make('url')
                                    ->label('URL')
                                    ->url()
                                    ->maxLength(255),
                            ])
                            ->columns(2),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['league', 'conference'])->withCount('teams'))
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

                TextColumn::make('league.name')
                    ->label('League')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('teams_count')
                    ->label('Teams')
                    ->sortable(),

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

                SelectFilter::make('league_id')
                    ->label('League')
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