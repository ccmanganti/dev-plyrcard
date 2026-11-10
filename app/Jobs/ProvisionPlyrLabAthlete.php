<?php

namespace App\Jobs;

use App\Models\PlyrLabBooking;
use App\Services\PlyomatService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProvisionPlyrLabAthlete implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;
    public int $timeout = 120;

    public function __construct(public int $bookingId)
    {
        $this->onQueue('default');
    }

    public function uniqueId(): string
    {
        return 'plyrlab-booking:' . $this->bookingId;
    }

    public function backoff(): array
    {
        return [10, 30, 90, 180];
    }

    public function handle(PlyomatService $plyomat): void
    {
        $booking = PlyrLabBooking::query()
            ->with(['trainer', 'player'])
            ->find($this->bookingId);

        if (! $booking || ! in_array($booking->status, PlyrLabBooking::ACCESS_STATUSES, true)) {
            return;
        }

        $trainer = $booking->trainer;

        if (! $trainer || ! $plyomat->isConfigured($trainer)) {
            $booking->forceFill([
                'status' => 'awaiting_integration',
                'last_sync_error' => 'The trainer\'s Plyomat API key is not configured.',
            ])->save();

            return;
        }

        if (blank($booking->plyomat_athlete_id)) {
            $booking->forceFill([
                'status' => 'provisioning',
                'last_sync_error' => null,
            ])->save();

            try {
                $athlete = $plyomat->createAthlete($booking);
                $athleteId = $plyomat->athleteIdentifier($athlete);

                if (blank($athleteId)) {
                    throw new \RuntimeException('Plyomat did not return an athlete ID after creation.');
                }

                $booking->forceFill([
                    'status' => 'active',
                    'plyomat_athlete_id' => $athleteId,
                    'plyomat_provisioned_at' => now(),
                    'plyomat_last_synced_at' => now(),
                    'plyomat_stats' => $plyomat->normalizeStats($athlete),
                    'last_sync_error' => null,
                ])->save();
            } catch (Throwable $exception) {
                $error = strtolower($exception->getMessage());
                $duplicate = str_contains($error, 'already exists');
                $trainerConnectionIssue = str_contains($error, 'bearer token')
                    || str_contains($error, 'api key')
                    || str_contains($error, 'athlete write access')
                    || str_contains($error, 'athlete read access')
                    || str_contains($error, 'http 401')
                    || str_contains($error, 'http 403');

                $booking->forceFill([
                    'status' => $duplicate
                        ? 'awaiting_athlete_id'
                        : ($trainerConnectionIssue ? 'awaiting_integration' : 'provisioning_failed'),
                    'last_sync_error' => $exception->getMessage(),
                ])->save();

                Log::warning('PLYRLab Plyomat athlete creation failed.', [
                    'booking_id' => $booking->getKey(),
                    'trainer_id' => $trainer->getKey(),
                    'error' => $exception->getMessage(),
                ]);

                return;
            }
        }

        $booking = $booking->fresh(['trainer']) ?? $booking;

        $this->syncStatsWhenAvailable($booking, $plyomat);
    }

    protected function syncStatsWhenAvailable(PlyrLabBooking $booking, PlyomatService $plyomat): void
    {
        if (! $booking->trainer || ! $plyomat->isConfigured($booking->trainer) || blank($booking->plyomat_athlete_id)) {
            return;
        }

        try {
            $stats = $plyomat->fetchStats($booking);
            $hasMeasurements = $plyomat->hasPerformanceMeasurements($stats);

            $updates = [
                'plyomat_last_synced_at' => now(),
                'plyomat_stats' => $stats,
                'last_sync_error' => $hasMeasurements
                    ? null
                    : 'Athlete ID is connected, but Plyomat did not return supported session/assessment measurements for this athlete yet.',
            ];

            if (in_array($booking->status, ['pending', 'provisioning', 'awaiting_athlete_id', 'awaiting_integration', 'provisioning_failed'], true)) {
                $updates['status'] = 'active';
                $updates['plyomat_provisioned_at'] = $booking->plyomat_provisioned_at ?: now();
            }

            $booking->forceFill($updates)->save();
        } catch (Throwable $exception) {
            $booking->forceFill([
                'last_sync_error' => $exception->getMessage(),
            ])->save();

            Log::warning('PLYRLab Plyomat stats sync failed.', [
                'booking_id' => $booking->getKey(),
                'error' => $exception->getMessage(),
            ]);
        }
    }

    public function failed(Throwable $exception): void
    {
        $booking = PlyrLabBooking::query()->find($this->bookingId);

        if ($booking && $booking->isOpen()) {
            $booking->forceFill([
                'last_sync_error' => $exception->getMessage(),
            ])->save();
        }

        Log::error('PLYRLab Plyomat background sync failed.', [
            'booking_id' => $this->bookingId,
            'error' => $exception->getMessage(),
        ]);
    }
}
