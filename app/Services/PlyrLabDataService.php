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
            ->whereIn('status', PlyrLabBooking::ACCESS_STATUSES)
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

        $connected = $booking
            && filled($booking->plyomat_athlete_id)
            && in_array($booking->status, ['active', 'completed'], true);

        return [
            'player' => $this->playerPayload($player),
            'access' => [
                'unlocked' => (bool) $connected,
                'has_booking' => (bool) $booking,
                'state' => $booking?->status ?? 'locked',
                'message' => $this->accessMessage($booking),
            ],
            'booking' => $booking ? $this->bookingPayload($booking) : null,
            'trainers' => $trainers,
            'stats' => $this->statsPayload($player, $booking),
        ];
    }

    /**
     * Return exact Plyomat measurements plus PLYRCARD-style 40-99 ratings.
     *
     * Ratings are cohort-relative (same sport/gender when available), while the
     * measured values remain untouched. This mirrors the athlete reference UI:
     * RATING is normalized; MEASURED is the exact Plyomat result.
     */
    protected function statsPayload(User $player, ?PlyrLabBooking $booking): ?array
    {
        if (! $booking || ! is_array($booking->plyomat_stats)) {
            return $booking?->plyomat_stats;
        }

        $stats = $booking->plyomat_stats;
        $sport = strtolower(trim((string) $player->sport));
        $gender = $this->normalizedGender($player->gender);

        $cohort = PlyrLabBooking::query()
            ->with('player')
            ->whereIn('status', PlyrLabBooking::ACCESS_STATUSES)
            ->whereNotNull('plyomat_stats')
            ->latest('id')
            ->get()
            ->unique('player_user_id')
            ->filter(function (PlyrLabBooking $peer) use ($sport, $gender): bool {
                if (! $peer->player || ! is_array($peer->plyomat_stats)) {
                    return false;
                }

                $peerSport = strtolower(trim((string) $peer->player->sport));
                $peerGender = $this->normalizedGender($peer->player->gender);

                $sportMatches = $sport === '' || $peerSport === '' || $peerSport === $sport;
                $genderMatches = $gender === '' || $peerGender === '' || $peerGender === $gender;

                return $sportMatches && $genderMatches;
            })
            ->values();

        $numberAt = static function (array $payload, string $path): ?float {
            $value = data_get($payload, $path);

            return is_numeric($value) ? (float) $value : null;
        };

        $metric = [
            'jump' => ['path' => 'jump.vertical_jump_cm', 'low' => false],
            'rsi' => ['path' => 'jump.rsi', 'low' => false],
            'bounce' => ['path' => 'jump.bounce_height_cm', 'low' => false],
            'quick' => ['path' => 'jump.ground_contact_ms', 'low' => true],
            'balance' => ['path' => 'jump.balance_gap_percent', 'low' => true],
            't10' => ['path' => 'speed.sprint_10m_seconds', 'low' => true],
            't20' => ['path' => 'speed.sprint_20m_seconds', 'low' => true],
            't30' => ['path' => 'speed.sprint_30m_seconds', 'low' => true],
            'vmax' => ['path' => 'speed.top_speed_kph', 'low' => false],
        ];

        $percentiles = [];
        foreach ($metric as $key => $definition) {
            $current = $numberAt($stats, $definition['path']);
            if ($current === null) {
                $percentiles[$key] = null;
                continue;
            }

            $pool = $cohort
                ->map(fn (PlyrLabBooking $peer): ?float => $numberAt($peer->plyomat_stats ?? [], $definition['path']))
                ->filter(fn (?float $value): bool => $value !== null)
                ->values();

            if ($pool->count() < 2) {
                // Same neutral starting percentile used by the supplied PLYRLab HTML.
                $percentiles[$key] = 50.0;
                continue;
            }

            $better = 0.0;
            $ties = 0;
            foreach ($pool as $candidate) {
                if (abs($candidate - $current) < 0.000001) {
                    $ties++;
                    continue;
                }

                if ($definition['low'] ? $candidate > $current : $candidate < $current) {
                    $better++;
                }
            }

            // One tie is the athlete themself; remaining ties count half.
            $otherTies = max(0, $ties - 1);
            $percentile = ($better + ($otherTies / 2)) / max(1, $pool->count() - 1) * 100;
            $percentiles[$key] = round(max(0, min(100, $percentile)), 1);
        }

        $rating = static fn (?float $percentile): ?int => $percentile === null
            ? null
            : (int) max(40, min(99, round(40 + ($percentile * 0.59))));

        $weightedPercentile = static function (array $weights, array $values): ?float {
            $total = 0.0;
            $weight = 0.0;

            foreach ($weights as $key => $w) {
                if (($values[$key] ?? null) === null) {
                    continue;
                }

                $total += $values[$key] * $w;
                $weight += $w;
            }

            return $weight > 0 ? $total / $weight : null;
        };

        $overallPercentile = $weightedPercentile([
            'rsi' => 0.35,
            'jump' => 0.30,
            'bounce' => 0.15,
            'quick' => 0.10,
            'balance' => 0.10,
        ], $percentiles);

        $paceValues = array_values(array_filter([
            $percentiles['t10'] ?? null,
            $percentiles['t20'] ?? null,
            $percentiles['t30'] ?? null,
            $percentiles['vmax'] ?? null,
        ], static fn ($value): bool => $value !== null));

        $pacePercentile = $paceValues === []
            ? null
            : array_sum($paceValues) / count($paceValues);

        $stats['ratings'] = [
            'overall' => $rating($overallPercentile),
            'pace' => $rating($pacePercentile),
            'jump' => $rating($percentiles['jump'] ?? null),
            'bounce' => $rating($percentiles['bounce'] ?? null),
            'rsi' => $rating($percentiles['rsi'] ?? null),
            'quick' => $rating($percentiles['quick'] ?? null),
            'balance' => $rating($percentiles['balance'] ?? null),
            't10' => $rating($percentiles['t10'] ?? null),
            't20' => $rating($percentiles['t20'] ?? null),
            't30' => $rating($percentiles['t30'] ?? null),
            'vmax' => $rating($percentiles['vmax'] ?? null),
            'percentiles' => $percentiles,
            'cohort_size' => $cohort->count(),
            'basis' => 'same sport and gender PLYRLab cohort',
        ];

        return $stats;
    }

    protected function normalizedGender(mixed $gender): string
    {
        $gender = strtolower(trim((string) $gender));

        return match ($gender) {
            'm', 'male', 'man', 'men', 'boy', 'boys' => 'men',
            'f', 'female', 'woman', 'women', 'girl', 'girls' => 'women',
            default => $gender,
        };
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
            'api_connected' => $trainer->hasPlyomatConnection(),
            'photo_url' => $this->publicAssetUrl($user?->player_image ?: $user?->plyrcard_image),
        ];
    }

    protected function bookingPayload(PlyrLabBooking $booking): array
    {
        $trainer = $booking->trainer;

        return [
            'id' => $booking->getKey(),
            'status' => $booking->status,
            'status_label' => match ($booking->status) {
                'provisioning', 'pending' => 'Connecting to Plyomat',
                'active' => 'Connected',
                'awaiting_athlete_id' => 'Existing Profile Detected',
                'awaiting_integration' => 'API Connection Required',
                'provisioning_failed' => 'Connection Needs Attention',
                'completed' => 'Connected',
                default => str($booking->status)->replace('_', ' ')->title()->toString(),
            },
            'scheduled_for' => $booking->scheduled_for?->toIso8601String(),
            'notes' => $booking->notes,
            'plyomat_ready' => filled($booking->plyomat_athlete_id),
            'plyomat_athlete_id' => $booking->plyomat_athlete_id,
            'last_synced_at' => $booking->plyomat_last_synced_at?->toIso8601String(),
            'connection_error' => $booking->last_sync_error,
            'trainer' => $trainer ? $this->trainerPayload($trainer) : null,
        ];
    }

    protected function accessMessage(?PlyrLabBooking $booking): string
    {
        if (! $booking) {
            return 'Book a PLYRLab trainer to unlock your personal performance dashboard.';
        }

        return match ($booking->status) {
            'active' => 'Your PLYRLab is connected to Plyomat and ready for performance data.',
            'provisioning', 'pending' => 'Trainer booked. PLYRCard is creating your Plyomat athlete profile automatically.',
            'awaiting_athlete_id' => 'An existing Plyomat athlete may already exist. Enter the Athlete ID from your trainer to connect that profile instead.',
            'awaiting_integration' => 'Your trainer\'s Plyomat API connection needs attention before PLYRCard can create or read athlete data.',
            'provisioning_failed' => 'Automatic Plyomat creation failed. You can connect an existing Athlete ID instead, or have an admin check the trainer API key.',
            'completed' => 'Your PLYRLab remains available with your saved performance results.',
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
