<?php
namespace App\Http\Controllers;
use App\Services\StripeBillingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
class StripeBillingController extends Controller
{
    public function summary(Request $request, StripeBillingService $stripe): JsonResponse
    {
        return response()->json(['success'=>true]+$stripe->billingSummary($request->user()));
    }
    public function paymentMethodSetup(Request $request, StripeBillingService $stripe): JsonResponse
    {
        try { return response()->json($stripe->createPaymentMethodSetup($request->user())); }
        catch (\Throwable $e) { return response()->json(['success'=>false,'message'=>$e->getMessage()],422); }
    }
    public function paymentMethodComplete(Request $request, StripeBillingService $stripe): JsonResponse
    {
        $data = $request->validate(['setup_intent_id' => ['required','string','max:255']]);
        try { return response()->json($stripe->completePaymentMethodSetup($request->user(), (string) $data['setup_intent_id'])); }
        catch (\Throwable $e) { return response()->json(['success'=>false,'message'=>$e->getMessage()],422); }
    }
    public function resume(Request $request, StripeBillingService $stripe): JsonResponse
    {
        try { return response()->json($stripe->resumeSubscription($request->user())); }
        catch (\Throwable $e) { return response()->json(['success'=>false,'message'=>$e->getMessage()],422); }
    }
}