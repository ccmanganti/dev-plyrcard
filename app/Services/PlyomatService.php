<?php

namespace App\Services;

use App\Models\PlyrLabBooking;
use App\Models\PlyrLabTrainer;
use App\Models\User;
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
        return $trainer->hasPlyomatApiKey();
    }

    public function verifyConnection(PlyrLabTrainer $trainer): array
    {
        if (! $trainer->hasPlyomatApiKey()) {
            throw new RuntimeException('Enter a Plyomat API key first.');
        }

        $base = rtrim((string) config('services.plyomat.base_url', 'https://api.plyomat.com'), '/');
        $path = trim((string) config('services.plyomat.athlete_read_path', '/v1/athletes'));
        $url = $base . '/' . ltrim($path, '/');

        $auth = [
            'header' => 'Authorization',
            'prefix' => 'Bearer',
        ];

        $response = $this->request($trainer, $auth)->get($url);

        if ($response->status() === 401) {
            throw new RuntimeException('Plyomat rejected this Bearer token.');
        }

        if ($response->status() === 403) {
            throw new RuntimeException('This Plyomat token does not have Athlete Read access.');
        }

        if (! $response->successful()) {
            throw new RuntimeException('Plyomat API verification failed: HTTP ' . $response->status() . '.');
        }

        $json = $response->json();

        if (! is_array($json)) {
            throw new RuntimeException('Plyomat returned a non-JSON response while verifying the API token.');
        }

        $this->rememberWorkingConnection($trainer, [
            'base_url' => $base,
            'path' => $path,
        ], $auth);

        return $json;
    }

    public function createAthlete(PlyrLabBooking $booking): array
    {
        $booking->loadMissing(['trainer', 'player']);
        $trainer = $booking->trainer;
        $player = $booking->player;

        if (! $trainer || ! $player) {
            throw new RuntimeException('PLYRLab booking is missing its trainer or player.');
        }

        if (! $this->isConfigured($trainer)) {
            throw new RuntimeException('The trainer\'s Plyomat API key is not configured.');
        }

        $path = trim((string) config('services.plyomat.athlete_create_path', '/v1/athletes'));
        $url = rtrim((string) config('services.plyomat.base_url', 'https://api.plyomat.com'), '/')
            . '/' . ltrim($path, '/');

        $response = $this->request($trainer, [
            'header' => 'Authorization',
            'prefix' => 'Bearer',
        ])->post($url, $this->athleteCreatePayload($player));

        if (! $response->successful()) {
            $json = $response->json();
            $message = is_array($json)
                ? $this->diagnosticText(
                    data_get($json, 'message')
                        ?? data_get($json, 'detail')
                        ?? data_get($json, 'error')
                )
                : null;

            if ($response->status() === 401) {
                throw new RuntimeException('Plyomat rejected the trainer\'s Bearer token. Confirm the API key is valid.');
            }

            if ($response->status() === 403) {
                throw new RuntimeException('The trainer\'s Plyomat API key does not have Athlete Write access.');
            }

            if ($response->status() === 409) {
                throw new RuntimeException('Plyomat reports that this athlete already exists. Use the existing Athlete ID to connect the profile.');
            }

            throw new RuntimeException(
                'Plyomat athlete creation failed: HTTP ' . $response->status()
                . ($message ? ' · ' . Str::limit($message, 180) : '')
            );
        }

        $json = $response->json();

        if (! is_array($json)) {
            throw new RuntimeException('Plyomat created the athlete but did not return a JSON athlete record.');
        }

        $this->rememberWorkingConnection($trainer, [
            'base_url' => rtrim((string) config('services.plyomat.base_url', 'https://api.plyomat.com'), '/'),
            'path' => $path,
        ], [
            'header' => 'Authorization',
            'prefix' => 'Bearer',
        ]);

        $athlete = $this->extractCreatedAthletePayload($json);
        $athleteId = $this->athleteIdentifier($athlete);

        if ($athleteId === null) {
            throw new RuntimeException('Plyomat created the athlete but the response did not include an athlete ID.');
        }

        return $athlete;
    }

    public function athleteIdentifier(array $athlete): ?string
    {
        $id = data_get($athlete, 'id')
            ?? data_get($athlete, 'athlete_id')
            ?? data_get($athlete, 'athleteId')
            ?? data_get($athlete, 'uuid');

        if (! is_scalar($id)) {
            return null;
        }

        $id = trim((string) $id);

        return $id !== '' ? $id : null;
    }

    public function athleteCreatePayload(User $player): array
    {
        $firstName = trim((string) $player->first_name);
        $lastName = trim((string) $player->last_name);

        if ($firstName === '' || $lastName === '') {
            throw new RuntimeException('The player needs a first and last name before PLYRCard can create the Plyomat athlete.');
        }

        $gender = strtolower(trim((string) $player->gender));
        $gender = match ($gender) {
            'female', 'f', 'woman', 'women', 'girl' => 'female',
            'male', 'm', 'man', 'men', 'boy' => 'male',
            default => null,
        };

        if ($gender === null) {
            throw new RuntimeException('The player needs a male/female gender value before PLYRCard can create the Plyomat athlete.');
        }

        $height = $this->heightCm($player->height);
        $weight = $this->weightKg($player->weight);

        if ($height === null) {
            throw new RuntimeException('The player needs a valid height before PLYRCard can create the Plyomat athlete.');
        }

        if ($weight === null) {
            throw new RuntimeException('The player needs a valid body weight before PLYRCard can create the Plyomat athlete.');
        }

        return [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'external_id' => app()->environment() . '-' . $player->getKey(),
            'external_source' => 'plyrcard',
            'gender' => $gender,
            'height_cm' => $height,
            'body_weight_kg' => $weight,
        ];
    }

    protected function extractCreatedAthletePayload(array $payload): array
    {
        foreach ([
            $payload,
            data_get($payload, 'data'),
            data_get($payload, 'athlete'),
            data_get($payload, 'result'),
            data_get($payload, 'data.athlete'),
            data_get($payload, 'result.athlete'),
        ] as $candidate) {
            if (! is_array($candidate) || $candidate === []) {
                continue;
            }

            if ($this->athleteIdentifier($candidate) !== null) {
                return $candidate;
            }
        }

        return $payload;
    }

    protected function heightCm(mixed $value): ?float
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        $raw = strtolower(trim((string) $value));

        if (preg_match('/^(\d+)\s*[\'′]\s*(\d+(?:\.\d+)?)?\s*(?:[\\"″])?$/u', $raw, $match)) {
            $feet = (float) $match[1];
            $inches = isset($match[2]) && $match[2] !== '' ? (float) $match[2] : 0.0;

            return round(($feet * 12 + $inches) * 2.54, 2);
        }

        $number = preg_replace('/[^0-9.]+/', '', $raw);
        if ($number === '' || ! is_numeric($number)) {
            return null;
        }

        $number = (float) $number;

        if (str_contains($raw, 'ft') || str_contains($raw, 'feet')) {
            return round($number * 30.48, 2);
        }

        if (str_contains($raw, 'in')) {
            return round($number * 2.54, 2);
        }

        if ($number > 0 && $number < 3) {
            return round($number * 100, 2);
        }

        if ($number >= 3 && $number <= 8) {
            return round($number * 30.48, 2);
        }

        return $number > 0 ? round($number, 2) : null;
    }

    protected function weightKg(mixed $value): ?float
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        $raw = strtolower(trim((string) $value));
        $number = preg_replace('/[^0-9.]+/', '', $raw);

        if ($number === '' || ! is_numeric($number)) {
            return null;
        }

        $number = (float) $number;

        if (str_contains($raw, 'lb')) {
            return round($number * 0.45359237, 2);
        }

        return $number > 0 ? round($number, 2) : null;
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

                $attempt = [
                    'url' => $candidate['url'],
                    'status' => $response->status(),
                    'content_type' => $contentType,
                    'auth' => $auth['label'],
                ];

                $attempts[] = $attempt;

                if (in_array($response->status(), [401, 403], true)) {
                    $lastAuthStatus = $response->status();
                    continue;
                }

                if (in_array($response->status(), [404, 405], true)) {
                    break;
                }

                if (! $response->successful()) {
                    $errorJson = $response->json();

                    if (is_array($errorJson)) {
                        $attempts[array_key_last($attempts)]['error_keys'] = array_slice(array_keys($errorJson), 0, 8);
                        $attempts[array_key_last($attempts)]['error_message'] = $this->diagnosticText(
                            data_get($errorJson, 'message')
                                ?? data_get($errorJson, 'detail')
                                ?? data_get($errorJson, 'error')
                        );
                    }

                    continue;
                }

                $json = $response->json();

                if (is_array($json)) {
                    $attempts[array_key_last($attempts)]['json_keys'] = array_slice(array_keys($json), 0, 8);
                }

                if (! is_array($json)) {
                    // A 200 HTML response means we hit the Plyomat web app/SPA,
                    // not the developer API. Auth headers cannot fix the route.
                    break;
                }

                $athlete = $this->extractAthletePayload($json, $athleteId);

                if ($athlete === null && ($candidate['path'] ?? null) === '/v1/athletes') {
                    $athlete = $this->findAthleteAcrossCollectionPages(
                        $trainer,
                        $candidate,
                        $auth,
                        $json,
                        $athleteId,
                    );
                }

                if ($athlete !== null) {
                    $this->rememberWorkingConnection($trainer, $candidate, $auth);

                    return $athlete;
                }

                // We reached a JSON endpoint, but it was not an athlete payload.
                // Try the next candidate route rather than repeating auth variants.
                break;
            }
        }

        Log::warning('PLYRLab could not resolve Plyomat Athlete Read endpoint.', [
            'trainer_id' => $trainer->getKey(),
            'athlete_id' => $athleteId,
            'attempts' => $attempts,
        ]);

        if ($lastAuthStatus === 401) {
            throw new RuntimeException('Plyomat returned HTTP 401 for Authorization: Bearer. PLYRCard used the trainer\'s saved secret token exactly once (and automatically removed a pasted "Bearer " prefix if present). Replace the trainer token if this continues.');
        }

        if ($lastAuthStatus === 403) {
            throw new RuntimeException('Plyomat denied Athlete Read access. Check the API key scope for this trainer.');
        }

        $summary = collect($attempts)
            ->take(8)
            ->map(function (array $attempt): string {
                $host = parse_url((string) ($attempt['url'] ?? ''), PHP_URL_HOST) ?: 'unknown-host';
                $path = parse_url((string) ($attempt['url'] ?? ''), PHP_URL_PATH) ?: '/';
                $query = parse_url((string) ($attempt['url'] ?? ''), PHP_URL_QUERY);
                $target = $host . $path . ($query ? '?' . $query : '');
                $type = trim((string) ($attempt['content_type'] ?? '')) ?: 'unknown content type';
                $keys = $attempt['json_keys'] ?? ($attempt['error_keys'] ?? []);
                $message = $this->diagnosticText($attempt['error_message'] ?? null);

                return $target . ' → HTTP ' . ($attempt['status'] ?? '?')
                    . ' · ' . $type
                    . ($keys !== [] ? ' · keys: ' . implode(', ', $keys) : '')
                    . ($message !== '' ? ' · ' . Str::limit($message, 120) : '');
            })
            ->implode(' | ');

        throw new RuntimeException(
            'Plyomat did not return an athlete record for that valid ID. API diagnostics: ' . $summary
        );
    }

    protected function endpointCandidates(PlyrLabTrainer $trainer, string $athleteId): array
    {
        $legacyBase = trim((string) $trainer->plyomat_base_url);
        $configuredBase = trim((string) config('services.plyomat.base_url', 'https://api.plyomat.com'));

        $bases = array_values(array_unique(array_filter([
            $legacyBase,
            $configuredBase,
            'https://api.plyomat.com',
        ])));

        $isUuid = (bool) preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $athleteId,
        );

        // Plyomat documents GET /v1/athletes/{id} for the primary UUID.
        // The create schema separately exposes external_id, so short IDs should
        // be resolved through the collection/filter API instead of being passed
        // into the UUID route (which currently returns HTTP 500 for short IDs).
        $paths = $isUuid
            ? [
                '/v1/athletes/{athlete_id}',
                trim((string) config('services.plyomat.athlete_read_path')),
                '/v1/athletes?id={athlete_id}',
                '/v1/athletes',
            ]
            : [
                '/v1/athletes?external_id={athlete_id}',
                '/v1/athletes?id={athlete_id}',
                '/v1/athletes?athlete_id={athlete_id}',
                '/v1/athletes',
            ];

        $paths = array_values(array_unique(array_filter($paths)));
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

    protected function findAthleteAcrossCollectionPages(
        PlyrLabTrainer $trainer,
        array $candidate,
        array $auth,
        array $firstPage,
        string $athleteId,
    ): ?array {
        $match = $this->extractAthletePayload($firstPage, $athleteId);

        if ($match !== null) {
            return $match;
        }

        $hasMore = (bool) data_get($firstPage, 'has_more', false);
        $limit = max(1, (int) data_get($firstPage, 'limit', count((array) data_get($firstPage, 'data', [])) ?: 50));
        $offset = max(0, (int) data_get($firstPage, 'offset', 0));
        $total = max(0, (int) data_get($firstPage, 'total', 0));

        if (! $hasMore) {
            return null;
        }

        $baseUrl = rtrim((string) ($candidate['base_url'] ?? config('services.plyomat.base_url', 'https://api.plyomat.com')), '/');
        $pagesChecked = 1;

        while ($hasMore && $pagesChecked < 50) {
            $offset += $limit;
            $url = $baseUrl . '/v1/athletes?limit=' . $limit . '&offset=' . $offset;
            $response = $this->request($trainer, $auth)->get($url);

            if (! $response->successful()) {
                break;
            }

            $json = $response->json();

            if (! is_array($json)) {
                break;
            }

            $match = $this->extractAthletePayload($json, $athleteId);

            if ($match !== null) {
                return $match;
            }

            $hasMore = (bool) data_get($json, 'has_more', false);
            $nextLimit = (int) data_get($json, 'limit', $limit);

            if ($nextLimit > 0) {
                $limit = $nextLimit;
            }

            $pagesChecked++;

            if ($total > 0 && $offset + $limit >= $total) {
                break;
            }
        }

        return null;
    }

    protected function authCandidates(PlyrLabTrainer $trainer): array
    {
        // Plyomat's developer example uses Authorization: Bearer <secret token>.
        // Keep Athlete Read deterministic and do not let stale auth metadata from
        // earlier integration experiments override the documented scheme.
        return [[
            'label' => 'authorization_bearer',
            'header' => 'Authorization',
            'prefix' => 'Bearer',
        ]];
    }

    protected function extractAthletePayload(array $payload, string $athleteId): ?array
    {
        $candidates = [
            $payload,
            data_get($payload, 'data'),
            data_get($payload, 'athlete'),
            data_get($payload, 'result'),
            data_get($payload, 'athletes'),
            data_get($payload, 'results'),
            data_get($payload, 'data.athlete'),
            data_get($payload, 'data.athletes'),
            data_get($payload, 'result.athlete'),
            data_get($payload, 'result.athletes'),
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

                    foreach ([
                        data_get($row, 'id'),
                        data_get($row, 'athlete_id'),
                        data_get($row, 'athleteId'),
                        data_get($row, 'uuid'),
                        data_get($row, 'external_id'),
                        data_get($row, 'externalId'),
                    ] as $id) {
                        if (is_scalar($id) && (string) $id === $athleteId) {
                            return true;
                        }
                    }

                    return false;
                });

                if (is_array($match)) {
                    return $match;
                }

                if (count($candidate) === 1 && is_array($candidate[0])) {
                    return $candidate[0];
                }

                continue;
            }

            $identifierValues = [
                data_get($candidate, 'id'),
                data_get($candidate, 'athlete_id'),
                data_get($candidate, 'athleteId'),
                data_get($candidate, 'uuid'),
                data_get($candidate, 'external_id'),
                data_get($candidate, 'externalId'),
            ];

            $hasIdentifier = collect($identifierValues)->contains(
                fn ($value): bool => is_scalar($value) && (string) $value === $athleteId
            );

            $hasAthleteShape = collect([
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

            if ($hasIdentifier || ($hasAthleteShape && collect($identifierValues)->filter(fn ($v) => is_scalar($v) && (string) $v !== '')->isEmpty())) {
                return $candidate;
            }
        }

        return null;
    }

    protected function rememberWorkingConnection(PlyrLabTrainer $trainer, array $candidate, array $auth): void
    {
        $trainer->forceFill([
            'plyomat_base_url' => $candidate['base_url'] ?? config('services.plyomat.base_url', 'https://api.plyomat.com'),
            'plyomat_auth_header' => $auth['header'] ?? 'Authorization',
            'plyomat_auth_prefix' => $auth['prefix'] ?? 'Bearer',
        ])->saveQuietly();
    }

    protected function rememberWorkingStatsConnection(PlyrLabTrainer $trainer, array $candidate, array $auth): void
    {
        $this->rememberWorkingConnection($trainer, $candidate, $auth);

        $trainer->forceFill([
            'plyomat_stats_path' => $candidate['path'] ?? null,
        ])->saveQuietly();
    }

    public function fetchStats(PlyrLabBooking $booking): array
    {
        // Athlete endpoints primarily describe the roster/profile. Performance
        // measurements may live under sessions/results/assessments instead, so
        // start with the athlete payload and then probe the read-only performance
        // resources until one actually contains supported metrics.
        $profile = $this->readAthlete($booking);
        $profileStats = $this->normalizeStats($profile);

        if ($this->hasPerformanceMeasurements($profileStats)) {
            return $profileStats;
        }

        foreach ($this->performancePayloadCandidates($booking) as $payload) {
            $stats = $this->normalizeStats($payload['data']);

            if (! $this->hasPerformanceMeasurements($stats)) {
                continue;
            }

            $stats['source_endpoint'] = $payload['path'];
            $this->rememberWorkingStatsConnection(
                $booking->trainer,
                [
                    'base_url' => $payload['base_url'],
                    'path' => $payload['path'],
                ],
                $payload['auth'],
            );

            return $stats;
        }

        // Keep the normalized profile shape so the UI can clearly show that
        // the athlete exists but no supported measurements were returned.
        return $profileStats;
    }

    protected function performancePayloadCandidates(PlyrLabBooking $booking): array
    {
        $booking->loadMissing('trainer');
        $trainer = $booking->trainer;

        if (! $trainer || blank($booking->plyomat_athlete_id) || ! $this->isConfigured($trainer)) {
            return [];
        }

        $athleteId = trim((string) $booking->plyomat_athlete_id);
        $base = rtrim(trim((string) ($trainer->plyomat_base_url ?: config('services.plyomat.base_url', 'https://api.plyomat.com'))), '/');

        $savedStatsPath = trim((string) $trainer->plyomat_stats_path);
        if ($savedStatsPath !== '' && ! preg_match('/(session|assessment|result|test|measurement|rep)/i', $savedStatsPath)) {
            // Older builds accidentally stored /v1/athletes here. Ignore that
            // roster endpoint when discovering actual performance data.
            $savedStatsPath = '';
        }

        $paths = array_values(array_unique(array_filter([
            $savedStatsPath,
            '/v1/athletes/{athlete_id}/results',
            '/v1/athletes/{athlete_id}/assessments',
            '/v1/athletes/{athlete_id}/sessions',
            '/v1/athletes/{athlete_id}/measurements',
            '/v1/athletes/{athlete_id}/tests',
            '/v1/results?athlete_id={athlete_id}',
            '/v1/assessments?athlete_id={athlete_id}',
            '/v1/sessions?athlete_id={athlete_id}',
            '/v1/measurements?athlete_id={athlete_id}',
            '/v1/tests?athlete_id={athlete_id}',
            '/v1/reps?athlete_id={athlete_id}',
        ])));

        $payloads = [];
        $authCandidates = $this->authCandidates($trainer);

        // Official developer examples use Authorization: Bearer, so put it first
        // even for trainers created before that format was saved.
        usort($authCandidates, static function (array $a, array $b): int {
            $score = static fn (array $auth): int => (
                strtolower((string) ($auth['header'] ?? '')) === 'authorization'
                && strtolower((string) ($auth['prefix'] ?? '')) === 'bearer'
            ) ? 0 : 1;

            return $score($a) <=> $score($b);
        });

        foreach ($paths as $path) {
            $resolved = str_replace(
                ['{athlete}', '{athlete_id}', '{id}'],
                rawurlencode($athleteId),
                $path,
            );
            $url = Str::startsWith($resolved, ['http://', 'https://'])
                ? $resolved
                : $base . '/' . ltrim($resolved, '/');

            foreach ($authCandidates as $auth) {
                $response = $this->request($trainer, $auth)->get($url);

                if (in_array($response->status(), [401, 403], true)) {
                    continue;
                }

                if (in_array($response->status(), [404, 405], true)) {
                    break;
                }

                if (! $response->successful()) {
                    continue;
                }

                $json = $response->json();

                if (! is_array($json) || ! $this->performancePayloadBelongsToAthlete($json, $athleteId, $resolved)) {
                    break;
                }

                $payloads[] = [
                    'data' => $json,
                    'path' => $path,
                    'base_url' => $base,
                    'auth' => $auth,
                ];

                // A successful JSON response does not guarantee it contains
                // measurements. Keep probing other resource types when needed.
                break;
            }
        }

        return $payloads;
    }

    protected function performancePayloadBelongsToAthlete(array $payload, string $athleteId, string $resolvedPath): bool
    {
        // A nested athlete endpoint is already scoped by the requested ID.
        if (str_contains($resolvedPath, '/' . rawurlencode($athleteId) . '/')) {
            return true;
        }

        $flat = Arr::dot($payload);
        $foundAthleteId = false;

        foreach ($flat as $key => $value) {
            if (! is_scalar($value)) {
                continue;
            }

            $normalizedKey = preg_replace('/[^a-z0-9]+/', '', strtolower((string) $key)) ?: '';

            if (! str_ends_with($normalizedKey, 'athleteid') && $normalizedKey !== 'athleteid') {
                continue;
            }

            $foundAthleteId = true;

            if ((string) $value === $athleteId) {
                return true;
            }
        }

        // Filtered endpoints sometimes omit athlete_id from each returned row.
        // In that case trust the explicit athlete_id query parameter.
        return ! $foundAthleteId && str_contains($resolvedPath, 'athlete_id=' . rawurlencode($athleteId));
    }

    public function hasPerformanceMeasurements(array $stats): bool
    {
        $values = [
            data_get($stats, 'overall_score'),
            data_get($stats, 'pace_score'),
            data_get($stats, 'jump.vertical_jump_cm'),
            data_get($stats, 'jump.rsi'),
            data_get($stats, 'jump.bounce_height_cm'),
            data_get($stats, 'jump.bounce_factor'),
            data_get($stats, 'jump.ground_contact_ms'),
            data_get($stats, 'jump.flight_time_ms'),
            data_get($stats, 'jump.left_jump_cm'),
            data_get($stats, 'jump.right_jump_cm'),
            data_get($stats, 'speed.sprint_10m_seconds'),
            data_get($stats, 'speed.sprint_20m_seconds'),
            data_get($stats, 'speed.sprint_30m_seconds'),
            data_get($stats, 'speed.top_speed_kph'),
        ];

        return collect($values)->contains(fn ($value): bool => is_numeric($value));
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

        // Plyomat/history payloads may represent measurements as records rather
        // than named JSON properties, for example:
        // { "name": "RSI", "value": 2.4 } or
        // { "metric": "Jump Height", "result": 37.8 }.
        // Flattening alone cannot associate the label with its value, so build
        // an additional lookup from those structured measurement rows.
        foreach ($this->structuredMeasurementLookup($payload) as $key => $candidate) {
            if (! array_key_exists($key, $lookup)) {
                $lookup[$key] = $candidate;
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

        $milliseconds = static function ($candidate) use ($number): ?float {
            $value = $number($candidate);

            if ($value === null) {
                return null;
            }

            // Plyomat-style timing payloads may expose seconds while the PLYRLab
            // interface displays milliseconds. Values below 10 are safely time
            // durations in seconds rather than realistic millisecond counts.
            return $value > 0 && $value < 10 ? $value * 1000 : $value;
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
                'vertical_jump_cm' => $number($value([
                    'vertical_jump_cm', 'vertical_jump', 'jump_height_cm', 'jumpheightcm',
                    'standing_vertical_jump_cm', 'jump_height', 'jumpheight', 'jump height',
                    'countermovement_jump_height', 'cmj_height', 'cmjheight', 'svj',
                ])),
                'rsi' => $number($value([
                    'reactive_strength_index', 'reactivestrengthindex', 'reactive strength index',
                    'rsi_value', 'rsi_score', 'rsi',
                ])),
                'bounce_height_cm' => $number($value([
                    'bounce_height_cm', 'bounce_height', 'bounce height',
                    'repeated_hop_height_cm', 'repeated_jump_height_cm',
                ])),
                'bounce_factor' => $number($value([
                    'bounce_factor', 'bouncefactor', 'bounce factor', 'bounce_index', 'bounceindex',
                ])),
                'ground_contact_ms' => $milliseconds($value([
                    'ground_contact_ms', 'ground_contact_time_ms', 'contact_time_ms',
                    'ground_contact_time', 'groundcontacttime', 'contact_time', 'contacttime', 'gct',
                ])),
                'flight_time_ms' => $milliseconds($value([
                    'flight_time_ms', 'flighttime_ms', 'flight_time', 'flighttime',
                ])),
                'left_jump_cm' => $left,
                'right_jump_cm' => $right,
                'balance_gap_percent' => $gap,
            ],
            'speed' => [
                'sprint_10m_seconds' => $number($value([
                    'sprint_10m_seconds', '10m_seconds', '10m sprint', '10 m sprint', '10 meter sprint', '10 metre sprint', 't10', '10m',
                ])),
                'sprint_20m_seconds' => $number($value([
                    'sprint_20m_seconds', '20m_seconds', '20m sprint', '20 m sprint', '20 meter sprint', '20 metre sprint', 't20', '20m',
                ])),
                'sprint_30m_seconds' => $number($value([
                    'sprint_30m_seconds', '30m_seconds', '30m sprint', '30 m sprint', '30 meter sprint', '30 metre sprint', 't30', '30m',
                ])),
                'top_speed_kph' => $number($value([
                    'top_speed_kph', 'max_speed_kph', 'top speed', 'max speed', 'vmax_kph', 'vmax', 'top_speed',
                ])),
            ],
        ];
    }

    protected function structuredMeasurementLookup(array $payload): array
    {
        $lookup = [];

        $walk = function ($node) use (&$walk, &$lookup): void {
            if (! is_array($node)) {
                return;
            }

            if (! array_is_list($node)) {
                $label = data_get($node, 'metric_name')
                    ?? data_get($node, 'metricName')
                    ?? data_get($node, 'metric')
                    ?? data_get($node, 'measurement_name')
                    ?? data_get($node, 'measurementName')
                    ?? data_get($node, 'measurement')
                    ?? data_get($node, 'test_name')
                    ?? data_get($node, 'testName')
                    ?? data_get($node, 'name')
                    ?? data_get($node, 'slug')
                    ?? data_get($node, 'key')
                    ?? data_get($node, 'type');

                $candidate = data_get($node, 'value')
                    ?? data_get($node, 'result')
                    ?? data_get($node, 'measurement_value')
                    ?? data_get($node, 'measurementValue')
                    ?? data_get($node, 'score')
                    ?? data_get($node, 'average')
                    ?? data_get($node, 'avg')
                    ?? data_get($node, 'mean')
                    ?? data_get($node, 'best')
                    ?? data_get($node, 'max');

                if (is_scalar($label) && is_scalar($candidate)) {
                    $key = preg_replace('/[^a-z0-9]+/', '', strtolower((string) $label)) ?: '';

                    if ($key !== '' && ! array_key_exists($key, $lookup)) {
                        $lookup[$key] = $candidate;
                    }

                    $unit = data_get($node, 'unit')
                        ?? data_get($node, 'units')
                        ?? data_get($node, 'uom');

                    if ($key !== '' && is_scalar($unit)) {
                        $unitKey = preg_replace('/[^a-z0-9]+/', '', strtolower((string) $unit)) ?: '';

                        if ($unitKey !== '' && ! array_key_exists($key . $unitKey, $lookup)) {
                            $lookup[$key . $unitKey] = $candidate;
                        }
                    }
                }
            }

            foreach ($node as $value) {
                if (is_array($value)) {
                    $walk($value);
                }
            }
        };

        $walk($payload);

        return $lookup;
    }

    protected function diagnosticText(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_scalar($value)) {
            return trim((string) $value);
        }

        if (is_array($value)) {
            // Prefer common validation/error leaves when available.
            foreach (['message', 'detail', 'error', 'errors', 'non_field_errors'] as $key) {
                if (array_key_exists($key, $value)) {
                    $nested = $this->diagnosticText($value[$key]);

                    if ($nested !== '') {
                        return $nested;
                    }
                }
            }

            $encoded = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            return $encoded !== false ? $encoded : 'Structured API error';
        }

        if ($value instanceof \Stringable) {
            return trim((string) $value);
        }

        return get_debug_type($value);
    }

    protected function normalizeSecretToken(string $token): string
    {
        $token = trim($token);

        // Be tolerant of values copied from curl snippets, browser fields, or
        // headers instead of only the bare secret.
        $token = preg_replace('/^Authorization\s*:\s*/i', '', $token) ?: $token;
        $token = preg_replace('/^Bearer\s+/i', '', $token) ?: $token;
        $token = trim($token, " \t\n\r\0\x0B\"'");
        $token = preg_replace('/\s+/', '', $token) ?: $token;

        return $token;
    }

    protected function request(PlyrLabTrainer $trainer, ?array $auth = null): PendingRequest
    {
        $auth ??= [
            'header' => trim((string) ($trainer->plyomat_auth_header ?: config('services.plyomat.auth_header', 'Authorization'))),
            'prefix' => trim((string) ($trainer->plyomat_auth_prefix ?? config('services.plyomat.auth_prefix', 'Bearer'))),
        ];

        $header = trim((string) ($auth['header'] ?? 'Authorization'));
        $prefix = trim((string) ($auth['prefix'] ?? ''));
        $key = $this->normalizeSecretToken((string) $trainer->plyomat_api_key);

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
