<?php

namespace App\Providers;

use App\Http\Controllers\PlyrPointsHistoryController;
use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class FilamentAssetServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        FilamentAsset::register([
            Js::make('plyrcard-onboarding', resource_path('js/filament/onboarding.js')),
        ]);

        Route::middleware(['web', 'auth'])
            ->get('/admin/plyr-points/history', PlyrPointsHistoryController::class)
            ->name('plyr-points.history');
    }
}