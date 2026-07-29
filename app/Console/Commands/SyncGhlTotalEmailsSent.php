<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\GoHighLevelService;
use Illuminate\Console\Command;

class SyncGhlTotalEmailsSent extends Command
{
    protected $signature = 'recruiting:sync-total-emails-sent
        {--user=* : One or more local user IDs}
        {--all : Sync every user with a GHL connection}
        {--max-conversations=1000 : Maximum conversations per user}
        {--max-message-pages=100 : Maximum message pages per conversation}';

    protected $description = 'Occasionally reconcile users.total_emails_sent from outbound HighLevel conversation emails.';

    public function handle(GoHighLevelService $goHighLevelService): int
    {
        $userIds = collect((array) $this->option('user'))
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values();

        if (! $this->option('all') && $userIds->isEmpty()) {
            $this->error('Pass at least one --user=ID or use --all.');

            return self::INVALID;
        }

        $query = User::query()
            ->whereNotNull('ghl_location_id')
            ->where('ghl_location_id', '!=', '')
            ->whereNotNull('ghl_api_key')
            ->where('ghl_api_key', '!=', '');

        if (! $this->option('all')) {
            $query->whereIn('id', $userIds->all());
        }

        if (! (clone $query)->exists()) {
            $this->warn('No matching users with a complete GHL connection were found.');

            return self::SUCCESS;
        }

        $failed = 0;
        $processed = 0;

        $query->orderBy('id')->chunkById(25, function ($users) use ($goHighLevelService, &$failed, &$processed): void {
            foreach ($users as $user) {
                $processed++;
                $this->line("Syncing user #{$user->id} {$user->email}...");

                $result = $goHighLevelService->syncTotalEmailsSentForUser(
                    $user,
                    (int) $this->option('max-conversations'),
                    (int) $this->option('max-message-pages'),
                );

                if (! ($result['success'] ?? false)) {
                    $failed++;
                    $this->error((string) ($result['error'] ?? 'Unknown synchronization error.'));
                    continue;
                }

                $this->info(sprintf(
                    'Saved %s sent emails from %s conversations (%s message rows scanned).',
                    number_format((int) ($result['total_emails_sent'] ?? 0)),
                    number_format((int) ($result['conversations_scanned'] ?? 0)),
                    number_format((int) ($result['messages_scanned'] ?? 0)),
                ));
            }
        });

        $this->newLine();
        $this->line(sprintf('Processed %s user(s); %s failed.', number_format($processed), number_format($failed)));

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}