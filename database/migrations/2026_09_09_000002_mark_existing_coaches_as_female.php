<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('coaches') || ! Schema::hasColumn('coaches', 'gender')) {
            return;
        }

        // All coach records that existed before the gender-aware import rollout
        // are from the girls/women's database. Preserve any coach that already
        // has an explicit gender and only backfill unclassified rows.
        DB::table('coaches')
            ->where(function ($query): void {
                $query->whereNull('gender')
                    ->orWhere('gender', '');
            })
            ->update([
                'gender' => 'female',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Intentionally do not erase gender values on rollback. Once coach gender
        // has been classified, reverting it to NULL could expose incorrect rosters.
    }
};
