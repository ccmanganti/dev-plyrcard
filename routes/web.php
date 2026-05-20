<?php

use App\Http\Controllers\LockerRoomController;
use App\Http\Controllers\PublicClubTeamController;
use App\Http\Controllers\PublicPlayerIntakeController;
use App\Http\Controllers\PublicWebsiteController;
use App\Http\Controllers\WebsiteEditorController;
use App\Http\Controllers\WebsiteOwnerAccessController;
use App\Models\Website;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

require __DIR__ . '/marketing-routes.php';

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
    'locker-room',
    'clubs',
    'teams',
    'csrf-token',
]);

Route::get('/', [PublicWebsiteController::class, 'home'])
    ->name('website.home');

Route::get('/preview/{website}', [PublicWebsiteController::class, 'preview'])
    ->name('website.preview');

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

Route::prefix('admin/websites')
    ->middleware(['auth'])
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

Route::post('/onboarding/complete', function (Request $request) {
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
})
    ->middleware(['auth'])
    ->name('onboarding.complete');

/*
|--------------------------------------------------------------------------
| CSRF refresh route
|--------------------------------------------------------------------------
|
| Keep this above the catch-all /{websiteName} route.
|
*/

Route::get('/csrf-token', function (Request $request) {
    return response()->json([
        'success' => true,
        'csrf_token' => csrf_token(),
    ]);
})->name('csrf-token');

/*
|--------------------------------------------------------------------------
| Drawer login route
|--------------------------------------------------------------------------
|
| Do not use /admin/login here. Filament owns /admin/login.
| This route is for the Locker Room / Get Started drawer login form.
|
*/

Route::post('/locker-room/login', function (Request $request) {
    $expectsJson = $request->expectsJson() || $request->ajax();

    $credentials = $request->validate([
        'email' => ['required', 'email'],
        'password' => ['required', 'string'],
    ]);

    if (! Auth::attempt($credentials, $request->boolean('remember'))) {
        if ($expectsJson) {
            return response()->json([
                'success' => false,
                'message' => 'These credentials do not match our records.',
                'errors' => [
                    'email' => ['These credentials do not match our records.'],
                ],
            ], 422);
        }

        return back()
            ->withErrors(['email' => 'These credentials do not match our records.'])
            ->onlyInput('email');
    }

    $request->session()->regenerate();
    $request->session()->regenerateToken();

    $user = Auth::user();

    $website = Website::query()
        ->where('user_id', $user->id)
        ->where('is_active', true)
        ->where('is_published', true)
        ->latest('updated_at')
        ->first();

    $redirectUrl = url('/');

    if ($website) {
        if (! blank($website->domain) && Route::has('locker-room.website.visit')) {
            $redirectUrl = route('locker-room.website.visit', $website);
        } elseif (! blank($website->slug)) {
            $redirectUrl = url('/' . ltrim($website->slug, '/'));
        }
    }

    if ($expectsJson) {
        return response()->json([
            'success' => true,
            'message' => 'Signed in successfully.',
            'redirect_url' => $redirectUrl,
            'csrf_token' => csrf_token(),
        ]);
    }

    return redirect()->to($redirectUrl);
})->name('plyrcard.drawer-login');

Route::middleware(['web', 'auth'])
    ->get('/locker-room/visit-my-website/{website}', [WebsiteOwnerAccessController::class, 'redirectToOwnedWebsite'])
    ->name('locker-room.website.visit');

Route::middleware(['web'])
    ->get('/locker-room/owner-access', [WebsiteOwnerAccessController::class, 'consumeOwnerAccess'])
    ->name('locker-room.website.owner-access');

Route::get('/clubs/{slug}', [PublicClubTeamController::class, 'club'])
    ->name('clubs.landing');

Route::get('/clubs/teams/{gender}/{slug}', [PublicClubTeamController::class, 'teamByClubGender'])
    ->whereIn('gender', ['mens', 'womens'])
    ->name('clubs.teams.landing');

Route::get('/teams/{slug}', [PublicClubTeamController::class, 'team'])
    ->name('teams.landing');

Route::middleware(['auth'])->group(function () {
    Route::post('/locker-room/profile', [LockerRoomController::class, 'updateProfile'])
        ->name('locker-room.profile.update');

    Route::post('/locker-room/schedule', [LockerRoomController::class, 'storeSchedule'])
        ->name('locker-room.schedule.store');

    Route::post('/locker-room/settings', [LockerRoomController::class, 'updateSettings'])
        ->name('locker-room.settings.update');

    Route::post('/locker-room/billing', [LockerRoomController::class, 'updateBilling'])
        ->name('locker-room.billing.update');

    Route::post('/locker-room/support', [LockerRoomController::class, 'storeSupport'])
        ->name('locker-room.support.store');

    Route::post('/locker-room/referral', [LockerRoomController::class, 'storeReferral'])
        ->name('locker-room.referral.store');

    Route::post('/locker-room/additional-service', [LockerRoomController::class, 'storeAdditionalService'])
        ->name('locker-room.additional-service.store');

    Route::post('/locker-room/website-settings', [LockerRoomController::class, 'updateWebsiteSettings'])
        ->name('locker-room.website-settings.update');

    Route::post('/locker-room/website-calendar/refresh', [LockerRoomController::class, 'refreshWebsiteCalendar'])
        ->name('locker-room.website-calendar.refresh');

    Route::post('/locker-room/password', [LockerRoomController::class, 'updatePassword'])
        ->name('locker-room.password.update');
});

Route::get('/{websiteName}', [PublicWebsiteController::class, 'showByName'])
    ->where('websiteName', '^(?!(' . $reservedWebsiteSlugs . ')$)[A-Za-z0-9\-]+$')
    ->name('website.show-by-name');