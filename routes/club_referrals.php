<?php

use App\Http\Controllers\ClubReferralController;
use Illuminate\Support\Facades\Route;

Route::get('/club-invite/{referral:token}', [ClubReferralController::class, 'show'])
    ->name('club-referrals.registration');