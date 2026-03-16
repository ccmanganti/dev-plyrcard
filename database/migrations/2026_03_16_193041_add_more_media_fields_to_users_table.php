<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('logos_image')->nullable()->after('youtube_thumbnail');
            $table->text('player_bio')->nullable()->after('logos_image');
            $table->string('featured_video_url')->nullable()->after('player_bio');
            $table->text('featured_video_urls')->nullable()->after('featured_video_url');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'logos_image',
                'player_bio',
                'featured_video_url',
                'featured_video_urls',
            ]);
        });
    }
};