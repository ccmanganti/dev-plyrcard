<?php

namespace App\Http\Controllers;

use App\Services\StripeBillingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PointPackPurchaseController extends Controller
{
    public function start(Request $request, StripeBillingService $stripe, string $pack): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 403);
        try {
            return response()->json($stripe->startPointPack($user, $pack));
        } catch (\Throwable $exception) {
            return response()->json(['success' => false, 'completed' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function status(Request $request, StripeBillingService $stripe, string $pack): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 403);
        return response()->json($stripe->pointPackStatus($user, $pack));
    }
}
