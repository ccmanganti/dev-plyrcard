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
            ->with(['player.team', 'player.club', 'trainer'])
            ->find($this->bookingId);

        if (! $booking || ! in_array($booking->status, PlyrLabBooking::ACCESS_STATUSES, true)) {
            return;
        }

        $trainer = $booking->trainer;

        if (! $trainer || ! $plyomat->isConfigured($trainer)) {
            $booking->forceFill([
                'status' => 'awaiting_integration',
                'last_sync_error' => 'Plyomat API credentials or athlete-creation endpoint are not configured for this trainer yet.',
            ])->save();

            return;
        }

        if (filled($booking->plyomat_athlete_id)) {
            $this->syncStatsWhenAvailable($booking, $plyomat);
            return;
        }

        $booking->forceFill([
            'status' => 'provisioning',
            'last_sync_error' => null,
        ])->save();

        $result = $plyomat->createAthlete($booking);

        $booking->forceFill([
            'status' => 'active',
            'plyomat_athlete_id' => (string) $result['athlete_id'],
            'plyomat_provisioned_at' => now(),
            'last_sync_error' => null,
        ])->save();

        $this->syncStatsWhenAvailable($booking->fresh(['trainer']) ?? $booking, $plyomat);

        Log::info('PLYRLab athlete provisioned in Plyomat.', [
            'booking_id' => $booking->getKey(),
            'player_user_id' => $booking->player_user_id,
            'trainer_id' => $booking->trainer_id,
            'plyomat_athlete_id' => $booking->plyomat_athlete_id,
        ]);
    }

    protected function syncStatsWhenAvailable(PlyrLabBooking $booking, PlyomatService $plyomat): void
    {
        if (blank($booking->trainer?->plyomat_stats_path) || blank($booking->plyomat_athlete_id)) {
            return;
        }

        try {
            $stats = $plyomat->fetchStats($booking);

            if ($stats !== []) {
                $booking->forceFill([
                    'plyomat_stats' => $stats,
                    'plyomat_last_synced_at' => now(),
                    'last_sync_error' => null,
                ])->save();
            }
        } catch (Throwable $exception) {
            $booking->forceFill([
                'last_sync_error' => $exception->getMessage(),
            ])->save();

            Log::warning('PLYRLab initial Plyomat stats sync failed after provisioning.', [
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
                'status' => 'provisioning_failed',
                'last_sync_error' => $exception->getMessage(),
            ])->save();
        }

        Log::error('PLYRLab Plyomat provisioning failed.', [
            'booking_id' => $this->bookingId,
            'error' => $exception->getMessage(),
        ]);
    }
}
