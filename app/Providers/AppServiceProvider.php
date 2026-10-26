<?php

namespace App\Providers;

use App\Http\Controllers\PlyrPointServiceAdminController;
use App\Http\Controllers\PlyrPointsHistoryController;
use App\Models\BillingInformation;
use App\Models\User;
use App\Models\Website;
use App\Observers\UserObserver;
use App\Observers\WebsiteObserver;
use App\Http\Responses\LoginResponse as PlyrCardLoginResponse;
use App\Services\PlayerReferralService;
use Filament\Auth\Http\Responses\Contracts\LoginResponse as LoginResponseContract;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(LoginResponseContract::class, PlyrCardLoginResponse::class);
    }

    public function boot(): void
    {
        Gate::before(function ($user, string $ability) {
            return $user->hasRole('Superadmin') ? true : null;
        });

        User::observe(UserObserver::class);
        Website::observe(WebsiteObserver::class);

        // Referral rewards are tied to a proven paid My Journey entitlement, not
        // to merely submitting a referral code. The service is idempotent, so
        // recurring billing saves/webhook retries cannot issue duplicate points.
        BillingInformation::saved(function (BillingInformation $billing): void {
            if (strtolower(trim((string) $billing->payment_status)) !== 'paid') {
                return;
            }

            try {
                app(PlayerReferralService::class)->qualifyBilling($billing);
            } catch (\Throwable $exception) {
                Log::warning('PLYRCARD referral qualification failed after billing update.', [
                    'billing_id' => $billing->getKey(),
                    'user_id' => $billing->user_id,
                    'error' => $exception->getMessage(),
                ]);
            }
        });

        // PLYR Points routes live in the always-loaded AppServiceProvider so the
        // admin settings view can resolve its named routes reliably on every request.
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