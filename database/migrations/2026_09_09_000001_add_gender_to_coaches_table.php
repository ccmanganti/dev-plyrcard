<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coaches', function (Blueprint $table): void {
            $table->string('gender', 20)->nullable()->after('sport')->index();
            $table->index(['school_id', 'gender'], 'coaches_school_gender_index');
            $table->index(['sport', 'gender', 'is_active'], 'coaches_sport_gender_active_index');
        });
    }

    public function down(): void
    {
        Schema::table('coaches', function (Blueprint $table): void {
            $table->dropIndex('coaches_school_gender_index');
            $table->dropIndex('coaches_sport_gender_active_index');
            $table->dropColumn('gender');
        });
    }
};
