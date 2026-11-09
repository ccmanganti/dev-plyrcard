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
            ->with(['trainer'])
            ->find($this->bookingId);

        if (! $booking || ! in_array($booking->status, PlyrLabBooking::ACCESS_STATUSES, true)) {
            return;
        }

        if (blank($booking->plyomat_athlete_id)) {
            return;
        }

        $trainer = $booking->trainer;

        if (! $trainer || ! $plyomat->isConfigured($trainer)) {
            $booking->forceFill([
                'status' => 'awaiting_integration',
                'last_sync_error' => 'The trainer\'s Plyomat Athlete Read connection is not configured.',
            ])->save();

            return;
        }

        $this->syncStatsWhenAvailable($booking, $plyomat);
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
