<?php

namespace App\Services;

use App\Models\PlyrLabBooking;
use App\Models\PlyrLabTrainer;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

class PlyrLabDataService
{
    public function __construct(
        protected PlyomatService $plyomat,
    ) {}

    public function payload(User $player): array
    {
        $player->loadMissing(['team', 'club', 'school']);

        $booking = PlyrLabBooking::query()
            ->with(['trainer.user'])
            ->where('player_user_id', $player->getKey())
            ->whereIn('status', PlyrLabBooking::OPEN_STATUSES)
            ->latest('id')
            ->first();

        $trainers = PlyrLabTrainer::query()
            ->with('user')
            ->where('is_active', true)
            ->orderBy('display_name')
            ->get()
            ->map(fn (PlyrLabTrainer $trainer): array => $this->trainerPayload($trainer))
            ->values()
            ->all();

        return [
            'player' => $this->playerPayload($player),
            'access' => [
                'unlocked' => (bool) $booking,
                'state' => $booking?->status ?? 'locked',
                'message' => $this->accessMessage($booking),
            ],
            'booking' => $booking ? $this->bookingPayload($booking) : null,
            'trainers' => $trainers,
            'stats' => $booking?->plyomat_stats,
        ];
    }

    public function syncStats(PlyrLabBooking $booking): PlyrLabBooking
    {
        if (blank($booking->plyomat_athlete_id)) {
            return $booking;
        }

        $stats = $this->plyomat->fetchStats($booking);

        if ($stats !== []) {
            $booking->forceFill([
                'plyomat_stats' => $stats,
                'plyomat_last_synced_at' => now(),
                'last_sync_error' => null,
            ])->save();
        }

        return $booking->fresh(['trainer.user']) ?? $booking;
    }

    public function playerPayload(User $player): array
    {
        $position = is_array($player->position)
            ? array_values(array_filter(array_map('strval', $player->position)))
            : array_values(array_filter([trim((string) $player->position)]));

        return [
            'id' => $player->getKey(),
            'first_name' => (string) $player->first_name,
            'last_name' => (string) $player->last_name,
            'name' => trim((string) $player->first_name . ' ' . (string) $player->last_name),
            'email' => (string) ($player->personal_email ?: $player->email),
            'gender' => $player->gender,
            'birth' => $player->birth,
            'year' => $player->year,
            'sport' => $player->sport,
            'position' => $position,
            'team' => $player->team?->name ?: $player->team_name ?: $player->club?->name ?: $player->school?->name,
            'height' => $player->height,
            'weight' => $player->weight,
            'jersey_number' => $player->jersey_number,
            'photo_url' => $this->publicAssetUrl($player->player_image ?: $player->action_image ?: $player->plyrcard_image),
        ];
    }

    protected function trainerPayload(PlyrLabTrainer $trainer): array
    {
        $user = $trainer->user;

        return [
            'id' => $trainer->getKey(),
            'name' => $trainer->displayName(),
            'headline' => $trainer->headline ?: 'PLYRLab Performance Trainer',
            'location' => $trainer->location,
            'specialties' => array_values(array_filter($trainer->specialties ?? [])),
            'bio' => $trainer->bio,
            'duration_minutes' => $trainer->booking_duration_minutes,
            'photo_url' => $this->publicAssetUrl($user?->player_image ?: $user?->plyrcard_image),
        ];
    }

    protected function bookingPayload(PlyrLabBooking $booking): array
    {
        $trainer = $booking->trainer;

        return [
            'id' => $booking->getKey(),
            'status' => $booking->status,
            'status_label' => str($booking->status)->replace('_', ' ')->title()->toString(),
            'scheduled_for' => $booking->scheduled_for?->toIso8601String(),
            'notes' => $booking->notes,
            'plyomat_ready' => filled($booking->plyomat_athlete_id),
            'last_synced_at' => $booking->plyomat_last_synced_at?->toIso8601String(),
            'last_sync_error' => $booking->last_sync_error,
            'trainer' => $trainer ? $this->trainerPayload($trainer) : null,
        ];
    }

    protected function accessMessage(?PlyrLabBooking $booking): string
    {
        if (! $booking) {
            return 'Book a PLYRLab trainer to unlock your personal performance dashboard.';
        }

        return match ($booking->status) {
            'active' => 'Your PLYRLab is connected to your trainer and ready for performance data.',
            'awaiting_integration' => 'Trainer booked. Your Plyomat profile is being connected.',
            'provisioning', 'pending' => 'Trainer booked. We are preparing your Plyomat athlete profile.',
            default => 'Your PLYRLab access is active.',
        };
    }

    protected function publicAssetUrl(?string $path): ?string
    {
        $path = trim((string) $path);

        if ($path === '') {
            return null;
        }

        if (filter_var($path, FILTER_VALIDATE_URL)) {
            return $path;
        }

        return Storage::disk('public')->url($path);
    }
}
