<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\CoachDatabaseService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class SyncRecruitingStats extends Command
{
    protected $signature = 'recruiting:sync-stats
        {--user= : Sync one user ID only}
        {--all : Sync all users with a GHL connection}
        {--force : Force refresh even if a recent cache exists}
        {--release-lock : Release the per-user background sync lock when finished}';

    protected $description = 'Sync Recruiting Center stats from GHL custom fields into the dashboard cache.';

    public function handle(CoachDatabaseService $coachDatabaseService): int
    {
        $userId = $this->option('user');
        $all = (bool) $this->option('all');
        $force = (bool) $this->option('force');
        $releaseLock = (bool) $this->option('release-lock');

        if (! $userId && ! $all) {
            $this->error('Pass --user={id} or --all.');
            return self::FAILURE;
        }

        $query = User::query()
            ->where(function ($query): void {
                $query->whereNotNull('ghl_location_id')
                    ->orWhereNotNull('ghl_api_key');
            });

        if ($userId) {
            $query->whereKey((int) $userId);
        }

        $processed = 0;
        $synced = 0;
        $failed = 0;
        $skipped = 0;

        $query->orderBy('id')->chunkById(50, function ($users) use ($coachDatabaseService, $force, &$processed, &$synced, &$failed, &$skipped): void {
            foreach ($users as $user) {
                $processed++;

                if (! $coachDatabaseService->canAccess($user) || ! $coachDatabaseService->hasGhlConnection($user)) {
                    $skipped++;
                    if ($releaseLock) {
                        Cache::forget('recruiting:stats-sync-running:' . $user->id);
                    }
                    $this->line("Skipped user {$user->id}: no access or missing GHL connection.");
                    continue;
                }

                $result = $coachDatabaseService->syncRecruitingStatsForUser($user, $force);

                if ($result['success'] ?? false) {
                    $synced++;
                    $coachCount = count($result['coaches'] ?? []);
                    $schoolCount = count($result['schools'] ?? []);
                    $emailSent = (int) ($result['stats']['email_sent_count'] ?? 0);
                    $profileViews = (int) ($result['stats']['profile_views'] ?? $result['stats']['view_profile_total'] ?? 0);
                    $clicks = (int) ($result['stats']['trigger_link_clicks'] ?? 0);

                    Cache::put('recruiting:stats-sync-status:' . $user->id, [
                        'status' => 'completed',
                        'completed_at' => now()->toDateTimeString(),
                        'user_id' => $user->id,
                        'coaches' => $coachCount,
                        'schools' => $schoolCount,
                        'email_sent_count' => $emailSent,
                        'profile_views' => $profileViews,
                        'clicks' => $clicks,
                        'csv_path' => $result['stats_csv_path'] ?? null,
                    ], now()->addMinutes(30));

                    $this->info("Synced user {$user->id}: {$coachCount} coaches, {$schoolCount} schools, {$emailSent} emails sent, {$profileViews} profile views, {$clicks} clicks.");

                    if ($releaseLock) {
                        Cache::forget('recruiting:stats-sync-running:' . $user->id);
                    }

                    continue;
                }

                $failed++;

                Cache::put('recruiting:stats-sync-status:' . $user->id, [
                    'status' => 'failed',
                    'failed_at' => now()->toDateTimeString(),
                    'user_id' => $user->id,
                    'error' => $result['error'] ?? $result['reason'] ?? 'Unknown error',
                ], now()->addMinutes(30));

                if ($releaseLock) {
                    Cache::forget('recruiting:stats-sync-running:' . $user->id);
                }

                $this->warn("Failed user {$user->id}: " . ($result['error'] ?? $result['reason'] ?? 'Unknown error'));
            }
        });

        $this->newLine();
        $this->info("Done. Processed: {$processed}. Synced: {$synced}. Skipped: {$skipped}. Failed: {$failed}.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}