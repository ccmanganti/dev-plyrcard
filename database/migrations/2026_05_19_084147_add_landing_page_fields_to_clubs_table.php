<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clubs', function (Blueprint $table) {
            $table->boolean('has_landing_page')->default(false)->after('logo');
            $table->boolean('landing_page_is_published')->default(false)->after('has_landing_page');
            $table->string('landing_page_slug')->nullable()->unique()->after('landing_page_is_published');

            $table->text('landing_page_intro')->nullable()->after('landing_page_slug');
            $table->text('landing_page_content')->nullable()->after('landing_page_intro');

            $table->json('contact_info')->nullable()->after('landing_page_content');
            $table->json('coaching_staff')->nullable()->after('contact_info');
            $table->json('sponsors_partners')->nullable()->after('coaching_staff');
            $table->json('social_links')->nullable()->after('sponsors_partners');
            $table->json('branding')->nullable()->after('social_links');
        });

        Schema::table('teams', function (Blueprint $table) {
            $table->boolean('has_landing_page')->default(false)->after('club_id');
            $table->boolean('landing_page_is_published')->default(false)->after('has_landing_page');
            $table->string('landing_page_slug')->nullable()->unique()->after('landing_page_is_published');

            $table->text('landing_page_intro')->nullable()->after('landing_page_slug');
            $table->text('landing_page_content')->nullable()->after('landing_page_intro');

            $table->json('coaching_staff')->nullable()->after('landing_page_content');
            $table->json('team_settings')->nullable()->after('coaching_staff');
            $table->json('branding')->nullable()->after('team_settings');
        });
    }

    public function down(): void
    {
        Schema::table('clubs', function (Blueprint $table) {
            $table->dropUnique(['landing_page_slug']);

            $table->dropColumn([
                'has_landing_page',
                'landing_page_is_published',
                'landing_page_slug',
                'landing_page_intro',
                'landing_page_content',
                'contact_info',
                'coaching_staff',
                'sponsors_partners',
                'social_links',
                'branding',
            ]);
        });

        Schema::table('teams', function (Blueprint $table) {
            $table->dropUnique(['landing_page_slug']);

            $table->dropColumn([
                'has_landing_page',
                'landing_page_is_published',
                'landing_page_slug',
                'landing_page_intro',
                'landing_page_content',
                'coaching_staff',
                'team_settings',
                'branding',
            ]);
        });
    }
};