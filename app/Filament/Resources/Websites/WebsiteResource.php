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
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;
use UnitEnum;

class WebsiteResource extends Resource
{
    protected static ?string $model = Website::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAlt;
    protected static string | BackedEnum | null $activeNavigationIcon = Heroicon::GlobeAlt;

    protected static string | UnitEnum | null $navigationGroup = 'Websites';

    public static function form(Schema $schema): Schema
    {
        return WebsiteForm::configure($schema)->schema([
            Placeholder::make('grapesjs_editor')
                ->label('Website Editor')
                ->columnSpan(2)
                ->content(fn ($record) => view('website-editor', ['record' => $record]))
                ->visible(fn ($record) => request()->is('admin/websites/*/edit')), // only show on edit page
            TextInput::make('light_color'),
            TextInput::make('dark_color'),

            Select::make('user_id')
                ->label('User')
                ->relationship('user', 'first_name')
                ->getOptionLabelFromRecordUsing(fn ($record) => $record->first_name . ' ' . $record->last_name)
                ->searchable()
                ->required()
                ->preload()
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return WebsiteInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WebsitesTable::configure($table)
        ->columns([
                TextColumn::make('user_full_name')
                    ->label('User')
                    ->getStateUsing(fn ($record) => $record->user ? $record->user->first_name . ' ' . $record->user->last_name : ''),
                TextColumn::make('updated_at')
                ->since()
                ->label('Updated'),
        ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
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
