<?php

namespace App\Providers;

use App\Http\Controllers\PlyrPointServiceAdminController;
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

        Route::middleware(['web', 'auth'])->group(function (): void {
            Route::get('/admin/plyr-points/history', PlyrPointsHistoryController::class)
                ->name('plyr-points.history');

            Route::prefix('/admin/plyr-points')
                ->name('admin.plyr-points.')
                ->group(function (): void {
                    Route::post('/services', [PlyrPointServiceAdminController::class, 'store'])
                        ->name('services.store');
                    Route::put('/services/{service}', [PlyrPointServiceAdminController::class, 'update'])
                        ->name('services.update');
                    Route::post('/services/{service}/toggle', [PlyrPointServiceAdminController::class, 'toggle'])
                        ->name('services.toggle');
                    Route::delete('/services/{service}', [PlyrPointServiceAdminController::class, 'destroy'])
                        ->name('services.destroy');
                    Route::post('/services/{service}/samples/url', [PlyrPointServiceAdminController::class, 'addUrlSample'])
                        ->name('services.samples.url');
                    Route::post('/services/{service}/samples/upload', [PlyrPointServiceAdminController::class, 'uploadSamples'])
                        ->name('services.samples.upload');
                    Route::delete('/services/{service}/samples/{sample}', [PlyrPointServiceAdminController::class, 'removeSample'])
                        ->name('services.samples.destroy');
                });
        });
    }
}