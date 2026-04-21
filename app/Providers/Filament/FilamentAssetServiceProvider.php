<?php

namespace App\Providers;

use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Support\ServiceProvider;

class FilamentAssetServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        FilamentAsset::register([
            Js::make('plyrcard-onboarding', resource_path('js/filament/onboarding.js')),
        ]);
    }
}