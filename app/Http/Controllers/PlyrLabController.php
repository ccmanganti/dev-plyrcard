<?php

namespace App\Http\Controllers;

use App\Jobs\ProvisionPlyrLabAthlete;
use App\Models\PlyrLabBooking;
use App\Models\PlyrLabTrainer;
use App\Notifications\PlyrLabTrainerBookedNotification;
use App\Services\PlyrLabDataService;
use App\Services\PlyomatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class PlyrLabController extends Controller
{
    public function index(Request $request, PlyrLabDataService $dataService, PlyomatService $plyomat): JsonResponse
    {
        $player = $request->user();
        abort_unless($player, 403);

        $booking = PlyrLabBooking::query()
            ->with('trainer')
            ->where('player_user_id', $player->getKey())
            ->whereIn('status', PlyrLabBooking::ACCESS_STATUSES)
            ->latest('id')
            ->first();

        if ($booking) {
            $canProvision = $booking->trainer?->hasPlyomatConnection() === true;

            if (
                blank($booking->plyomat_athlete_id)
                && (
                    $booking->status === 'provisioning'
                    || ($booking->status === 'awaiting_integration' && $canProvision)
                )
            ) {
                // Self-heal both interrupted provisioning and bookings that were
                // waiting for a trainer to connect an API key.
                $booking->forceFill([
                    'status' => 'provisioning',
                    'last_sync_error' => null,
                ])->save();

                ProvisionPlyrLabAthlete::dispatchSync($booking->getKey());
                $booking = $booking->fresh(['trainer']);
            } elseif (
                filled($booking->plyomat_athlete_id)
                && (
                    ! is_array($booking->plyomat_stats)
                    || ! $plyomat->hasPerformanceMeasurements($booking->plyomat_stats)
                )
                && (
                    ! $booking->plyomat_last_synced_at
                    || $booking->plyomat_last_synced_at->lt(now()->subMinute())
                )
            ) {
                // Existing/manual connections created before the performance
                // endpoint fix may have an Athlete ID with empty normalized data.
                // Retry synchronously on open so they recover without reconnecting.
                ProvisionPlyrLabAthlete::dispatchSync($booking->getKey());
                $booking = $booking->fresh(['trainer']);
            } elseif (
                filled($booking->plyomat_athlete_id)
                && (! $booking->plyomat_last_synced_at || $booking->plyomat_last_synced_at->lt(now()->subMinutes(10)))
            ) {
                ProvisionPlyrLabAthlete::dispatch($booking->getKey());
            }
        }

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

        $previousConnection = PlyrLabBooking::query()
            ->where('player_user_id', $player->getKey())
            ->where('trainer_id', $trainer->getKey())
            ->where('status', 'cancelled')
            ->whereNotNull('plyomat_athlete_id')
            ->latest('id')
            ->first();

        $booking = DB::transaction(function () use ($player, $trainer, $validated, $previousConnection): PlyrLabBooking {
            return PlyrLabBooking::query()->create([
                'player_user_id' => $player->getKey(),
                'trainer_id' => $trainer->getKey(),
                'scheduled_for' => $validated['scheduled_for'],
                'status' => 'provisioning',
                'notes' => $validated['notes'] ?? null,
                'plyomat_athlete_id' => $previousConnection?->plyomat_athlete_id,
            ]);
        });

        if ($trainer->user) {
            try {
                $trainer->user->notify(new PlyrLabTrainerBookedNotification($booking->getKey()));
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        // The initial Plyomat creation is intentionally synchronous here.
        // This makes booking reliable even when a queue worker is not running.
        // The frontend immediately renders a "Connecting to Plyomat" state while
        // this request is in flight.
        ProvisionPlyrLabAthlete::dispatchSync($booking->getKey());

        $booking = $booking->fresh(['trainer.user']) ?? $booking;

        $message = match ($booking->status) {
            'active' => 'Trainer booked and your Plyomat athlete profile is connected.',
            'awaiting_athlete_id' => 'Trainer booked. Plyomat reports an existing profile, so enter the existing Athlete ID to connect it.',
            'awaiting_integration' => 'Trainer booked. We are waiting for your trainer to connect Plyomat. Your booking is saved—come back soon or cancel it from PLYRLab.',
            'provisioning_failed' => 'Trainer booked, but automatic Plyomat creation could not complete. You can connect an existing Athlete ID instead.',
            default => 'Trainer booked. PLYRCard is connecting your Plyomat athlete profile.',
        };

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $dataService->payload($player),
        ]);
    }

    public function connect(Request $request, PlyrLabDataService $dataService, PlyomatService $plyomat): JsonResponse
    {
        $player = $request->user();
        abort_unless($player, 403);

        $validated = $request->validate([
            'athlete_id' => ['required', 'string', 'max:255'],
        ]);

        $athleteId = trim((string) $validated['athlete_id']);

        $booking = PlyrLabBooking::query()
            ->with(['trainer.user'])
            ->where('player_user_id', $player->getKey())
            ->whereIn('status', PlyrLabBooking::ACCESS_STATUSES)
            ->latest('id')
            ->first();

        if (! $booking) {
            return response()->json([
                'success' => false,
                'message' => 'Book a PLYRLab trainer before connecting a Plyomat Athlete ID.',
                'data' => $dataService->payload($player),
            ], 422);
        }

        if (! $booking->trainer || ! $plyomat->isConfigured($booking->trainer)) {
            return response()->json([
                'success' => false,
                'message' => 'Your trainer\'s Plyomat Athlete Read connection is not configured yet.',
                'data' => $dataService->payload($player),
            ], 422);
        }

        $previousId = $booking->plyomat_athlete_id;
        $previousStatus = $booking->status;

        try {
            // Verify the ID before persisting it. readAthlete() uses the booked
            // trainer's API credentials, so a successful response proves that
            // this trainer can access the submitted athlete.
            $booking->setAttribute('plyomat_athlete_id', $athleteId);
            $athlete = $plyomat->readAthlete($booking);

            if ($athlete === []) {
                throw new \RuntimeException('Plyomat returned an empty athlete record for that ID.');
            }

            $canonicalAthleteId = $plyomat->athleteIdentifier($athlete) ?: $athleteId;

            // If the player entered an external/short identifier, switch the
            // booking to Plyomat's canonical primary UUID before requesting
            // sessions/results.
            $booking->setAttribute('plyomat_athlete_id', $canonicalAthleteId);

            // The athlete profile proves the ID is valid. Pull performance
            // data separately because Plyomat roster responses may not contain
            // sessions/assessment measurements.
            $stats = $plyomat->fetchStats($booking);
            $hasMeasurements = $plyomat->hasPerformanceMeasurements($stats);

            $booking->forceFill([
                'status' => 'active',
                'plyomat_athlete_id' => $canonicalAthleteId,
                'plyomat_provisioned_at' => now(),
                'plyomat_last_synced_at' => now(),
                'plyomat_stats' => $stats,
                'last_sync_error' => $hasMeasurements
                    ? null
                    : $plyomat->performanceSyncMessage($stats),
            ])->save();
        } catch (Throwable $exception) {
            $booking->forceFill([
                'status' => filled($previousId) ? $previousStatus : 'awaiting_athlete_id',
                'plyomat_athlete_id' => $previousId,
                'last_sync_error' => $exception->getMessage(),
            ])->save();

            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
                'data' => $dataService->payload($player),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Plyomat profile connected. Your available performance data has been synced.',
            'data' => $dataService->payload($player),
        ]);
    }

    public function updateBooking(Request $request, PlyrLabDataService $dataService): JsonResponse
    {
        $player = $request->user();
        abort_unless($player, 403);

        $validated = $request->validate([
            'scheduled_for' => ['required', 'date', 'after:now'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $booking = PlyrLabBooking::query()
            ->where('player_user_id', $player->getKey())
            ->whereIn('status', PlyrLabBooking::OPEN_STATUSES)
            ->latest('id')
            ->first();

        if (! $booking) {
            return response()->json([
                'success' => false,
                'message' => 'There is no active PLYRLab booking to update.',
                'data' => $dataService->payload($player),
            ], 422);
        }

        $booking->forceFill([
            'scheduled_for' => $validated['scheduled_for'],
            'notes' => $validated['notes'] ?? null,
        ])->save();

        return response()->json([
            'success' => true,
            'message' => 'Your PLYRLab trainer booking has been updated.',
            'data' => $dataService->payload($player),
        ]);
    }

    public function cancelBooking(Request $request, PlyrLabDataService $dataService): JsonResponse
    {
        $player = $request->user();
        abort_unless($player, 403);

        $booking = PlyrLabBooking::query()
            ->where('player_user_id', $player->getKey())
            ->whereIn('status', PlyrLabBooking::OPEN_STATUSES)
            ->latest('id')
            ->first();

        if (! $booking) {
            return response()->json([
                'success' => false,
                'message' => 'There is no active PLYRLab booking to cancel.',
                'data' => $dataService->payload($player),
            ], 422);
        }

        $booking->forceFill([
            'status' => 'cancelled',
            'last_sync_error' => null,
        ])->save();

        return response()->json([
            'success' => true,
            'message' => 'Your PLYRLab trainer booking has been cancelled. You can choose another trainer whenever you are ready.',
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
            return response()->json([
                'success' => false,
                'message' => 'Enter the Plyomat Athlete ID provided by your trainer before refreshing stats.',
                'data' => $dataService->payload($player),
            ], 422);
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
