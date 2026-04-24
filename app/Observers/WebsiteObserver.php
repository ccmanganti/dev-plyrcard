<?php

namespace App\Observers;

use App\Models\Website;
use App\Services\GoHighLevelService;
use Illuminate\Support\Facades\Log;

class WebsiteObserver
{
    public function updated(Website $website): void
    {
        if (
            ! $website->wasChanged('is_published') ||
            ! $website->is_published ||
            ! is_null($website->published_notification_sent_at)
        ) {
            return;
        }

        $user = $website->user;

        if (! $user || ! $user->email) {
            Log::warning('GHL site published sync skipped. Website has no valid user/email.', [
                'website_id' => $website->id,
                'user_id' => $website->user_id,
            ]);

            return;
        }

        $synced = app(GoHighLevelService::class)->syncSitePublished($user, $website);

        if ($synced) {
            $website->forceFill([
                'published_notification_sent_at' => now(),
            ])->saveQuietly();
        }
    }
}