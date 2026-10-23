<?php

namespace App\Services;

use App\Models\PlyrPointService;
use App\Models\PlyrPointServiceSample;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class PlyrPointCatalogService
{
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

    public function databaseReady(): bool
    {
        return Schema::hasTable('plyr_point_services') && Schema::hasTable('plyr_point_service_samples');
    }

    public function catalog(): array
    {
        if (! $this->databaseReady()) {
            return $this->fallbackCatalog();
        }

        return PlyrPointService::query()
            ->with('samples')
            ->where('is_enabled', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->mapWithKeys(fn (PlyrPointService $service): array => [
                $service->key => $this->serviceArray($service),
            ])
            ->all();
    }

    public function adminRows(): array
    {
        if (! $this->databaseReady()) {
            return collect($this->fallbackCatalog())
                ->map(fn (array $row, string $key): array => [
                    ...$row,
                    'id' => null,
                    'key' => $key,
                    'enabled' => true,
                    'is_system' => true,
                ])
                ->values()
                ->all();
        }

        return PlyrPointService::query()
            ->with('samples')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (PlyrPointService $service): array => [
                ...$this->serviceArray($service),
                'enabled' => (bool) $service->is_enabled,
                'is_system' => (bool) $service->is_system,
            ])
            ->values()
            ->all();
    }

    public function quoteService(string $itemKey, int $quantity = 1, bool $rush = false): array
    {
        $itemKey = strtolower(trim($itemKey));
        $item = $this->catalog()[$itemKey] ?? null;

        if (! $item) {
            throw ValidationException::withMessages([
                'item_key' => 'That PLYR Points service is not available.',
            ]);
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

    private function serviceArray(PlyrPointService $service): array
    {
        return [
            'id' => (int) $service->getKey(),
            'key' => (string) $service->key,
            'name' => (string) $service->name,
            'points' => (int) $service->points,
            'description' => (string) ($service->description ?? ''),
            'cta' => trim((string) $service->cta) ?: 'Request service',
            'inclusions' => collect((array) $service->inclusions)
                ->map(fn ($item): string => trim((string) $item))
                ->filter()
                ->values()
                ->all(),
            'samples' => $service->samples
                ->map(fn (PlyrPointServiceSample $sample): ?array => $this->sampleArray($sample))
                ->filter()
                ->values()
                ->all(),
        ];
    }

    private function sampleArray(PlyrPointServiceSample $sample): ?array
    {
        $type = strtolower(trim((string) $sample->type));
        $sourceUrl = trim((string) ($sample->source_url ?? ''));
        $path = trim((string) ($sample->path ?? ''));

        if ($type === 'youtube') {
            $youtubeId = trim((string) ($sample->youtube_id ?? ''));
            if ($youtubeId === '') {
                return null;
            }

            return [
                'id' => (string) $sample->uuid,
                'db_id' => (int) $sample->getKey(),
                'type' => 'youtube',
                'name' => trim((string) ($sample->title ?? '')) ?: 'YouTube sample',
                'url' => $sourceUrl,
                'embed_url' => 'https://www.youtube-nocookie.com/embed/' . rawurlencode($youtubeId) . '?rel=0',
            ];
        }

        if (in_array($type, ['image', 'video'], true)) {
            $url = $path !== '' ? Storage::disk('public')->url($path) : $sourceUrl;
            if ($url === '') {
                return null;
            }

            return [
                'id' => (string) $sample->uuid,
                'db_id' => (int) $sample->getKey(),
                'type' => $type,
                'name' => trim((string) ($sample->title ?? '')) ?: ucfirst($type) . ' sample',
                'url' => $url,
            ];
        }

        if ($type === 'link' && $sourceUrl !== '') {
            return [
                'id' => (string) $sample->uuid,
                'db_id' => (int) $sample->getKey(),
                'type' => 'link',
                'name' => trim((string) ($sample->title ?? '')) ?: 'Sample link',
                'url' => $sourceUrl,
            ];
        }

        return null;
    }

    private function fallbackCatalog(): array
    {
        $configured = (array) config('plyrcard-points.catalog', []);

        return collect(self::DEFAULTS)->mapWithKeys(function (array $default, string $key) use ($configured): array {
            $row = array_merge($default, (array) ($configured[$key] ?? []));

            return [$key => [
                'id' => null,
                'key' => $key,
                'name' => trim((string) ($row['name'] ?? $default['name'])) ?: $default['name'],
                'points' => max(1, (int) ($row['points'] ?? $default['points'])),
                'description' => trim((string) ($row['description'] ?? $default['description'])),
                'cta' => trim((string) ($row['cta'] ?? $default['cta'])) ?: $default['cta'],
                'inclusions' => [],
                'samples' => [],
            ]];
        })->all();
    }
}