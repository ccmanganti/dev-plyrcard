<?php

use App\Http\Controllers\WebsiteEditorController;
use App\Models\Website;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function (Request $request) {
    $host = strtolower($request->getHost());
    $normalizedHost = preg_replace('/^www\./', '', $host);

    /*
    |--------------------------------------------------------------------------
    | Platform domain -> admin
    |--------------------------------------------------------------------------
    */
    $platformHosts = [
        'dev.plyrcard.com',
        'plyrcard.com',
        'www.plyrcard.com',
    ];

    if (in_array($host, $platformHosts, true)) {
        return redirect('/admin');
    }

    /*
    |--------------------------------------------------------------------------
    | Local development
    |--------------------------------------------------------------------------
    | Example:
    | http://127.0.0.1:8000
    | http://localhost:8000
    |--------------------------------------------------------------------------
    */
    if (in_array($host, ['127.0.0.1', 'localhost'], true)) {
        $website = Website::query()
            ->with([
                'user.school',
                'user.club.league',
                'siteTemplate',
                'heroTemplate',
                'fieldValues.templateField',
                'heroFieldValues.templateField',
            ])
            ->orderBy('id')
            ->first();

        abort_unless($website, 404, 'No website record found.');

        abort_unless(
            $website->siteTemplate && $website->siteTemplate->blade_view,
            404,
            'The website does not have a valid site template.'
        );

        return view($website->siteTemplate->blade_view, compact('website'));
    }

    /*
    |--------------------------------------------------------------------------
    | Production custom domains
    |--------------------------------------------------------------------------
    | Match users.domain
    |--------------------------------------------------------------------------
    */
    $website = Website::query()
        ->whereHas('user', function ($query) use ($host, $normalizedHost) {
            $query->where(function ($subQuery) use ($host, $normalizedHost) {
                $subQuery
                    ->whereRaw('LOWER(domain) = ?', [$host])
                    ->orWhereRaw('LOWER(domain) = ?', [$normalizedHost])
                    ->orWhereRaw("LOWER(REPLACE(domain, 'www.', '')) = ?", [$normalizedHost]);
            });
        })
        ->with([
            'user.school',
            'user.club.league',
            'siteTemplate',
            'heroTemplate',
            'fieldValues.templateField',
            'heroFieldValues.templateField',
        ])
        ->first();

    if (! $website) {
        abort(404);
    }

    if (! $website->siteTemplate || ! $website->siteTemplate->blade_view) {
        abort(404, 'The website does not have a valid site template.');
    }

    return view($website->siteTemplate->blade_view, compact('website'));
})->name('website.home');

/*
|--------------------------------------------------------------------------
| Local/manual preview routes
|--------------------------------------------------------------------------
| Useful for previewing a specific website even without domain setup.
|--------------------------------------------------------------------------
*/
Route::get('/preview/{website}', function (Website $website) {
    $website->load([
        'user.school',
        'user.club.league',
        'siteTemplate',
        'heroTemplate',
        'fieldValues.templateField',
        'heroFieldValues.templateField',
    ]);

    abort_unless(
        $website->siteTemplate && $website->siteTemplate->blade_view,
        404,
        'The website does not have a valid site template.'
    );

    return view($website->siteTemplate->blade_view, compact('website'));
})->name('website.preview');

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

Route::middleware(['web', 'auth'])->group(function () {
    Route::post('/websites/{id}/assets/upload', [WebsiteEditorController::class, 'uploadAsset'])
        ->name('websites.assets.upload');

    Route::delete('/websites/{id}/assets/delete', [WebsiteEditorController::class, 'deleteAsset'])
        ->name('websites.assets.delete');
});