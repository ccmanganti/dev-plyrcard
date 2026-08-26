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
use App\Http\Controllers\RecruitingProfileViewTrackingController;
use App\Http\Controllers\Admin\ExternalTrackingUrlGeneratorController;
use App\Http\Controllers\ExternalSocialTrackingController;
use App\Http\Controllers\LockerRoomPasswordResetController;
use App\Http\Middleware\SendPlayerActivityEmail;

/*
 * Load this file from routes/web.php before any catch-all /{slug} route:
 * require __DIR__ . '/external_tracking.php';
 */

Route::middleware('auth')->group(function (): void {
    Route::get('/url-generator-external-tracking', [ExternalTrackingUrlGeneratorController::class, 'index'])
        ->name('admin.external-tracking-url-generator');

    Route::post('/url-generator-external-tracking', [ExternalTrackingUrlGeneratorController::class, 'generate'])
        ->name('admin.external-tracking-url-generator.generate');
});

/* Platform-hosted player: /Sample/out/instagram */
Route::get('/{slug}/out/{platform}', [ExternalSocialTrackingController::class, 'platform'])
    ->where('platform', 'instagram|youtube|x')
    ->middleware(SendPlayerActivityEmail::class)
    ->name('external.social.platform');

/* Parked/custom domain player: /out/instagram */
Route::get('/out/{platform}', [ExternalSocialTrackingController::class, 'customDomain'])
    ->where('platform', 'instagram|youtube|x')
    ->middleware(SendPlayerActivityEmail::class)
    ->name('external.social.custom-domain');

Route::get('/track/click/{token}', [TrackingController::class, 'click'])->where('token', '[^/]+')->name('tracking.click');
Route::get('/track/profile/{token}', [TrackingController::class, 'profile'])->where('token', '[^/]+')->name('tracking.profile');
Route::get('/track/open/{token}.gif', [TrackingController::class, 'open'])->where('token', '[^/]+')->name('tracking.open');

Route::get('/recruiting/profile-view', RecruitingProfileViewTrackingController::class)->name('recruiting.profile-view');


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
    'recruiting',
]);

/*
|--------------------------------------------------------------------------
| Public website root
|--------------------------------------------------------------------------
*/

Route::get('/', [PublicWebsiteController::class, 'home'])
    ->middleware([
        \App\Http\Middleware\ShowPendingPlyrcard::class,
        SendPlayerActivityEmail::class,
    ])
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

Route::post('/locker-room/password-reset/request', [LockerRoomPasswordResetController::class, 'store'])
    ->middleware('throttle:5,1')
    ->name('locker-room.password-reset.request');

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

    // v10.38: after drawer sign-in, return the player to THEIR published PLYRCARD.
    // If it is still preparing/unpublished, use the main PLYRCARD homepage instead.
    // Build from APP_URL so a request originating on a custom domain never sends the
    // user to that custom domain's root by mistake.
    $mainAppUrl = rtrim((string) config('app.url'), '/');
    if ($mainAppUrl === '') {
        $mainAppUrl = rtrim($request->getSchemeAndHttpHost(), '/');
    }

    $redirectUrl = $mainAppUrl . '/';

    if ($website) {
        if (! blank($website->domain)) {
            $ownerBridgePath = route('locker-room.website.visit', $website, false);
            $redirectUrl = $mainAppUrl . '/' . ltrim($ownerBridgePath, '/');
        } elseif (! blank($website->slug)) {
            $redirectUrl = $mainAppUrl . '/' . ltrim((string) $website->slug, '/');
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

Route::middleware(['auth', 'signed'])
    ->get('/billing/payment-method/return', function (\Illuminate\Http\Request $request) {
        if ($request->user()) {
            try {
                app(\App\Services\BillingProfileService::class)
                    ->refreshPaymentIdentity($request->user());
            } catch (\Throwable $exception) {
                report($exception);
            }
        }

        return redirect('/admin/coach-database/settings?payment_method=updated#billing-payments');
    })
    ->name('billing.payment-method.return');

Route::middleware(['auth'])->group(function () {
    Route::get('/locker-room/data', [LockerRoomController::class, 'data'])
        ->name('locker-room.data');

    Route::get('/locker-room/profile/options', [LockerRoomController::class, 'profileOptions'])
        ->name('locker-room.profile.options');

    Route::get('/locker-room/dashboard/activity', [LockerRoomController::class, 'dashboardActivity'])
        ->name('locker-room.dashboard.activity');

    Route::get('/locker-room/dashboard/school/{school}', [LockerRoomController::class, 'dashboardSchool'])
        ->where('school', '[^/]+')
        ->name('locker-room.dashboard.school');

    Route::post('/locker-room/profile', [LockerRoomController::class, 'updateProfile'])
        ->name('locker-room.profile.update');

    Route::post('/locker-room/schedule', [LockerRoomController::class, 'storeSchedule'])
        ->name('locker-room.schedule.store');

    Route::put('/locker-room/schedule/{schedule}', [LockerRoomController::class, 'updateSchedule'])
        ->whereNumber('schedule')
        ->name('locker-room.schedule.update');

    Route::delete('/locker-room/schedule/{schedule}', [LockerRoomController::class, 'deleteSchedule'])
        ->whereNumber('schedule')
        ->name('locker-room.schedule.delete');

    Route::post('/locker-room/settings', [LockerRoomController::class, 'updateSettings'])
        ->name('locker-room.settings.update');

    // Compatibility aliases for the former separate Website Settings UI.
    // The v10.33 Locker Room now exposes these controls inside Settings.
    Route::post('/locker-room/website-settings', [LockerRoomController::class, 'updateWebsiteSettings'])
        ->name('locker-room.website-settings.update');

    Route::post('/locker-room/website-settings/calendar/refresh', [LockerRoomController::class, 'refreshWebsiteCalendar'])
        ->name('locker-room.website-settings.calendar.refresh');

    Route::post('/locker-room/billing', [LockerRoomController::class, 'updateBilling'])
        ->name('locker-room.billing.update');

    Route::post('/locker-room/support', [LockerRoomController::class, 'storeSupport'])
        ->name('locker-room.support.store');

    Route::post('/locker-room/referral', [LockerRoomController::class, 'storeReferral'])
        ->name('locker-room.referral.store');

    Route::post('/locker-room/additional-service', [LockerRoomController::class, 'storeAdditionalService'])
        ->name('locker-room.additional-service.store');

    Route::post('/locker-room/password', [LockerRoomController::class, 'updatePasswordFromOverlay'])
        ->name('locker-room.password.update');
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
    ->middleware([
        \App\Http\Middleware\ShowPendingPlyrcard::class,
        SendPlayerActivityEmail::class,
    ])
    ->where('websiteName', '^(?!(' . $reservedWebsiteSlugs . ')$)[A-Za-z0-9\-]+$')
    ->name('website.show-by-name');