<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Http;

class YouTubeChannelService
{
    public function refreshUserVideos(User $user, int $limit = 12): array
    {
        $channelUrl = trim((string) ($user->yt_url ?? ''));

        if ($channelUrl === '') {
            $this->clearYouTubeCache($user);
            return [];
        }

        $channelId = $user->youtube_channel_id;
        $uploadsPlaylistId = $user->youtube_uploads_playlist_id;

        if (! $channelId || ! $uploadsPlaylistId) {
            $resolved = $this->resolveChannelFromUrl($channelUrl);

            if (! $resolved) {
                $this->clearYouTubeCache($user);
                return [];
            }

            $channelId = $resolved['channel_id'];
            $uploadsPlaylistId = $resolved['uploads_playlist_id'];

            $user->forceFill([
                'youtube_channel_id' => $channelId,
                'youtube_uploads_playlist_id' => $uploadsPlaylistId,
            ])->save();
        }

        $videos = $this->fetchLatestVideosFromUploadsPlaylist($uploadsPlaylistId, $limit);

        $user->forceFill([
            'youtube_cached_videos' => $videos,
            'youtube_cache_refreshed_at' => now(),
        ])->save();

        return $videos;
    }

    public function getUserVideos(User $user, int $limit = 12, int $refreshDays = 3): array
    {
        $cached = is_array($user->youtube_cached_videos) ? $user->youtube_cached_videos : [];
        $refreshedAt = $user->youtube_cache_refreshed_at;

        $isFresh = $refreshedAt && $refreshedAt->gt(now()->subDays($refreshDays));

        if ($isFresh && ! empty($cached)) {
            return array_slice($cached, 0, $limit);
        }

        return $this->refreshUserVideos($user, $limit);
    }

    protected function resolveChannelFromUrl(string $url): ?array
    {
        if (preg_match('~youtube\.com/channel/([A-Za-z0-9_-]+)~i', $url, $matches)) {
            return $this->fetchChannelDetailsById($matches[1]);
        }

        if (preg_match('~youtube\.com/@([A-Za-z0-9._-]+)~i', $url, $matches)) {
            return $this->fetchChannelDetailsByHandle('@' . $matches[1]);
        }

        return null;
    }

    protected function fetchChannelDetailsByHandle(string $handle): ?array
    {
        $apiKey = config('services.youtube.key');

        $response = Http::timeout(15)->get('https://www.googleapis.com/youtube/v3/channels', [
            'key' => $apiKey,
            'part' => 'id,contentDetails',
            'forHandle' => $handle,
        ]);

        if (! $response->successful()) {
            return null;
        }

        $item = data_get($response->json(), 'items.0');

        if (! $item) {
            return null;
        }

        return [
            'channel_id' => data_get($item, 'id'),
            'uploads_playlist_id' => data_get($item, 'contentDetails.relatedPlaylists.uploads'),
        ];
    }

    protected function fetchChannelDetailsById(string $channelId): ?array
    {
        $apiKey = config('services.youtube.key');

        $response = Http::timeout(15)->get('https://www.googleapis.com/youtube/v3/channels', [
            'key' => $apiKey,
            'part' => 'id,contentDetails',
            'id' => $channelId,
        ]);

        if (! $response->successful()) {
            return null;
        }

        $item = data_get($response->json(), 'items.0');

        if (! $item) {
            return null;
        }

        return [
            'channel_id' => data_get($item, 'id'),
            'uploads_playlist_id' => data_get($item, 'contentDetails.relatedPlaylists.uploads'),
        ];
    }

    protected function fetchLatestVideosFromUploadsPlaylist(string $playlistId, int $limit = 12): array
    {
        $apiKey = config('services.youtube.key');

        $response = Http::timeout(15)->get('https://www.googleapis.com/youtube/v3/playlistItems', [
            'key' => $apiKey,
            'part' => 'snippet,contentDetails',
            'playlistId' => $playlistId,
            'maxResults' => min($limit, 50),
        ]);

        if (! $response->successful()) {
            return [];
        }

        return collect(data_get($response->json(), 'items', []))
            ->map(function ($item) {
                $videoId = data_get($item, 'contentDetails.videoId');

                if (! $videoId) {
                    return null;
                }

                return [
                    'video_id' => $videoId,
                    'title' => data_get($item, 'snippet.title'),
                    'thumbnail' => data_get($item, 'snippet.thumbnails.high.url')
                        ?: data_get($item, 'snippet.thumbnails.medium.url')
                        ?: data_get($item, 'snippet.thumbnails.default.url'),
                    'embed_url' => 'https://www.youtube.com/embed/' . $videoId . '?' . http_build_query([
                        'rel' => 0,
                        'modestbranding' => 1,
                        'playsinline' => 1,
                    ]),
                    'watch_url' => 'https://www.youtube.com/watch?v=' . $videoId,
                    'published_at' => data_get($item, 'contentDetails.videoPublishedAt'),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    protected function clearYouTubeCache(User $user): void
    {
        $user->forceFill([
            'youtube_channel_id' => null,
            'youtube_uploads_playlist_id' => null,
            'youtube_cached_videos' => null,
            'youtube_cache_refreshed_at' => null,
        ])->save();
    }
}