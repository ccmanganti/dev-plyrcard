<?php

namespace App\Services;

use App\Models\PlyrLabBooking;
use App\Models\PlyrLabTrainer;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class PlyomatService
{
    public function isConfigured(PlyrLabTrainer $trainer): bool
    {
        return $trainer->hasPlyomatConnection();
    }

    public function readAthlete(PlyrLabBooking $booking): array
    {
        $booking->loadMissing('trainer');
        $trainer = $booking->trainer;

        if (! $trainer || blank($booking->plyomat_athlete_id)) {
            throw new RuntimeException('This player does not have a Plyomat athlete ID yet.');
        }

        $athleteId = trim((string) $booking->plyomat_athlete_id);
        $attempts = [];
        $lastAuthStatus = null;

        foreach ($this->endpointCandidates($trainer, $athleteId) as $candidate) {
            foreach ($this->authCandidates($trainer) as $auth) {
                $response = $this->request($trainer, $auth)->get($candidate['url']);
                $contentType = strtolower((string) $response->header('Content-Type'));

                $attempts[] = [
                    'url' => $candidate['url'],
                    'status' => $response->status(),
                    'content_type' => $contentType,
                    'auth' => $auth['label'],
                ];

                if (in_array($response->status(), [401, 403], true)) {
                    $lastAuthStatus = $response->status();
                    continue;
                }

                if (in_array($response->status(), [404, 405], true)) {
                    break;
                }

                if (! $response->successful()) {
                    continue;
                }

                $json = $response->json();

                if (! is_array($json)) {
                    // A 200 HTML response means we hit the Plyomat web app/SPA,
                    // not the developer API. Keep probing the read-only API candidates.
                    continue;
                }

                $athlete = $this->extractAthletePayload($json, $athleteId);

                if ($athlete !== null) {
                    $this->rememberWorkingConnection($trainer, $candidate, $auth);

                    return $athlete;
                }
            }
        }

        Log::warning('PLYRLab could not resolve Plyomat Athlete Read endpoint.', [
            'trainer_id' => $trainer->getKey(),
            'athlete_id' => $athleteId,
            'attempts' => $attempts,
        ]);

        if ($lastAuthStatus === 401) {
            throw new RuntimeException('The trainer\'s Plyomat API key was rejected. Check that the key is valid and has Athlete Read access.');
        }

        if ($lastAuthStatus === 403) {
            throw new RuntimeException('Plyomat denied Athlete Read access. Check the API key scope for this trainer.');
        }

        throw new RuntimeException('PLYRCard could not find a valid Plyomat Athlete Read response for that ID. The endpoint returned a web/empty response instead of athlete data.');
    }

    protected function endpointCandidates(PlyrLabTrainer $trainer, string $athleteId): array
    {
        $legacyBase = trim((string) $trainer->plyomat_base_url);
        $configuredBase = trim((string) config('services.plyomat.base_url', 'https://app.plyomat.com'));

        $bases = array_values(array_unique(array_filter([
            $legacyBase,
            $configuredBase,
            'https://app.plyomat.com',
            'https://api.plyomat.com',
        ])));

        $paths = array_values(array_unique(array_filter([
            trim((string) $trainer->plyomat_stats_path),
            trim((string) config('services.plyomat.athlete_read_path')),
            '/api/v1/athletes/{athlete_id}',
            '/api/v1/athlete/{athlete_id}',
            '/api/athletes/{athlete_id}',
            '/api/athlete/{athlete_id}',
        ])));

        $candidates = [];

        foreach ($paths as $path) {
            $resolvedPath = str_replace(
                ['{athlete}', '{athlete_id}', '{id}'],
                rawurlencode($athleteId),
                $path,
            );

            if (Str::startsWith($resolvedPath, ['http://', 'https://'])) {
                $candidates[$resolvedPath] = [
                    'url' => $resolvedPath,
                    'base_url' => Str::beforeLast($resolvedPath, '/'),
                    'path' => $path,
                ];

                continue;
            }

            foreach ($bases as $base) {
                $url = rtrim($base, '/') . '/' . ltrim($resolvedPath, '/');

                $candidates[$url] = [
                    'url' => $url,
                    'base_url' => rtrim($base, '/'),
                    'path' => $path,
                ];
            }
        }

        return array_values($candidates);
    }

    protected function authCandidates(PlyrLabTrainer $trainer): array
    {
        $configuredHeader = trim((string) config('services.plyomat.auth_header', 'Authorization'));
        $configuredPrefix = trim((string) config('services.plyomat.auth_prefix', 'Bearer'));

        return [
            [
                'label' => 'configured',
                'header' => $configuredHeader,
                'prefix' => $configuredPrefix,
            ],
            [
                'label' => 'authorization_raw',
                'header' => 'Authorization',
                'prefix' => '',
            ],
            [
                'label' => 'x_api_key',
                'header' => 'X-API-Key',
                'prefix' => '',
            ],
            [
                'label' => 'api_key',
                'header' => 'API-Key',
                'prefix' => '',
            ],
        ];
    }

    protected function extractAthletePayload(array $payload, string $athleteId): ?array
    {
        $candidates = [
            $payload,
            data_get($payload, 'data'),
            data_get($payload, 'athlete'),
            data_get($payload, 'result'),
            data_get($payload, 'data.athlete'),
            data_get($payload, 'result.athlete'),
        ];

        foreach ($candidates as $candidate) {
            if (! is_array($candidate) || $candidate === []) {
                continue;
            }

            if (array_is_list($candidate)) {
                $match = collect($candidate)->first(function ($row) use ($athleteId): bool {
                    if (! is_array($row)) {
                        return false;
                    }

                    $id = data_get($row, 'id')
                        ?? data_get($row, 'athlete_id')
                        ?? data_get($row, 'athleteId')
                        ?? data_get($row, 'uuid');

                    return $id !== null && (string) $id === $athleteId;
                });

                if (is_array($match)) {
                    return $match;
                }

                if (count($candidate) === 1 && is_array($candidate[0])) {
                    return $candidate[0];
                }

                continue;
            }

            $hasAthleteShape = collect([
                'id',
                'athlete_id',
                'athleteId',
                'uuid',
                'first_name',
                'firstName',
                'last_name',
                'lastName',
                'name',
                'email',
                'assessments',
                'sessions',
                'results',
            ])->contains(fn (string $key): bool => data_get($candidate, $key) !== null);

            if ($hasAthleteShape) {
                return $candidate;
            }
        }

        return null;
    }

    protected function rememberWorkingConnection(PlyrLabTrainer $trainer, array $candidate, array $auth): void
    {
        $trainer->forceFill([
            'plyomat_base_url' => $candidate['base_url'] ?? null,
            'plyomat_stats_path' => $candidate['path'] ?? null,
            'plyomat_auth_header' => $auth['header'] ?? 'Authorization',
            'plyomat_auth_prefix' => $auth['prefix'] ?? '',
        ])->saveQuietly();
    }

    public function fetchStats(PlyrLabBooking $booking): array
    {
        return $this->normalizeStats($this->readAthlete($booking));
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

    protected function request(PlyrLabTrainer $trainer, ?array $auth = null): PendingRequest
    {
        $auth ??= [
            'header' => trim((string) ($trainer->plyomat_auth_header ?: config('services.plyomat.auth_header', 'Authorization'))),
            'prefix' => trim((string) ($trainer->plyomat_auth_prefix ?? config('services.plyomat.auth_prefix', 'Bearer'))),
        ];

        $header = trim((string) ($auth['header'] ?? 'Authorization'));
        $prefix = trim((string) ($auth['prefix'] ?? ''));
        $key = trim((string) $trainer->plyomat_api_key);
        $credential = trim(($prefix !== '' ? $prefix . ' ' : '') . $key);

        return Http::acceptJson()
            ->asJson()
            ->timeout(25)
            ->retry(1, 250)
            ->withHeaders([
                $header => $credential,
                'X-PLYRCARD-Integration' => 'PLYRLab',
            ]);
    }
}
