<?php

namespace App\Http\Controllers;

use App\Services\LocalRecruitingTrackingService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class RecruitingProfileViewTrackingController extends Controller
{
    public function __invoke(Request $request, LocalRecruitingTrackingService $tracking): Response
    {
        $payload=[
            'athlete_id'=>$request->input('rc_athlete_id',$request->query('rc_athlete_id')),
            'contact_id'=>$request->input('rc_contact_id',$request->query('rc_contact_id',$request->query('rc_ghl_contact_id'))),
            'business_id'=>$request->input('rc_business_id',$request->query('rc_business_id')),
            'platform'=>$request->input('rc_platform',$request->query('rc_platform',$request->query('utm_medium','website'))),
            'source'=>$request->input('rc_source',$request->query('rc_source','player_website')),
            'destination_url'=>$request->input('destination_url',$request->fullUrl()),
            'event_type'=>'profile_view',
        ];
        $event=$tracking->record($payload,$request,'profile_view');
        return response('', $event?204:202)->header('Cache-Control','no-store, no-cache, must-revalidate, max-age=0')->header('Pragma','no-cache');
    }
}