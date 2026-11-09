<?php

namespace App\Http\Controllers;

use App\Jobs\ProvisionPlyrLabAthlete;
use App\Models\PlyrLabBooking;
use App\Models\PlyrLabTrainer;
use App\Notifications\PlyrLabTrainerBookedNotification;
use App\Services\PlyrLabDataService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class PlyrLabController extends Controller
{
    public function index(Request $request, PlyrLabDataService $dataService): JsonResponse
    {
        $player = $request->user();
        abort_unless($player, 403);

        return response()->json([
            'success' => true,
            'data' => $dataService->payload($player),
        ]);
    }

    public function book(Request $request, PlyrLabDataService $dataService): JsonResponse
    {
        $player = $request->user();
        abort_unless($player, 403);

        $validated = $request->validate([
            'trainer_id' => ['required', 'integer', 'exists:plyr_lab_trainers,id'],
            'scheduled_for' => ['required', 'date', 'after:now'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $trainer = PlyrLabTrainer::query()
            ->with('user')
            ->whereKey((int) $validated['trainer_id'])
            ->where('is_active', true)
            ->firstOrFail();

        $existing = PlyrLabBooking::query()
            ->where('player_user_id', $player->getKey())
            ->whereIn('status', PlyrLabBooking::OPEN_STATUSES)
            ->latest('id')
            ->first();

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'You already have an active PLYRLab trainer booking.',
                'data' => $dataService->payload($player),
            ], 422);
        }

        $booking = DB::transaction(function () use ($player, $trainer, $validated): PlyrLabBooking {
            return PlyrLabBooking::query()->create([
                'player_user_id' => $player->getKey(),
                'trainer_id' => $trainer->getKey(),
                'scheduled_for' => $validated['scheduled_for'],
                'status' => 'pending',
                'notes' => $validated['notes'] ?? null,
            ]);
        });

        if ($trainer->user) {
            try {
                $trainer->user->notify(new PlyrLabTrainerBookedNotification($booking->getKey()));
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        ProvisionPlyrLabAthlete::dispatch($booking->getKey())->afterCommit();

        return response()->json([
            'success' => true,
            'message' => 'Trainer booked. Your PLYRLab is unlocked while your Plyomat profile is prepared.',
            'data' => $dataService->payload($player),
        ]);
    }

    public function refresh(Request $request, PlyrLabDataService $dataService): JsonResponse
    {
        $player = $request->user();
        abort_unless($player, 403);

        $booking = PlyrLabBooking::query()
            ->with(['trainer.user'])
            ->where('player_user_id', $player->getKey())
            ->whereIn('status', PlyrLabBooking::ACCESS_STATUSES)
            ->latest('id')
            ->first();

        if (! $booking) {
            return response()->json([
                'success' => false,
                'message' => 'Book a PLYRLab trainer before syncing performance stats.',
                'data' => $dataService->payload($player),
            ], 422);
        }

        if (blank($booking->plyomat_athlete_id)) {
            ProvisionPlyrLabAthlete::dispatch($booking->getKey());

            return response()->json([
                'success' => true,
                'message' => 'Your Plyomat profile is still being prepared. We retried the connection in the background.',
                'data' => $dataService->payload($player),
            ]);
        }

        try {
            $dataService->syncStats($booking);
        } catch (Throwable $exception) {
            $booking->forceFill(['last_sync_error' => $exception->getMessage()])->save();

            return response()->json([
                'success' => false,
                'message' => 'We could not refresh Plyomat stats right now. Your last synced results are still available.',
                'data' => $dataService->payload($player),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'PLYRLab stats refreshed.',
            'data' => $dataService->payload($player),
        ]);
    }
}
