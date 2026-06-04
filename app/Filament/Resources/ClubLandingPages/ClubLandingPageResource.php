<?php

namespace App\Filament\Resources\ClubLandingPages;

use App\Filament\Resources\ClubLandingPages\Pages\EditClubLandingPage;
use App\Filament\Resources\ClubLandingPages\Pages\ListClubLandingPages;
use App\Models\Club;
use App\Support\ClubManagerAccess;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class ClubLandingPageResource extends Resource
{
    protected static ?string $model = Club::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedGlobeAlt;

    protected static string | UnitEnum | null $navigationGroup = 'Club Admin';

    protected static ?string $navigationLabel = 'Landing Page';

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Landing Page')
                ->columns(2)
                ->schema([
                    Toggle::make('has_landing_page')->label('Enable Club Page')->default(true),
                    Toggle::make('landing_page_is_published')->label('Published'),
                    TextInput::make('landing_page_slug')->label('URL Slug')->unique(ignoreRecord: true),
                    TextInput::make('landing_page_intro')->label('Intro Headline')->maxLength(255)->columnSpanFull(),
                    Textarea::make('landing_page_content')->label('Club Description')->rows(7)->columnSpanFull(),
                    FileUpload::make('logo')->label('Club Logo')->image()->disk('public')->directory('club-logos')->visibility('public'),
                    FileUpload::make('background_image')->label('Background Image')->image()->disk('public')->directory('club-landing')->visibility('public'),
                    ColorPicker::make('primary_color')->label('Primary Color'),
                    ColorPicker::make('secondary_color')->label('Secondary Color'),
                    Repeater::make('coaching_staff')
                        ->label('Coaches')
                        ->schema([
                            TextInput::make('name')->label('Name'),
                            TextInput::make('role')->label('Role'),
                            TextInput::make('email')->label('Email')->email(),
                        ])
                        ->columns(3)
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => ClubManagerAccess::scopeClubs($query, auth()->user()))
            ->columns([
                TextColumn::make('name')->label('Club')->searchable(),
                TextColumn::make('landing_page_slug')->label('Slug')->copyable(),
                TextColumn::make('updated_at')->label('Updated')->since(),
            ])
            ->actions([
                EditAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListClubLandingPages::route('/'),
            'edit' => EditClubLandingPage::route('/{record}/edit'),
        ];
    }
}
