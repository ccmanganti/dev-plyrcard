<?php

namespace App\Filament\Resources\Permissions;

use App\Filament\Resources\Permissions\Pages\CreatePermission;
use App\Filament\Resources\Permissions\Pages\EditPermission;
use App\Filament\Resources\Permissions\Pages\ListPermissions;
use App\Filament\Resources\Permissions\Schemas\PermissionForm;
use App\Filament\Resources\Permissions\Tables\PermissionsTable;
// use App\Models\Permission;
use Spatie\Permission\Models\Permission;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use UnitEnum;

class PermissionResource extends Resource
{
    protected static ?string $model = Permission::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;
    protected static string | BackedEnum | null $activeNavigationIcon = Heroicon::ShieldCheck;

    protected static string | UnitEnum | null $navigationGroup = 'Users & Permissions';

    public static function form(Schema $schema): Schema
    {
        return PermissionForm::configure($schema)->components([
            TextInput::make('name')
        ]);
    }

    public static function table(Table $table): Table
    {
        return PermissionsTable::configure($table)->columns([
            TextColumn::make('name'),
            TextColumn::make('created_at')
                ->since()
                ->label('Created'),
            TextColumn::make('updated_at')
                ->since()
                ->label('Updated')
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
            'index' => ListPermissions::route('/'),
            'create' => CreatePermission::route('/create'),
            'edit' => EditPermission::route('/{record}/edit'),
        ];
    }
}
