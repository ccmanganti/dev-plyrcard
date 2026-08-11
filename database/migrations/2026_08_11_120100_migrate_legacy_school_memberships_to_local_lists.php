<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('coach_database_school_memberships')
            || ! Schema::hasTable('schools')
            || ! Schema::hasTable('favorite_schools')
            || ! Schema::hasTable('my_lists')
            || ! Schema::hasTable('my_list_schools')) {
            return;
        }

        $defaults = [
            'dream' => ['Dream Schools', '#ff6338', 10],
            'target' => ['Target Schools', '#3b82f6', 20],
            'safety' => ['Safety Schools', '#22c55e', 30],
            'camp_follow_up' => ['Camp Follow-Up', '#f59e0b', 40],
            'showcase_follow_up' => ['Showcase Follow-Up', '#7c5cff', 50],
            'general_recruiting' => ['General Recruiting', '#64748b', 60],
        ];

        DB::table('coach_database_school_memberships')
            ->orderBy('id')
            ->chunkById(500, function ($memberships) use ($defaults): void {
                foreach ($memberships as $membership) {
                    $userId = (int) $membership->user_id;
                    $businessId = trim((string) $membership->business_id);
                    $key = strtolower(trim((string) $membership->list_key));
                    if ($userId <= 0 || $businessId === '' || $key === '') continue;

                    $school = DB::table('schools')->where('ghl_business_id', $businessId)->first();
                    if (! $school) continue;

                    if ($key === '__favorite__') {
                        DB::table('favorite_schools')->insertOrIgnore([
                            'user_id' => $userId,
                            'school_id' => $school->id,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                        continue;
                    }

                    $slug = str_starts_with($key, 'custom:') ? substr($key, 7) : $key;
                    $slug = Str::slug(str_replace('_', '-', $slug));
                    if ($slug === '') continue;

                    [$name, $color, $sort] = $defaults[$slug] ?? [Str::headline($slug), '#ff6338', 100];
                    DB::table('my_lists')->insertOrIgnore([
                        'user_id' => $userId,
                        'name' => $name,
                        'slug' => $slug,
                        'color' => $color,
                        'is_system' => array_key_exists($slug, $defaults),
                        'sort_order' => $sort,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $listId = DB::table('my_lists')->where('user_id', $userId)->where('slug', $slug)->value('id');
                    if ($listId) {
                        DB::table('my_list_schools')->insertOrIgnore([
                            'my_list_id' => $listId,
                            'school_id' => $school->id,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            });
    }

    public function down(): void
    {
        // Intentionally non-destructive. The legacy tables remain available for rollback.
    }
};
