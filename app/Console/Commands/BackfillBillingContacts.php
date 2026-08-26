<?php

namespace App\Console\Commands;

use App\Models\BillingInformation;
use App\Services\BillingAccountService;
use Illuminate\Console\Command;

class BackfillBillingContacts extends Command
{
    protected $signature = 'plyrcard:billing-backfill-contacts {--user= : Only process one user ID} {--refresh : Also refresh subscription payment identity when available}';

    protected $description = 'Connect existing PLYRCARD billing profiles to their payer/subscriber contact in the PLYRCARD billing account.';

    public function handle(BillingAccountService $billingAccount): int
    {
        $query = BillingInformation::query()->with('user')->orderBy('id');

        if ($userId = $this->option('user')) {
            $query->where('user_id', $userId);
        } else {
            $query->where(function ($q) {
                $q->whereNull('ghl_contact_id')->orWhere('ghl_contact_id', '');
            });
        }

        $processed = 0;
        $connected = 0;
        $failed = 0;

        $query->chunkById(100, function ($rows) use ($billingAccount, &$processed, &$connected, &$failed) {
            foreach ($rows as $billing) {
                $processed++;
                $user = $billing->user;

                if (! $user) {
                    $failed++;
                    $this->warn("Billing #{$billing->id}: user not found.");
                    continue;
                }

                $contactId = $billingAccount->ensureBillingContact($user, $billing);

                if (! $contactId) {
                    $failed++;
                    $billing->forceFill(['ghl_sync_status' => 'failed'])->save();
                    $this->warn("User #{$user->id}: billing account could not be connected.");
                    continue;
                }

                $billing->forceFill([
                    'ghl_contact_id' => $contactId,
                    'ghl_location_id' => $billing->ghl_location_id ?: config('ghl.location_id'),
                    'ghl_sync_status' => 'synced',
                    'ghl_synced_at' => now(),
                ])->save();

                if ($this->option('refresh') && filled($billing->ghl_subscription_id)) {
                    $billingAccount->refreshPaymentIdentity($billing);
                }

                $connected++;
                $this->line("User #{$user->id}: connected billing account.");
            }
        });

        $this->newLine();
        $this->info("Processed: {$processed}; connected: {$connected}; failed: {$failed}");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
