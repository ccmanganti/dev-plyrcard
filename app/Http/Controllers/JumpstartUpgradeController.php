<?php

namespace App\Http\Controllers;

use App\Services\JumpstartUpgradeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JumpstartUpgradeController extends Controller
{
    public function start(Request $request, JumpstartUpgradeService $service): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 401);

        $result = $service->start($user);
        return response()->json($result, ($result['success'] ?? false) ? 200 : 422);
    }

    public function status(Request $request, JumpstartUpgradeService $service): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 401);

        return response()->json($service->status($user));
    }
}
