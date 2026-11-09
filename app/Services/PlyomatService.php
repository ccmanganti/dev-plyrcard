<?php

namespace App\Services;

use App\Models\PlyrLabBooking;
use App\Models\PlyrLabTrainer;
use App\Models\User;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class PlyomatService
{
    public function isConfigured(PlyrLabTrainer $trainer): bool
    {
        return $trainer->hasPlyomatConnection();
    }

    public function createAthlete(PlyrLabBooking $booking): array
    {
        $booking->loadMissing(['player.team', 'player.club', 'trainer']);
        $trainer = $booking->trainer;
        $player = $booking->player;

        if (! $trainer || ! $player || ! $this->isConfigured($trainer)) {
            throw new RuntimeException('Plyomat integration is not configured for this trainer.');
        }

        $payload = $this->playerPayload($player);
        $response = $this->request($trainer)
            ->post($this->endpoint($trainer, (string) $trainer->plyomat_create_athlete_path), $payload);

        if (! $response->successful()) {
            if ($response->status() === 403) {
                throw new RuntimeException('Plyomat denied Athlete Write access. Confirm this trainer API key includes the Athlete Write scope.');
            }

            throw new RuntimeException('Plyomat athlete write failed: HTTP ' . $response->status() . '.');
        }

        $json = $response->json();
        $athleteId = data_get($json, 'id')
            ?? data_get($json, 'athlete_id')
            ?? data_get($json, 'athleteId')
            ?? data_get($json, 'data.id')
            ?? data_get($json, 'data.athlete_id')
            ?? data_get($json, 'data.athleteId');

        if (blank($athleteId)) {
            throw new RuntimeException('Plyomat athlete creation succeeded but no athlete ID was returned.');
        }

        return [
            'athlete_id' => (string) $athleteId,
            'response' => is_array($json) ? $json : [],
        ];
    }

    public function readAthlete(PlyrLabBooking $booking): array
    {
        $booking->loadMissing('trainer');
        $trainer = $booking->trainer;

        if (! $trainer || blank($booking->plyomat_athlete_id)) {
            throw new RuntimeException('This player does not have a Plyomat athlete ID yet.');
        }

        $path = trim((string) $trainer->plyomat_stats_path);
        if ($path === '') {
            throw new RuntimeException('The Plyomat Athlete Read endpoint is not configured for this trainer.');
        }

        $path = str_replace(
            ['{athlete}', '{athlete_id}', '{id}'],
            rawurlencode((string) $booking->plyomat_athlete_id),
            $path,
        );

        $response = $this->request($trainer)->get($this->endpoint($trainer, $path));

        if (! $response->successful()) {
            if ($response->status() === 403) {
                throw new RuntimeException('Plyomat denied Athlete Read access. Confirm this trainer API key includes the Athlete Read scope.');
            }

            throw new RuntimeException('Plyomat athlete read failed: HTTP ' . $response->status() . '.');
        }

        $json = $response->json();

        return is_array($json) ? $json : [];
    }

    public function fetchStats(PlyrLabBooking $booking): array
    {
        return $this->normalizeStats($this->readAthlete($booking));
    }

    public function playerPayload(User $player): array
    {
        $position = is_array($player->position)
            ? array_values(array_filter(array_map('strval', $player->position)))
            : array_values(array_filter([trim((string) $player->position)]));

        return [
            'external_id' => 'plyrcard:' . $player->getKey(),
            'first_name' => (string) $player->first_name,
            'last_name' => (string) $player->last_name,
            'name' => trim((string) $player->first_name . ' ' . (string) $player->last_name),
            'email' => (string) ($player->personal_email ?: $player->email),
            'date_of_birth' => $player->birth ? (string) $player->birth : null,
            'gender' => $player->gender ?: null,
            'sport' => $player->sport ?: null,
            'team' => $player->team?->name ?: $player->team_name ?: $player->club?->name,
            'position' => $position,
            'height' => $player->height ?: null,
            'weight' => $player->weight ?: null,
        ];
    }

    public function normalizeStats(array $payload): array
    {
        $flat = Arr::dot($payload);
        $lookup = [];

        foreach ($flat as $key => $value) {
            if (! is_scalar($value) && $value !== null) {
                continue;
            }

            $normalized = preg_replace('/[^a-z0-9]+/', '', strtolower((string) $key)) ?: '';
            if ($normalized !== '' && ! array_key_exists($normalized, $lookup)) {
                $lookup[$normalized] = $value;
            }
        }

        $value = function (array $aliases) use ($lookup) {
            foreach ($aliases as $alias) {
                $needle = preg_replace('/[^a-z0-9]+/', '', strtolower($alias)) ?: '';
                if ($needle === '') {
                    continue;
                }

                foreach ($lookup as $key => $candidate) {
                    if ($key === $needle || str_ends_with($key, $needle)) {
                        return $candidate;
                    }
                }
            }

            return null;
        };

        $number = static function ($candidate): ?float {
            if ($candidate === null || $candidate === '' || ! is_numeric($candidate)) {
                return null;
            }

            return (float) $candidate;
        };

        $left = $number($value(['left_jump_cm', 'left_jump_height', 'left_cm', 'single_leg_left_cm', 'left']));
        $right = $number($value(['right_jump_cm', 'right_jump_height', 'right_cm', 'single_leg_right_cm', 'right']));
        $gap = $number($value(['balance_gap_percent', 'left_right_diff_percent', 'asymmetry_percent', 'symmetry_gap_percent']));

        if ($gap === null && $left !== null && $right !== null && max($left, $right) > 0) {
            $gap = round(abs($left - $right) / max($left, $right) * 100, 1);
        }

        return [
            'source' => 'plyomat',
            'overall_score' => $number($value(['overall_score', 'overall_rating', 'ovr'])),
            'pace_score' => $number($value(['pace_score', 'pace_rating', 'pac'])),
            'jump_profile' => $value(['jump_profile', 'reactive_strength_profile', 'profile']),
            'latest_test_at' => $value(['last_tested_on', 'latest_test_at', 'tested_at', 'test_date', 'date']),
            'jump' => [
                'vertical_jump_cm' => $number($value(['vertical_jump_cm', 'vertical_jump', 'jump_height_cm', 'standing_vertical_jump_cm', 'svj'])),
                'rsi' => $number($value(['reactive_strength_index', 'rsi'])),
                'bounce_height_cm' => $number($value(['bounce_height_cm', 'bounce_height', 'repeated_hop_height_cm', 'bounce'])),
                'ground_contact_ms' => $number($value(['ground_contact_ms', 'ground_contact_time_ms', 'contact_time_ms', 'gct'])),
                'left_jump_cm' => $left,
                'right_jump_cm' => $right,
                'balance_gap_percent' => $gap,
            ],
            'speed' => [
                'sprint_10m_seconds' => $number($value(['sprint_10m_seconds', '10m_seconds', 't10', '10m'])),
                'sprint_20m_seconds' => $number($value(['sprint_20m_seconds', '20m_seconds', 't20', '20m'])),
                'sprint_30m_seconds' => $number($value(['sprint_30m_seconds', '30m_seconds', 't30', '30m'])),
                'top_speed_kph' => $number($value(['top_speed_kph', 'max_speed_kph', 'vmax_kph', 'vmax', 'top_speed'])),
            ],
        ];
    }

    protected function request(PlyrLabTrainer $trainer): PendingRequest
    {
        $header = trim((string) ($trainer->plyomat_auth_header ?: 'Authorization'));
        $prefix = trim((string) ($trainer->plyomat_auth_prefix ?: ''));
        $key = trim((string) $trainer->plyomat_api_key);
        $credential = trim(($prefix !== '' ? $prefix . ' ' : '') . $key);

        return Http::acceptJson()
            ->asJson()
            ->timeout(25)
            ->retry(2, 350)
            ->withHeaders([
                $header => $credential,
                'X-PLYRCARD-Integration' => 'PLYRLab',
            ]);
    }

    protected function endpoint(PlyrLabTrainer $trainer, string $path): string
    {
        $base = rtrim(trim((string) $trainer->plyomat_base_url), '/');
        $path = trim($path);

        if (Str::startsWith($path, ['http://', 'https://'])) {
            return $path;
        }

        return $base . '/' . ltrim($path, '/');
    }
}
