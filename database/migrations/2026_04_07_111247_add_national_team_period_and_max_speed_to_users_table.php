<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('national_team_period')->nullable()->after('natl_team_exp'); // e.g. 2025-2026
            $table->decimal('max_speed', 6, 2)->nullable()->after('weight'); // e.g. 19.00
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'national_team_period',
                'max_speed',
            ]);
        });
    }
};