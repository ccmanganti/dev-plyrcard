<?php

namespace App\Http\Controllers;

use App\Services\LocalRecruitingDatabaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LockerRoomSchoolActionController extends Controller
{
    public function favorite(Request $request, LocalRecruitingDatabaseService $database): JsonResponse
    {
        $data = $request->validate([
            'school_id' => ['required', 'string'],
            'favorite' => ['required', 'boolean'],
        ]);

        $result = $database->setFavorite($request->user(), $data['school_id'], (bool) $data['favorite']);

        return response()->json($result, ($result['success'] ?? false) ? 200 : 422);
    }

    public function list(Request $request, LocalRecruitingDatabaseService $database): JsonResponse
    {
        $data = $request->validate([
            'school_id' => ['required', 'string'],
            'list_key' => ['required', 'string', 'max:120'],
            'in_list' => ['required', 'boolean'],
        ]);

        $school = $database->findSchool($request->user(), $data['school_id']);
        if (! $school) {
            return response()->json(['success' => false, 'error' => 'School could not be found.'], 404);
        }

        $result = $database->setSchoolsInList(
            $request->user(),
            [(int) $school->getKey()],
            $data['list_key'],
            (bool) $data['in_list'],
        );

        return response()->json($result, ($result['success'] ?? false) ? 200 : 422);
    }
}
