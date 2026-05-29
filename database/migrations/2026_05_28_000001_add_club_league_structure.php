<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leagues', function (Blueprint $table) {
            if (! Schema::hasColumn('leagues', 'genders')) {
                $table->json('genders')->nullable()->after('gender');
            }
        });

        Schema::table('clubs', function (Blueprint $table) {
            if (! Schema::hasColumn('clubs', 'canonical_club_id')) {
                $table->foreignId('canonical_club_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('clubs')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('clubs', 'legacy_landing_page_slug')) {
                $table->string('legacy_landing_page_slug')->nullable()->after('landing_page_slug');
            }
        });

        if (! Schema::hasTable('club_leagues')) {
            Schema::create('club_leagues', function (Blueprint $table) {
                $table->id();
                $table->foreignId('club_id')->constrained('clubs')->cascadeOnDelete();
                $table->foreignId('league_id')->constrained('leagues')->cascadeOnDelete();
                $table->json('genders')->nullable();
                $table->string('sport')->nullable();
                $table->boolean('is_active')->default(true)->index();
                $table->unsignedInteger('sort_order')->default(0);
                $table->json('legacy_club_ids')->nullable();
                $table->json('settings')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['club_id', 'league_id']);
                $table->index(['league_id', 'is_active']);
                $table->index(['club_id', 'is_active']);
            });
        }

        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'club_league_id')) {
                $table->foreignId('club_league_id')
                    ->nullable()
                    ->after('league_id')
                    ->constrained('club_leagues')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('users', 'legacy_club_id')) {
                $table->foreignId('legacy_club_id')
                    ->nullable()
                    ->after('club_league_id')
                    ->constrained('clubs')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('users', 'legacy_league_id')) {
                $table->foreignId('legacy_league_id')
                    ->nullable()
                    ->after('legacy_club_id')
                    ->constrained('leagues')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('users', 'legacy_team_name')) {
                $table->string('legacy_team_name')->nullable()->after('legacy_league_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'legacy_team_name')) {
                $table->dropColumn('legacy_team_name');
            }

            if (Schema::hasColumn('users', 'legacy_league_id')) {
                $table->dropConstrainedForeignId('legacy_league_id');
            }

            if (Schema::hasColumn('users', 'legacy_club_id')) {
                $table->dropConstrainedForeignId('legacy_club_id');
            }

            if (Schema::hasColumn('users', 'club_league_id')) {
                $table->dropConstrainedForeignId('club_league_id');
            }
        });

        Schema::dropIfExists('club_leagues');

        Schema::table('clubs', function (Blueprint $table) {
            if (Schema::hasColumn('clubs', 'legacy_landing_page_slug')) {
                $table->dropColumn('legacy_landing_page_slug');
            }

            if (Schema::hasColumn('clubs', 'canonical_club_id')) {
                $table->dropConstrainedForeignId('canonical_club_id');
            }
        });

        Schema::table('leagues', function (Blueprint $table) {
            if (Schema::hasColumn('leagues', 'genders')) {
                $table->dropColumn('genders');
            }
        });
    }
};
