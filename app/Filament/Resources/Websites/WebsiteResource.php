<?php

namespace App\Filament\Resources\Websites;

use App\Filament\Resources\Websites\Pages\CreateWebsite;
use App\Filament\Resources\Websites\Pages\EditWebsite;
use App\Filament\Resources\Websites\Pages\ListWebsites;
use App\Filament\Resources\Websites\Pages\ViewWebsite;
use App\Filament\Resources\Websites\Schemas\WebsiteForm;
use App\Filament\Resources\Websites\Schemas\WebsiteInfolist;
use App\Filament\Resources\Websites\Tables\WebsitesTable;
use App\Models\Website;
use BackedEnum;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Infolists\Components\ImageEntry;

use UnitEnum;


class WebsiteResource extends Resource
{
    protected static ?string $model = Website::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAlt;
    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::GlobeAlt;
    protected static string|UnitEnum|null $navigationGroup = 'Websites';

    public static function form(Schema $schema): Schema
    {
        return WebsiteForm::configure($schema)->schema([
            Tabs::make('Website Settings')
                ->columnSpanFull()
                ->tabs([
                    Tabs\Tab::make('Content')
                        ->schema([
                            Section::make('About')
                                ->columns(2)
                                ->schema([
                                    TextInput::make('aboutme_headline')
                                        ->label('About Me Headline')
                                        ->maxLength(255)
                                        ->nullable(),

                                    TextInput::make('player_tagline')
                                        ->label('Player Tagline')
                                        ->maxLength(255)
                                        ->nullable(),

                                    RichEditor::make('player_bio')
                                        ->label('Player Bio')
                                        ->columnSpanFull()
                                        ->nullable(),
                                ]),

                            Section::make('Highlights')
                                ->columns(2)
                                ->schema([
                                    TextInput::make('highlights_headline')
                                        ->label('Highlights Headline')
                                        ->maxLength(255)
                                        ->nullable(),

                                    TextInput::make('highlights_tagline')
                                        ->label('Highlights Tagline')
                                        ->maxLength(255)
                                        ->nullable(),
                                ]),

                            Section::make('Schedules')
                                ->columns(2)
                                ->schema([
                                    TextInput::make('schedules_headline')
                                        ->label('Schedules Headline')
                                        ->maxLength(255)
                                        ->nullable(),

                                    TextInput::make('schedules_tagline')
                                        ->label('Schedules Tagline')
                                        ->maxLength(255)
                                        ->nullable(),
                                ]),

                            Section::make('Academic Accolades')
                                ->columns(2)
                                ->schema([
                                    TextInput::make('acad_accolades_headline')
                                        ->label('Academic Accolades Headline')
                                        ->maxLength(255)
                                        ->nullable(),

                                    TextInput::make('acad_accolades_tagline')
                                        ->label('Academic Accolades Tagline')
                                        ->maxLength(255)
                                        ->nullable(),

                                    RichEditor::make('academic_accolades')
                                        ->label('Academic Accolades Content')
                                        ->columnSpanFull()
                                        ->nullable(),
                                ]),

                            Section::make('Sports Accolades')
                                ->columns(2)
                                ->schema([
                                    TextInput::make('sport_accolades_headline')
                                        ->label('Sports Accolades Headline')
                                        ->maxLength(255)
                                        ->nullable(),

                                    TextInput::make('sport_accolades_tagline')
                                        ->label('Sports Accolades Tagline')
                                        ->maxLength(255)
                                        ->nullable(),

                                    RichEditor::make('sports_accolades')
                                        ->label('Sports Accolades Content')
                                        ->columnSpanFull()
                                        ->nullable(),
                                ]),
                        ]),

                    Tabs\Tab::make('Theme')
                        ->schema([
                            Section::make('Colors')
                                ->description('Use HEX values or pick colors. Recommended: #RRGGBB')
                                ->columns(2)
                                ->schema([
                                    ColorPicker::make('primary_color')->label('Primary')->nullable(),
                                    ColorPicker::make('secondary_color')->label('Secondary')->nullable(),
                                    ColorPicker::make('background_color')->label('Background')->nullable(),
                                ]),
                        ]),

                    Tabs\Tab::make('Embeds')
                        ->schema([
                            Section::make('Embeds')
                                ->columns(2)
                                ->schema([
                                    Textarea::make('contact_form_embed')
                                        ->label('Contact Form Embed / URL')
                                        ->rows(6)
                                        ->columnSpanFull()
                                        ->nullable(),

                                    Textarea::make('yt_embed')
                                        ->label('About Me | YouTube Video')
                                        ->rows(4)
                                        ->nullable(),

                                    Textarea::make('yt_playlist_embed')
                                        ->label('Highlights | YouTube Video URLs')
                                        ->placeholder("Paste one YouTube URL per line\nhttps://www.youtube.com/watch?v=gOW0GpXKvOg\nhttps://youtu.be/7H9oZSbxWrk")
                                        ->rows(5)
                                        ->helperText('Paste one YouTube video link per line.')
                                        ->nullable(),

                                    FileUpload::make('highlights_thumbnail')
                                        ->label('Thumbnail for Video Highlights')
                                        ->image()
                                        ->columnSpan(2)
                                        ->disk('public')                 // IMPORTANT
                                        ->directory('video-highlights-thumbnail')
                                        ->visibility('public')
                                        ->multiple()                     // IMPORTANT (logos is stored as array)
                                        ->maxFiles(1)                    // only one image
                                        ->reorderable(false)             // optional, not needed for 1
                                        ->imagePreviewHeight('40')
                                        ->panelLayout('grid')
                                        ->nullable(),
                                ]),

                            Section::make('Footer')
                                ->schema([
                                    FileUpload::make('logos')
                                        ->label('Footer Logo')
                                        ->image()
                                        ->disk('public')                 // IMPORTANT
                                        ->directory('website-logos')
                                        ->visibility('public')
                                        ->multiple()                     // IMPORTANT (logos is stored as array)
                                        ->maxFiles(1)                    // only one image
                                        ->reorderable(false)             // optional, not needed for 1
                                        ->imagePreviewHeight('40')
                                        ->panelLayout('grid')
                                        ->nullable(),
                                ])
                        ]),

                    Tabs\Tab::make('Builder')
                        ->schema([
                            Section::make('GrapesJS')
                                ->schema([
                                    Placeholder::make('grapesjs_editor')
                                        ->label('Website Editor')
                                        ->columnSpanFull()
                                        ->content(fn ($record) => view('website-editor-embed', ['record' => $record]))
                                        ->visible(fn ($record) => request()->is('admin/websites/*/edit')),
                                ]),
                        ]),
                    
                ]),
            Section::make('Owner')
                ->columnSpan(2)
                ->schema([
                    Select::make('user_id')
                        ->label('User')
                        ->relationship('user', 'first_name')
                        ->getOptionLabelFromRecordUsing(fn ($record) => $record->first_name . ' ' . $record->last_name)
                        ->searchable()
                        ->required()
                        ->preload(),
                ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return WebsiteInfolist::configure($schema);
    }

        public static function table(Table $table): Table
    {
        return WebsitesTable::configure($table)->columns([
            TextColumn::make('user_full_name')
                ->label('User')
                ->getStateUsing(fn ($record) => $record->user ? $record->user->first_name . ' ' . $record->user->last_name : ''),
            TextColumn::make('updated_at')
                ->since()
                ->label('Updated'),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWebsites::route('/'),
            'create' => CreateWebsite::route('/create'),
            'view' => ViewWebsite::route('/{record}'),
            'edit' => EditWebsite::route('/{record}/edit'),
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