<?php

use App\Http\Controllers\PublicPlayerIntakeController;
use App\Http\Controllers\PublicWebsiteController;
use App\Http\Controllers\WebsiteEditorController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public website routes
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

/*
|--------------------------------------------------------------------------
| Website editor routes
|--------------------------------------------------------------------------
*/
Route::prefix('admin')
    ->middleware(['auth'])
    ->group(function () {
        Route::get('/websites/{id}/load', [WebsiteEditorController::class, 'loadProject'])
            ->name('websites.load');

        Route::post('/websites/{id}/save', [WebsiteEditorController::class, 'saveProject'])
            ->name('websites.save');

        Route::get('/websites/{id}/editor', [WebsiteEditorController::class, 'editor'])
            ->name('websites.editor');
    });

/*
|--------------------------------------------------------------------------
| Website editor asset routes
|--------------------------------------------------------------------------
*/
Route::middleware(['web', 'auth'])->group(function () {
    Route::post('/websites/{id}/assets/upload', [WebsiteEditorController::class, 'uploadAsset'])
        ->name('websites.assets.upload');

    Route::delete('/websites/{id}/assets/delete', [WebsiteEditorController::class, 'deleteAsset'])
        ->name('websites.assets.delete');
});

// Route::get('/ssl-debug', function () {
//     return response()->json([
//         'loaded_php_ini' => php_ini_loaded_file(),
//         'curl.cainfo' => ini_get('curl.cainfo'),
//         'openssl.cafile' => ini_get('openssl.cafile'),
//         'cacert_exists' => file_exists(ini_get('curl.cainfo')),
//         'cacert_readable' => is_readable(ini_get('curl.cainfo')),
//         'php_sapi' => php_sapi_name(),
//         'curl_version' => function_exists('curl_version') ? curl_version() : null,
//     ]);
// });