<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('websites')) {
            return;
        }

        Schema::table('websites', function (Blueprint $table) {
            if (! Schema::hasColumn('websites', 'article_section_type')) {
                $table->string('article_section_type')->default('follow_me')->after('text_secondary_color');
            }

            if (! Schema::hasColumn('websites', 'ghl_location_id')) {
                $table->string('ghl_location_id')->nullable()->after('article_section_type');
            }

            if (! Schema::hasColumn('websites', 'ghl_calendar_id')) {
                $table->string('ghl_calendar_id')->nullable()->after('ghl_location_id');
            }

            if (! Schema::hasColumn('websites', 'ghl_calendar_name')) {
                $table->string('ghl_calendar_name')->nullable()->after('ghl_calendar_id');
            }

            if (! Schema::hasColumn('websites', 'ghl_calendar_embed_url')) {
                $table->text('ghl_calendar_embed_url')->nullable()->after('ghl_calendar_name');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('websites')) {
            return;
        }

        Schema::table('websites', function (Blueprint $table) {
            foreach ([
                'ghl_calendar_embed_url',
                'ghl_calendar_name',
                'ghl_calendar_id',
                'ghl_location_id',
                'article_section_type',
            ] as $column) {
                if (Schema::hasColumn('websites', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
