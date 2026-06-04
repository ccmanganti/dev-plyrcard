<?php

namespace App\Http\Controllers;

use App\Models\ClubReferral;
use Illuminate\Http\RedirectResponse;

class ClubReferralController extends Controller
{
    public function show(ClubReferral $referral): RedirectResponse
    {
        $referral->markClicked();

        return redirect()->away($referral->registrationUrl());
    }
}