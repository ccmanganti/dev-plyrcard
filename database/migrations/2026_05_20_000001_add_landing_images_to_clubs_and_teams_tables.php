<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('clubs')) {
            Schema::table('clubs', function (Blueprint $table) {
                if (! Schema::hasColumn('clubs', 'hero_image')) {
                    $table->string('hero_image')->nullable()->after('logo');
                }

                if (! Schema::hasColumn('clubs', 'background_image')) {
                    $table->string('background_image')->nullable()->after('hero_image');
                }
            });
        }

        if (Schema::hasTable('teams')) {
            Schema::table('teams', function (Blueprint $table) {
                if (! Schema::hasColumn('teams', 'hero_image')) {
                    $table->string('hero_image')->nullable()->after('logo');
                }

                if (! Schema::hasColumn('teams', 'background_image')) {
                    $table->string('background_image')->nullable()->after('hero_image');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('teams')) {
            Schema::table('teams', function (Blueprint $table) {
                if (Schema::hasColumn('teams', 'background_image')) {
                    $table->dropColumn('background_image');
                }

                if (Schema::hasColumn('teams', 'hero_image')) {
                    $table->dropColumn('hero_image');
                }
            });
        }

        if (Schema::hasTable('clubs')) {
            Schema::table('clubs', function (Blueprint $table) {
                if (Schema::hasColumn('clubs', 'background_image')) {
                    $table->dropColumn('background_image');
                }

                if (Schema::hasColumn('clubs', 'hero_image')) {
                    $table->dropColumn('hero_image');
                }
            });
        }
    }
};
