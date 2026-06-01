<?php

namespace App\Filament\Resources\Profiles;

use App\Filament\Resources\Profiles\Pages\EditProfile;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;

class ProfileResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUser;
    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::User;

    protected static ?string $navigationLabel = 'Profile';
    protected static ?string $slug = 'my-profile';

    public static function canAccess(): bool
    {
        return auth()->check();
    }

    protected static function isSuperadminNavigationUser(): bool
    {
        $user = auth()->user();

        return $user
            && method_exists($user, 'hasRole')
            && (
                $user->hasRole('Superadmin')
                || $user->hasRole('superadmin')
                || $user->hasRole('Super Admin')
            );
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && ! static::isSuperadminNavigationUser();
    }

    public static function getNavigationUrl(): string
    {
        return static::getUrl('index');
    }

    public static function getPages(): array
    {
        return [
            'index' => EditProfile::route('/'),
        ];
    }

    public static function getRelations(): array
    {
        return [];
    }
}