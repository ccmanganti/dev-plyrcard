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
use App\Http\Controllers\TrackingController;

Route::get('/track/click/{token}', [TrackingController::class, 'click'])->name('tracking.click');
Route::get('/track/profile/{token}', [TrackingController::class, 'profile'])->name('tracking.profile');
Route::get('/track/open/{token}.gif', [TrackingController::class, 'open'])->name('tracking.open');

require __DIR__ . '/club_referrals.php';
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
    'locker-room',
    'clubs',
    'teams',
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
| Club / Team Landing Pages
|--------------------------------------------------------------------------
|
| Keep these ABOVE the catch-all /{websiteName} route.
|
| Current public structure:
|
| /clubs/{clubSlug}
| /clubs/{clubSlug}/teams/boys/{teamSlug}
| /clubs/{clubSlug}/teams/girls/{teamSlug}
|
| Legacy accepted team gender segments:
|
| /clubs/{clubSlug}/teams/mens/{teamSlug}
| /clubs/{clubSlug}/teams/womens/{teamSlug}
|
| The controller should normalize:
| mens   -> boys
| womens -> girls
|
*/

Route::get('/clubs/{clubSlug}', [PublicClubTeamController::class, 'club'])
    ->name('clubs.landing');

Route::post('/clubs/{clubSlug}/coach-checkin', [PublicClubTeamController::class, 'coachCheckIn'])
    ->name('clubs.coach-checkin');

Route::post('/clubs/{clubSlug}/coach-checkout', [PublicClubTeamController::class, 'coachCheckOut'])
    ->name('clubs.coach-checkout');

Route::post('/clubs/{clubSlug}/coach-watchlist/email', [PublicClubTeamController::class, 'emailWatchlist'])
    ->name('clubs.coach-email-watchlist');

Route::get('/clubs/{clubSlug}/teams/{gender}/{teamSlug}', [PublicClubTeamController::class, 'team'])
    ->whereIn('gender', ['boys', 'girls', 'mens', 'womens'])
    ->name('clubs.teams.landing');

Route::post('/clubs/{clubSlug}/teams/{gender}/{teamSlug}/players/{player}/save', [PublicClubTeamController::class, 'savePlayer'])
    ->whereIn('gender', ['boys', 'girls', 'mens', 'womens'])
    ->whereNumber('player')
    ->name('clubs.coach-save-player');

Route::delete('/clubs/{clubSlug}/teams/{gender}/{teamSlug}/players/{player}/save', [PublicClubTeamController::class, 'unsavePlayer'])
    ->whereIn('gender', ['boys', 'girls', 'mens', 'womens'])
    ->whereNumber('player')
    ->name('clubs.coach-unsave-player');

/*
|--------------------------------------------------------------------------
| Legacy Team Redirect
|--------------------------------------------------------------------------
|
| Keep this for old /teams/{slug} links. It redirects to the new club-based URI.
| You can remove this later once old links are no longer used.
|
*/

Route::get('/teams/{teamSlug}', [PublicClubTeamController::class, 'legacyTeam'])
    ->name('teams.landing');

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

/*
|--------------------------------------------------------------------------
| Onboarding routes
|--------------------------------------------------------------------------
*/

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
| Drawer login route
|--------------------------------------------------------------------------
|
| Do not use /admin/login here. Filament owns /admin/login.
| This route is for the Locker Room / Get Started drawer login form.
|
| Important:
| If the player has a custom domain, we send them through the owner bridge
| route instead of directly redirecting to the custom domain. This lets the
| custom domain receive its own owner/session access.
|
*/

Route::get('/csrf-token', function () {
    return response()->json([
        'success' => true,
        'csrf_token' => csrf_token(),
    ]);
})->name('csrf-token');

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
        if (! blank($website->domain)) {
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

/*
|--------------------------------------------------------------------------
| Locker Room owner website access bridge
|--------------------------------------------------------------------------
|
| This is needed because a session on plyrcard.com/admin does not automatically
| exist on a custom player domain like selinpehlivan.com.
|
| Flow:
| 1. Logged-in user clicks Visit my Website.
| 2. /locker-room/visit-my-website/{website} verifies ownership.
| 3. It redirects to the custom domain with a temporary signed access token.
| 4. /locker-room/owner-access on the custom domain consumes the token and
|    logs the owner in on that domain.
|
| Keep these routes above the catch-all /{websiteName} route.
|
*/

Route::middleware(['web', 'auth'])
    ->get('/locker-room/visit-my-website/{website}', [WebsiteOwnerAccessController::class, 'redirectToOwnedWebsite'])
    ->name('locker-room.website.visit');

Route::middleware(['web'])
    ->get('/locker-room/owner-access', [WebsiteOwnerAccessController::class, 'consumeOwnerAccess'])
    ->name('locker-room.website.owner-access');

/*
|--------------------------------------------------------------------------
| Locker Room drawer form routes
|--------------------------------------------------------------------------
*/

Route::middleware(['auth'])->group(function () {
    Route::post('/locker-room/profile', [LockerRoomController::class, 'updateProfile'])
        ->name('locker-room.profile.update');

    Route::post('/locker-room/schedule', [LockerRoomController::class, 'storeSchedule'])
        ->name('locker-room.schedule.store');

    Route::post('/locker-room/settings', [LockerRoomController::class, 'updateSettings'])
        ->name('locker-room.settings.update');

    Route::post('/locker-room/support', [LockerRoomController::class, 'storeSupport'])
        ->name('locker-room.support.store');

    Route::post('/locker-room/referral', [LockerRoomController::class, 'storeReferral'])
        ->name('locker-room.referral.store');
});

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