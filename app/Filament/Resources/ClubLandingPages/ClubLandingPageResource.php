<?php

namespace App\Filament\Resources\ClubLandingPages;

use App\Filament\Resources\ClubLandingPages\Pages\EditClubLandingPage;
use App\Filament\Resources\ClubLandingPages\Pages\ListClubLandingPages;
use App\Models\Club;
use App\Support\ClubManagerAccess;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
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

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => ClubManagerAccess::scopeClubs($query, auth()->user()))
            ->columns([])
            ->actions([])
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