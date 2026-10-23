<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        // This migration is intentionally re-runnable. A previous deployment may have
        // created one or both tables before MySQL rejected Laravel's auto-generated
        // composite index name for exceeding MySQL's 64-character identifier limit.
        if (! Schema::hasTable('plyr_point_services')) {
            Schema::create('plyr_point_services', function (Blueprint $table) {
                $table->id();
                $table->string('key', 120)->unique();
                $table->string('name', 120);
                $table->unsignedInteger('points');
                $table->text('description')->nullable();
                $table->string('cta', 120)->default('Request service');
                $table->json('inclusions')->nullable();
                $table->boolean('is_enabled')->default(true)->index();
                $table->boolean('is_system')->default(false);
                $table->unsignedInteger('sort_order')->default(0)->index();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('plyr_point_service_samples')) {
            Schema::create('plyr_point_service_samples', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('plyr_point_service_id')
                    ->constrained('plyr_point_services')
                    ->cascadeOnDelete();
                $table->string('type', 24);
                $table->string('title', 180)->nullable();
                $table->string('path', 500)->nullable();
                $table->text('source_url')->nullable();
                $table->string('youtube_id', 80)->nullable();
                $table->string('mime', 120)->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();

                // Keep the index name short enough for MySQL's 64-character limit.
                $table->index(['plyr_point_service_id', 'sort_order'], 'pps_service_sort_idx');
            });
        } else {
            $this->ensureShortSampleIndex();
        }

        $defaults = [
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

        $legacy = $this->legacyRows();
        $legacyByKey = collect($legacy)
            ->filter(fn ($row) => is_array($row) && filled($row['key'] ?? null))
            ->keyBy(fn ($row) => strtolower(trim((string) $row['key'])))
            ->all();

        $sort = 10;
        foreach ($defaults as $key => $default) {
            $saved = (array) ($legacyByKey[$key] ?? []);
            $serviceId = $this->firstOrCreateService($key, [
                'name' => trim((string) ($saved['name'] ?? $default['name'])) ?: $default['name'],
                'points' => max(1, (int) ($saved['points'] ?? $default['points'])),
                'description' => trim((string) ($saved['description'] ?? $default['description'])),
                'cta' => trim((string) ($saved['cta'] ?? $default['cta'])) ?: $default['cta'],
                'inclusions' => $this->cleanInclusions($saved['inclusions'] ?? []),
                'is_enabled' => array_key_exists('enabled', $saved) ? (bool) $saved['enabled'] : true,
                'is_system' => true,
                'sort_order' => $sort,
            ]);

            $this->importLegacySamplesOnce($serviceId, (array) ($saved['samples'] ?? []));
            unset($legacyByKey[$key]);
            $sort += 10;
        }

        foreach ($legacyByKey as $key => $saved) {
            $name = trim((string) ($saved['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $serviceKey = $this->existingOrUniqueKey((string) $key, $name);
            $serviceId = $this->firstOrCreateService($serviceKey, [
                'name' => $name,
                'points' => max(1, (int) ($saved['points'] ?? 1)),
                'description' => trim((string) ($saved['description'] ?? '')),
                'cta' => trim((string) ($saved['cta'] ?? '')) ?: 'Request service',
                'inclusions' => $this->cleanInclusions($saved['inclusions'] ?? []),
                'is_enabled' => array_key_exists('enabled', $saved) ? (bool) $saved['enabled'] : true,
                'is_system' => false,
                'sort_order' => $sort,
            ]);

            $this->importLegacySamplesOnce($serviceId, (array) ($saved['samples'] ?? []));
            $sort += 10;
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('plyr_point_service_samples');
        Schema::dropIfExists('plyr_point_services');
    }

    private function ensureShortSampleIndex(): void
    {
        try {
            $database = DB::connection()->getDatabaseName();
            $exists = DB::table('information_schema.statistics')
                ->where('table_schema', $database)
                ->where('table_name', 'plyr_point_service_samples')
                ->where('index_name', 'pps_service_sort_idx')
                ->exists();

            if (! $exists) {
                Schema::table('plyr_point_service_samples', function (Blueprint $table) {
                    $table->index(['plyr_point_service_id', 'sort_order'], 'pps_service_sort_idx');
                });
            }
        } catch (\Throwable) {
            // The composite index is a performance optimization, not a functional
            // requirement. Never block the migration if a host restricts metadata access.
        }
    }

    private function firstOrCreateService(string $key, array $attributes): int
    {
        $existingId = DB::table('plyr_point_services')->where('key', $key)->value('id');
        if ($existingId) {
            return (int) $existingId;
        }

        return (int) DB::table('plyr_point_services')->insertGetId([
            'key' => $key,
            'name' => $attributes['name'],
            'points' => $attributes['points'],
            'description' => $attributes['description'],
            'cta' => $attributes['cta'],
            'inclusions' => json_encode($attributes['inclusions']),
            'is_enabled' => $attributes['is_enabled'],
            'is_system' => $attributes['is_system'],
            'sort_order' => $attributes['sort_order'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function legacyRows(): array
    {
        if (! Schema::hasTable('system_settings')) {
            return [];
        }

        $value = DB::table('system_settings')
            ->where('key', 'plyr_point_services')
            ->value('value');

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : [];
        }

        return is_array($value) ? $value : [];
    }

    private function cleanInclusions(mixed $value): array
    {
        return collect(is_array($value) ? $value : [])
            ->map(fn ($item) => trim((string) $item))
            ->filter()
            ->unique()
            ->take(30)
            ->values()
            ->all();
    }

    private function importLegacySamplesOnce(int $serviceId, array $samples): void
    {
        if ($samples === [] || DB::table('plyr_point_service_samples')->where('plyr_point_service_id', $serviceId)->exists()) {
            return;
        }

        $sort = 10;
        foreach ($samples as $sample) {
            if (! is_array($sample)) {
                continue;
            }

            $type = strtolower(trim((string) ($sample['type'] ?? '')));
            if (! in_array($type, ['image', 'video', 'youtube', 'link'], true)) {
                continue;
            }

            DB::table('plyr_point_service_samples')->insert([
                'uuid' => (string) Str::uuid(),
                'plyr_point_service_id' => $serviceId,
                'type' => $type,
                'title' => trim((string) ($sample['name'] ?? $sample['title'] ?? '')) ?: null,
                'path' => filled($sample['path'] ?? null) ? (string) $sample['path'] : null,
                'source_url' => filled($sample['source_url'] ?? $sample['url'] ?? null) ? (string) ($sample['source_url'] ?? $sample['url']) : null,
                'youtube_id' => filled($sample['youtube_id'] ?? null) ? (string) $sample['youtube_id'] : null,
                'mime' => filled($sample['mime'] ?? null) ? (string) $sample['mime'] : null,
                'sort_order' => $sort,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $sort += 10;
        }
    }

    private function existingOrUniqueKey(string $candidate, string $name): string
    {
        $candidate = Str::slug($candidate, '_');
        if ($candidate !== '' && DB::table('plyr_point_services')->where('key', $candidate)->exists()) {
            return $candidate;
        }

        $base = $candidate !== '' ? $candidate : (Str::slug($name, '_') ?: 'service');
        $key = $base;
        $suffix = 2;

        while (DB::table('plyr_point_services')->where('key', $key)->exists()) {
            $key = $base . '_' . $suffix;
            $suffix++;
        }

        return $key;
    }
};