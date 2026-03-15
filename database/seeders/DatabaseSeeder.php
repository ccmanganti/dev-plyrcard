<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;


class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    protected static ?string $password = null;

    public function run(): void
    {
        $role = Role::firstOrCreate(['name' => 'Superadmin']);

        $user = User::updateOrCreate(
            ['email' => 'superadmin@plyrcard.com'],
            [
                'first_name' => 'Superadmin',
                'last_name' => 'User',
                'email_verified_at' => now(),
                'password' => static::$password ??= Hash::make('password'),
            ]
        );

        if (! $user->hasRole($role->name)) {
            $user->assignRole($role);
        }

        $this->call([
            Template1SiteTemplateSeeder::class,
            Template1HeroTemplateSeeder::class,
            Template2HeroTemplateSeeder::class,
        ]);
    }
}