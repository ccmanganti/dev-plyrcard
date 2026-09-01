<?php

namespace App\Http\Controllers;

use App\Services\MyJourneyUpgradeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MyJourneyUpgradeController extends Controller
{
    public function start(Request $request, MyJourneyUpgradeService $service): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 403);

        $result = $service->start($user);
        $status = ($result['error'] ?? false) ? 422 : 200;

        return response()->json(array_merge(['success' => ! ($result['error'] ?? false)], $result), $status);
    }

    public function status(Request $request, MyJourneyUpgradeService $service): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 403);

        return response()->json(array_merge(['success' => true], $service->status($user)));
    }
}
