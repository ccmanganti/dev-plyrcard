<?php

namespace App\Http\Controllers;

use App\Services\AmplifyUpgradeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AmplifyUpgradeController extends Controller
{
    public function start(Request $request, AmplifyUpgradeService $service): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 401);

        $result = $service->start($user);
        return response()->json($result, ($result['success'] ?? false) ? 200 : 422);
    }

    public function status(Request $request, AmplifyUpgradeService $service): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 401);

        return response()->json($service->status($user));
    }
}
