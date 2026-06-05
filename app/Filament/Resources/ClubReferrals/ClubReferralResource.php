<?php

namespace App\Filament\Resources\ClubReferrals;

use App\Filament\Resources\ClubReferrals\Pages\CreateClubReferral;
use App\Filament\Resources\ClubReferrals\Pages\ListClubReferrals;
use App\Models\ClubReferral;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class ClubReferralResource extends Resource
{
    protected static ?string $model = ClubReferral::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedPaperAirplane;

    protected static string | UnitEnum | null $navigationGroup = 'Club Admin';

    protected static ?string $navigationLabel = 'Invites';

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([])->actions([])->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListClubReferrals::route('/'),
            'create' => CreateClubReferral::route('/create'),
        ];
    }
}