<?php

use App\Http\Controllers\PublicPlayerIntakeController;
use App\Http\Controllers\PublicWebsiteController;
use App\Http\Controllers\WebsiteEditorController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;

/*
|--------------------------------------------------------------------------
| Marketing routes
|--------------------------------------------------------------------------
*/

require __DIR__ . '/marketing-routes.php';

/*
|--------------------------------------------------------------------------
| Reserved public slugs
|--------------------------------------------------------------------------
|
| These should never be treated as public website names.
| Important: "admin" must stay reserved because Filament owns /admin.
|
*/

$reservedWebsiteSlugs = implode('|', [
    'admin',
    'about',
    'pricing',
    'podcast',
    'book-demo',
    'registration',
    'player-intake',
    'player-intake-app',
    'preview',
    'login',
    'logout',
    'register',
    'password-reset',
    'forgot-password',
    'email-verification',
    'livewire',
    'filament',
    'storage',
    'api',
]);

/*
|--------------------------------------------------------------------------
| Public website root
|--------------------------------------------------------------------------
*/

Route::get('/', [PublicWebsiteController::class, 'home'])
    ->name('website.home');

/*
|--------------------------------------------------------------------------
| Local/manual preview routes
|--------------------------------------------------------------------------
*/

Route::get('/preview/{website}', [PublicWebsiteController::class, 'preview'])
    ->name('website.preview');

/*
|--------------------------------------------------------------------------
| Public player intake routes
|--------------------------------------------------------------------------
*/

Route::get('/player-intake', [PublicPlayerIntakeController::class, 'create'])
    ->name('public.player-intake.create');

Route::post('/player-intake', [PublicPlayerIntakeController::class, 'store'])
    ->name('public.player-intake.store');

Route::get('/player-intake-app', [PublicPlayerIntakeController::class, 'createApp'])
    ->name('public.player-intake-app.create');

Route::post('/player-intake-app', [PublicPlayerIntakeController::class, 'storeApp'])
    ->name('public.player-intake-app.store');

Route::get('/player-intake-app/auto-login/{user}', [PublicPlayerIntakeController::class, 'autoLogin'])
    ->middleware('signed')
    ->name('public.player-intake-app.auto-login');

/*
|--------------------------------------------------------------------------
| Filament-related custom admin routes
|--------------------------------------------------------------------------
|
| Filament itself should own:
|
|   /admin
|   /admin/login
|   /admin/password-reset/...
|
| These custom routes are only for your website editor actions.
|
*/

Route::prefix('admin/websites')
    ->middleware(['web', 'auth'])
    ->name('websites.')
    ->group(function () {
        Route::get('/{id}/editor', [WebsiteEditorController::class, 'editor'])
            ->name('editor');

        Route::get('/{id}/load', [WebsiteEditorController::class, 'loadProject'])
            ->name('load');

        Route::post('/{id}/save', [WebsiteEditorController::class, 'saveProject'])
            ->name('save');

        Route::post('/{id}/assets/upload', [WebsiteEditorController::class, 'uploadAsset'])
            ->name('assets.upload');

        Route::delete('/{id}/assets/delete', [WebsiteEditorController::class, 'deleteAsset'])
            ->name('assets.delete');
    });

/*
|--------------------------------------------------------------------------
| Onboarding routes
|--------------------------------------------------------------------------
*/

Route::middleware(['web', 'auth'])->post('/onboarding/complete', function (Request $request) {
    $user = $request->user();

    if (! $user) {
        return response()->json([
            'success' => false,
            'message' => 'No authenticated user.',
        ], 401);
    }

    if (is_null($user->onboarding_completed_at)) {
        $user->onboarding_completed_at = now();
        $user->save();
        $user->refresh();
    }

    return response()->json([
        'success' => true,
        'user_id' => $user->id,
        'onboarding_completed_at' => optional($user->onboarding_completed_at)->toDateTimeString(),
    ]);
})->name('onboarding.complete');

/*
|--------------------------------------------------------------------------
| Public website-by-name route
|--------------------------------------------------------------------------
|
| Keep this at the very bottom.
| This prevents public website names from hijacking reserved app routes.
|
*/

Route::get('/{websiteName}', [PublicWebsiteController::class, 'showByName'])
    ->where('websiteName', '^(?!(' . $reservedWebsiteSlugs . ')$)[A-Za-z0-9\-]+$')
    ->name('website.show-by-name');


Route::post('/admin/login', function (Request $request) {
    $credentials = $request->validate([
        'email' => ['required', 'email'],
        'password' => ['required', 'string'],
    ]);

    if (! Auth::attempt($credentials, $request->boolean('remember'))) {
        return back()
            ->withErrors(['email' => 'These credentials do not match our records.'])
            ->onlyInput('email');
    }

    $request->session()->regenerate();

    $user = Auth::user();

    $website = \App\Models\Website::query()
        ->where('user_id', $user->id)
        ->where('is_active', true)
        ->where('is_published', true)
        ->latest('updated_at')
        ->first();

    if (! $website) {
        return redirect('/');
    }

    if (! blank($website->domain)) {
        $domain = preg_replace('#^https?://#i', '', trim($website->domain));
        return redirect()->away('https://' . rtrim($domain, '/'));
    }

    if (! blank($website->slug)) {
        return redirect('/' . ltrim($website->slug, '/'));
    }

    return redirect('/');
})->middleware('web')->name('plyrcard.drawer-login');