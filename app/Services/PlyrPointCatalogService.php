<?php

namespace App\Services;

use App\Models\SystemSetting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class PlyrPointCatalogService
{
    public const SETTING_KEY = 'plyr_point_services';

    public const DEFAULTS = [
        'outreach' => [
            'name' => 'Coach Outreach Program',
            'points' => 80,
            'description' => 'Targeted outreach to the coaches on your list, from your own recruiting email.',
            'cta' => 'Request an outreach program',
        ],
        'reel' => [
            'name' => 'Highlight Reel',
            'points' => 90,
            'description' => 'Your best moments cut from VEO or game film.',
            'cta' => 'Request a reel',
        ],
        'graphic' => [
            'name' => 'Custom Graphic',
            'points' => 50,
            'description' => 'Commitment posts, CV graphics, game day and stat cards.',
            'cta' => 'Request a graphic',
        ],
        'production_hour' => [
            'name' => 'Rep Hour',
            'points' => 50,
            'description' => 'Have your rep review your profile, or hand off the work when you are short on time.',
            'cta' => 'Book a rep hour',
        ],
    ];

    public function catalog(): array
    {
        return collect($this->adminRows())
            ->filter(fn (array $row): bool => (bool) ($row['enabled'] ?? true))
            ->mapWithKeys(fn (array $row, string $key): array => [
                $key => [
                    'name' => $row['name'],
                    'points' => (int) $row['points'],
                    'description' => $row['description'],
                    'cta' => $row['cta'],
                    'samples' => $row['samples'],
                ],
            ])
            ->all();
    }

    public function adminRows(): array
    {
        return collect($this->rawRows())
            ->mapWithKeys(fn (array $row, string $key): array => [
                $key => [
                    ...$row,
                    'samples' => collect($row['samples'] ?? [])
                        ->map(fn ($sample) => $this->decorateSample(is_array($sample) ? $sample : []))
                        ->filter()
                        ->values()
                        ->all(),
                ],
            ])
            ->all();
    }

    public function enabledKeys(): array
    {
        return array_keys($this->catalog());
    }

    public function find(string $serviceKey): ?array
    {
        $serviceKey = strtolower(trim($serviceKey));
        return $this->adminRows()[$serviceKey] ?? null;
    }

    public function setEnabled(string $serviceKey, bool $enabled): void
    {
        $rows = $this->rawRows();
        $this->assertServiceExists($serviceKey, $rows);
        $rows[$serviceKey]['enabled'] = $enabled;
        $this->persist($rows);
    }

    public function saveService(array $data, ?string $existingKey = null): string
    {
        $rows = $this->rawRows();
        $existingKey = $existingKey !== null ? strtolower(trim($existingKey)) : null;

        if ($existingKey !== null && $existingKey !== '') {
            $this->assertServiceExists($existingKey, $rows);
            $key = $existingKey;
        } else {
            $base = Str::slug((string) ($data['name'] ?? ''), '_') ?: 'service';
            $key = $base;
            $suffix = 2;
            while (array_key_exists($key, $rows)) {
                $key = $base . '_' . $suffix;
                $suffix++;
            }
        }

        $current = (array) ($rows[$key] ?? []);
        $rows[$key] = [
            'key' => $key,
            'name' => trim((string) ($data['name'] ?? '')),
            'points' => max(1, (int) ($data['points'] ?? 1)),
            'description' => trim((string) ($data['description'] ?? '')),
            'cta' => trim((string) ($data['cta'] ?? '')) ?: 'Request service',
            'enabled' => (bool) ($data['enabled'] ?? true),
            'custom' => ! array_key_exists($key, self::DEFAULTS),
            'samples' => collect((array) ($current['samples'] ?? []))
                ->filter(fn ($sample): bool => is_array($sample))
                ->values()
                ->all(),
        ];

        $this->persist($rows);

        return $key;
    }

    public function deleteService(string $serviceKey): void
    {
        $serviceKey = strtolower(trim($serviceKey));
        if (array_key_exists($serviceKey, self::DEFAULTS)) {
            throw new InvalidArgumentException('Built-in PLYR Points services can be turned off, but not deleted.');
        }

        $rows = $this->rawRows();
        $this->assertServiceExists($serviceKey, $rows);

        foreach ((array) ($rows[$serviceKey]['samples'] ?? []) as $sample) {
            if (is_array($sample) && filled($sample['path'] ?? null)) {
                Storage::disk('public')->delete((string) $sample['path']);
            }
        }

        unset($rows[$serviceKey]);
        $this->persist($rows);
    }

    public function quoteService(string $itemKey, int $quantity = 1, bool $rush = false): array
    {
        $itemKey = strtolower(trim($itemKey));
        $catalog = $this->catalog();
        $item = $catalog[$itemKey] ?? null;

        if (! $item) {
            throw ValidationException::withMessages(['item_key' => 'That PLYR Points service is not available.']);
        }

        $quantity = max(1, min(20, $quantity));
        $unitPrice = max(1, (int) ($item['points'] ?? 1));
        $basePoints = $unitPrice * $quantity;
        $totalPoints = $rush ? (int) ceil($basePoints * 1.5) : $basePoints;

        return [
            'item_key' => $itemKey,
            'item_name' => (string) $item['name'],
            'description' => (string) ($item['description'] ?? ''),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'modifier' => $rush ? 'rush' : null,
            'points' => $totalPoints,
            'catalog_version' => (int) config('plyrcard-points.catalog_version', 1),
        ];
    }

    public function addYoutubeSample(string $serviceKey, string $url): void
    {
        $rows = $this->rawRows();
        $this->assertServiceExists($serviceKey, $rows);

        $youtubeId = $this->extractYoutubeId($url);
        if (! $youtubeId) {
            throw new InvalidArgumentException('Enter a valid YouTube video, Shorts, live, or embed URL.');
        }

        $rows[$serviceKey]['samples'][] = [
            'id' => (string) Str::uuid(),
            'type' => 'youtube',
            'youtube_id' => $youtubeId,
            'source_url' => trim($url),
            'name' => 'YouTube sample',
        ];

        $this->persist($rows);
    }

    public function addUploadedSample(string $serviceKey, UploadedFile $file): void
    {
        $rows = $this->rawRows();
        $this->assertServiceExists($serviceKey, $rows);

        $mime = strtolower((string) ($file->getMimeType() ?: $file->getClientMimeType()));
        $type = str_starts_with($mime, 'image/') ? 'image' : (str_starts_with($mime, 'video/') ? 'video' : null);

        if (! $type) {
            throw new InvalidArgumentException('Only image and video files can be used as uploaded samples.');
        }

        $path = $file->store('plyr-points/samples/' . $serviceKey, 'public');

        $rows[$serviceKey]['samples'][] = [
            'id' => (string) Str::uuid(),
            'type' => $type,
            'path' => $path,
            'name' => trim((string) $file->getClientOriginalName()) ?: ucfirst($type) . ' sample',
            'mime' => $mime,
        ];

        $this->persist($rows);
    }

    public function removeSample(string $serviceKey, string $sampleId): void
    {
        $rows = $this->rawRows();
        $this->assertServiceExists($serviceKey, $rows);

        $kept = [];
        foreach ((array) ($rows[$serviceKey]['samples'] ?? []) as $sample) {
            if (! is_array($sample)) {
                continue;
            }

            if ((string) ($sample['id'] ?? '') === $sampleId) {
                if (filled($sample['path'] ?? null)) {
                    Storage::disk('public')->delete((string) $sample['path']);
                }
                continue;
            }

            $kept[] = $sample;
        }

        $rows[$serviceKey]['samples'] = $kept;
        $this->persist($rows);
    }

    private function rawRows(): array
    {
        $savedByKey = [];

        try {
            if (Schema::hasTable('system_settings')) {
                foreach (SystemSetting::value(self::SETTING_KEY, []) as $row) {
                    if (! is_array($row)) {
                        continue;
                    }

                    $key = strtolower(trim((string) ($row['key'] ?? '')));
                    if ($key !== '') {
                        $savedByKey[$key] = $row;
                    }
                }
            }
        } catch (\Throwable) {
            $savedByKey = [];
        }

        $configured = (array) config('plyrcard-points.catalog', []);
        $rows = [];

        foreach (self::DEFAULTS as $key => $default) {
            $config = (array) ($configured[$key] ?? []);
            $saved = (array) ($savedByKey[$key] ?? []);

            $rows[$key] = [
                'key' => $key,
                'name' => trim((string) ($saved['name'] ?? $config['name'] ?? $default['name'])) ?: $default['name'],
                'points' => max(1, (int) ($saved['points'] ?? $config['points'] ?? $default['points'])),
                'description' => trim((string) ($saved['description'] ?? $config['description'] ?? $default['description'])),
                'cta' => trim((string) ($saved['cta'] ?? $config['cta'] ?? $default['cta'])) ?: $default['cta'],
                'enabled' => array_key_exists('enabled', $saved) ? (bool) $saved['enabled'] : true,
                'custom' => false,
                'samples' => collect((array) ($saved['samples'] ?? []))
                    ->filter(fn ($sample): bool => is_array($sample))
                    ->values()
                    ->all(),
            ];

            unset($savedByKey[$key]);
        }

        foreach ($savedByKey as $key => $saved) {
            $name = trim((string) ($saved['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $rows[$key] = [
                'key' => $key,
                'name' => $name,
                'points' => max(1, (int) ($saved['points'] ?? 1)),
                'description' => trim((string) ($saved['description'] ?? '')),
                'cta' => trim((string) ($saved['cta'] ?? '')) ?: 'Request service',
                'enabled' => array_key_exists('enabled', $saved) ? (bool) $saved['enabled'] : true,
                'custom' => true,
                'samples' => collect((array) ($saved['samples'] ?? []))
                    ->filter(fn ($sample): bool => is_array($sample))
                    ->values()
                    ->all(),
            ];
        }

        return $rows;
    }

    private function persist(array $rows): void
    {
        if (! Schema::hasTable('system_settings')) {
            throw new InvalidArgumentException('System settings are not available yet. Run the database migrations first.');
        }

        $payload = collect($rows)
            ->map(fn (array $row, string $key): array => [
                'key' => $key,
                'name' => trim((string) ($row['name'] ?? '')),
                'points' => max(1, (int) ($row['points'] ?? 1)),
                'description' => trim((string) ($row['description'] ?? '')),
                'cta' => trim((string) ($row['cta'] ?? '')) ?: 'Request service',
                'enabled' => (bool) ($row['enabled'] ?? true),
                'custom' => (bool) ($row['custom'] ?? ! array_key_exists($key, self::DEFAULTS)),
                'samples' => collect((array) ($row['samples'] ?? []))
                    ->filter(fn ($sample): bool => is_array($sample))
                    ->map(fn (array $sample): array => collect($sample)
                        ->only(['id', 'type', 'path', 'name', 'mime', 'youtube_id', 'source_url'])
                        ->all())
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();

        SystemSetting::putValue(self::SETTING_KEY, $payload);
    }

    private function decorateSample(array $sample): ?array
    {
        $type = strtolower(trim((string) ($sample['type'] ?? '')));
        $id = trim((string) ($sample['id'] ?? '')) ?: (string) Str::uuid();

        if ($type === 'youtube') {
            $youtubeId = trim((string) ($sample['youtube_id'] ?? ''));
            if ($youtubeId === '') {
                $youtubeId = (string) ($this->extractYoutubeId((string) ($sample['source_url'] ?? '')) ?? '');
            }
            if ($youtubeId === '') {
                return null;
            }

            return [
                ...$sample,
                'id' => $id,
                'type' => 'youtube',
                'youtube_id' => $youtubeId,
                'url' => trim((string) ($sample['source_url'] ?? '')),
                'embed_url' => 'https://www.youtube-nocookie.com/embed/' . $youtubeId . '?rel=0',
                'name' => trim((string) ($sample['name'] ?? '')) ?: 'YouTube sample',
            ];
        }

        if (in_array($type, ['image', 'video'], true) && filled($sample['path'] ?? null)) {
            $path = (string) $sample['path'];

            return [
                ...$sample,
                'id' => $id,
                'type' => $type,
                'url' => Storage::disk('public')->url($path),
                'name' => trim((string) ($sample['name'] ?? basename($path))) ?: ucfirst($type) . ' sample',
            ];
        }

        return null;
    }

    private function assertServiceExists(string $serviceKey, array $rows): void
    {
        if (! array_key_exists($serviceKey, $rows)) {
            throw new InvalidArgumentException('That PLYR Points service does not exist.');
        }
    }

    private function extractYoutubeId(string $url): ?string
    {
        $url = trim($url);
        if ($url === '') {
            return null;
        }

        $parts = parse_url($url);
        if (! is_array($parts)) {
            return null;
        }

        $host = strtolower(preg_replace('/^www\./', '', (string) ($parts['host'] ?? '')) ?? '');
        $path = trim((string) ($parts['path'] ?? ''), '/');

        if ($host === 'youtu.be' && $path !== '') {
            return explode('/', $path)[0] ?: null;
        }

        if (in_array($host, ['youtube.com', 'm.youtube.com', 'music.youtube.com', 'youtube-nocookie.com'], true)) {
            parse_str((string) ($parts['query'] ?? ''), $query);
            if (filled($query['v'] ?? null)) {
                return trim((string) $query['v']);
            }

            foreach (['shorts/', 'embed/', 'live/'] as $prefix) {
                if (str_starts_with($path, $prefix)) {
                    return trim((string) explode('/', substr($path, strlen($prefix)))[0]) ?: null;
                }
            }
        }

        return null;
    }
}