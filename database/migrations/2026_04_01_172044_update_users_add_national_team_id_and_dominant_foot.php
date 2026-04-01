<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'national_team_id')) {
                $table->foreignId('national_team_id')
                    ->nullable()
                    ->after('league_id')
                    ->constrained('national_teams')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('users', 'dominant_foot')) {
                $table->string('dominant_foot')
                    ->nullable()
                    ->after('position');
            }

            if (Schema::hasColumn('users', 'national_team_name')) {
                $table->dropColumn('national_team_name');
            }

            if (Schema::hasColumn('users', 'national_team_logo')) {
                $table->dropColumn('national_team_logo');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'national_team_name')) {
                $table->string('national_team_name')->nullable();
            }

            if (! Schema::hasColumn('users', 'national_team_logo')) {
                $table->string('national_team_logo')->nullable();
            }

            if (Schema::hasColumn('users', 'national_team_id')) {
                $table->dropConstrainedForeignId('national_team_id');
            }

            if (Schema::hasColumn('users', 'dominant_foot')) {
                $table->dropColumn('dominant_foot');
            }
        });
    }
};