<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('youtube_channel_id')->nullable()->after('yt_url');
            $table->string('youtube_uploads_playlist_id')->nullable()->after('youtube_channel_id');
            $table->json('youtube_cached_videos')->nullable()->after('youtube_uploads_playlist_id');
            $table->timestamp('youtube_cache_refreshed_at')->nullable()->after('youtube_cached_videos');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'youtube_channel_id',
                'youtube_uploads_playlist_id',
                'youtube_cached_videos',
                'youtube_cache_refreshed_at',
            ]);
        });
    }
};