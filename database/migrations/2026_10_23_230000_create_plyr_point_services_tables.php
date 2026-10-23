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

        Schema::create('plyr_point_service_samples', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('plyr_point_service_id')->constrained('plyr_point_services')->cascadeOnDelete();
            $table->string('type', 24);
            $table->string('title', 180)->nullable();
            $table->string('path', 500)->nullable();
            $table->text('source_url')->nullable();
            $table->string('youtube_id', 80)->nullable();
            $table->string('mime', 120)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['plyr_point_service_id', 'sort_order']);
        });

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

            $serviceId = DB::table('plyr_point_services')->insertGetId([
                'key' => $key,
                'name' => trim((string) ($saved['name'] ?? $default['name'])) ?: $default['name'],
                'points' => max(1, (int) ($saved['points'] ?? $default['points'])),
                'description' => trim((string) ($saved['description'] ?? $default['description'])),
                'cta' => trim((string) ($saved['cta'] ?? $default['cta'])) ?: $default['cta'],
                'inclusions' => json_encode($this->cleanInclusions($saved['inclusions'] ?? [])),
                'is_enabled' => array_key_exists('enabled', $saved) ? (bool) $saved['enabled'] : true,
                'is_system' => true,
                'sort_order' => $sort,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->importLegacySamples($serviceId, (array) ($saved['samples'] ?? []));
            unset($legacyByKey[$key]);
            $sort += 10;
        }

        foreach ($legacyByKey as $key => $saved) {
            $name = trim((string) ($saved['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $serviceId = DB::table('plyr_point_services')->insertGetId([
                'key' => $this->uniqueKey($key),
                'name' => $name,
                'points' => max(1, (int) ($saved['points'] ?? 1)),
                'description' => trim((string) ($saved['description'] ?? '')),
                'cta' => trim((string) ($saved['cta'] ?? '')) ?: 'Request service',
                'inclusions' => json_encode($this->cleanInclusions($saved['inclusions'] ?? [])),
                'is_enabled' => array_key_exists('enabled', $saved) ? (bool) $saved['enabled'] : true,
                'is_system' => false,
                'sort_order' => $sort,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->importLegacySamples($serviceId, (array) ($saved['samples'] ?? []));
            $sort += 10;
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('plyr_point_service_samples');
        Schema::dropIfExists('plyr_point_services');
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

    private function importLegacySamples(int $serviceId, array $samples): void
    {
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

    private function uniqueKey(string $candidate): string
    {
        $base = Str::slug($candidate, '_') ?: 'service';
        $key = $base;
        $suffix = 2;

        while (DB::table('plyr_point_services')->where('key', $key)->exists()) {
            $key = $base . '_' . $suffix;
            $suffix++;
        }

        return $key;
    }
};
