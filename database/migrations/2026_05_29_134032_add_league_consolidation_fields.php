<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leagues', function (Blueprint $table) {
            if (! Schema::hasColumn('leagues', 'canonical_league_id')) {
                $table->foreignId('canonical_league_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('leagues')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('leagues', 'legacy_gender')) {
                $table->string('legacy_gender', 50)
                    ->nullable()
                    ->after('gender');
            }
        });

        Schema::table('club_leagues', function (Blueprint $table) {
            if (! Schema::hasColumn('club_leagues', 'canonical_club_league_id')) {
                $table->foreignId('canonical_club_league_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('club_leagues')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('club_leagues', 'legacy_league_ids')) {
                $table->json('legacy_league_ids')
                    ->nullable()
                    ->after('legacy_club_ids');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'legacy_club_league_id')) {
                $table->foreignId('legacy_club_league_id')
                    ->nullable()
                    ->after('club_league_id')
                    ->constrained('club_leagues')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'legacy_club_league_id')) {
                $table->dropConstrainedForeignId('legacy_club_league_id');
            }
        });

        Schema::table('club_leagues', function (Blueprint $table) {
            if (Schema::hasColumn('club_leagues', 'legacy_league_ids')) {
                $table->dropColumn('legacy_league_ids');
            }

            if (Schema::hasColumn('club_leagues', 'canonical_club_league_id')) {
                $table->dropConstrainedForeignId('canonical_club_league_id');
            }
        });

        Schema::table('leagues', function (Blueprint $table) {
            if (Schema::hasColumn('leagues', 'legacy_gender')) {
                $table->dropColumn('legacy_gender');
            }

            if (Schema::hasColumn('leagues', 'canonical_league_id')) {
                $table->dropConstrainedForeignId('canonical_league_id');
            }
        });
    }
};