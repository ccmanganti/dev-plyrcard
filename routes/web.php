<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Models\User;
use App\Http\Controllers\WebsiteEditorController;

Route::get('/', function (Request $request) {

    $host = $request->getHost();
    // MAIN PLATFORM
    // if ($host === 'dev.plyrcard.com') {
    //     return redirect('/admin'); // or main landing
    // }

    // if ($host === '127.0.0.1') {
    //     return redirect('/admin'); // or main landing
    // }

    // return view('template_one'); // or main landing
    // return view('slide_builder'); // or main landing


    // CUSTOM DOMAIN
    // $user = User::where('domain', $host)
    //     ->with('website')
    //     ->first();

    // if (! $user || ! $user->website) {
    //     abort(404);
    // }

    // $projectJson = $user->website->project_json;

    // return view('template_one', compact('user', 'projectJson'));

    $user = User::where('first_name', 'Sebastian')
        ->with('website')
        ->firstOrFail();

    $html = null;
    $css = null;

    if ($user->website && $user->website->html && $user->website->css) {
        $html = $user->website->html;
        $css = $user->website->css;
    }
    return view('template_one', compact('user', 'html', 'css'));
});


Route::prefix('admin')->middleware(['auth'])->group(function () {
    Route::get('/websites/{id}/load', [WebsiteEditorController::class, 'loadProject'])->name('websites.load');
    Route::post('/websites/{id}/save', [WebsiteEditorController::class, 'saveProject'])->name('websites.save');
});

Route::middleware(['web', 'auth'])->group(function () {
    Route::post('/websites/{id}/assets/upload', [WebsiteEditorController::class, 'uploadAsset'])
        ->name('websites.assets.upload');

    Route::delete('/websites/{id}/assets/delete', [WebsiteEditorController::class, 'deleteAsset'])
        ->name('websites.assets.delete');
});

Route::prefix('admin')->middleware(['auth'])->group(function () {
    Route::get('/websites/{id}/load', [WebsiteEditorController::class, 'loadProject'])->name('websites.load');
    Route::post('/websites/{id}/save', [WebsiteEditorController::class, 'saveProject'])->name('websites.save');

    // NEW: iframe editor page
    Route::get('/websites/{id}/editor', [WebsiteEditorController::class, 'editor'])
        ->name('websites.editor');
});