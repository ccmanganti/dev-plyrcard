<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coaches', function (Blueprint $table): void {
            $table->index(['sport', 'deleted_at'], 'coaches_sport_deleted_idx');
            $table->index(['sport', 'division'], 'coaches_sport_division_idx');
            $table->index(['sport', 'conference'], 'coaches_sport_conference_idx');
            $table->index(['school_id', 'sport'], 'coaches_school_sport_idx');
            $table->index(['last_name', 'first_name'], 'coaches_name_idx');
        });
    }

    public function down(): void
    {
        Schema::table('coaches', function (Blueprint $table): void {
            $table->dropIndex('coaches_sport_deleted_idx');
            $table->dropIndex('coaches_sport_division_idx');
            $table->dropIndex('coaches_sport_conference_idx');
            $table->dropIndex('coaches_school_sport_idx');
            $table->dropIndex('coaches_name_idx');
        });
    }
};
