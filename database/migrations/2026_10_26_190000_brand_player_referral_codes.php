<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'referral_code')) {
            return;
        }

        DB::table('users')
            ->select(['id', 'first_name', 'last_name', 'league_id', 'referral_code', 'legacy_referral_code'])
            ->orderBy('id')
            ->chunkById(500, function ($users): void {
                foreach ($users as $user) {
                    $first = Str::of((string) ($user->first_name ?? ''))
                        ->ascii()->upper()->replaceMatches('/[^A-Z0-9]/', '')->toString();
                    $last = Str::of((string) ($user->last_name ?? ''))
                        ->ascii()->upper()->replaceMatches('/[^A-Z0-9]/', '')->toString();

                    $initials = mb_substr($first, 0, 1) . mb_substr($last, 0, 1);
                    if (mb_strlen($initials) < 2) {
                        $initials = mb_substr($first . $last . 'PL', 0, 2);
                    }
                    $initials = $initials !== '' ? $initials : 'PL';

                    $newCode = 'PLYR-' . $initials . max(1, (int) $user->id) . 'L' . max(0, (int) ($user->league_id ?? 0));
                    $current = strtoupper(trim((string) ($user->referral_code ?? '')));

                    if ($current === $newCode) {
                        continue;
                    }

                    $updates = ['referral_code' => $newCode];

                    // Keep the earliest stored legacy alias intact. The immediately
                    // previous INITIALS-ID-L# format is resolved dynamically by the
                    // referral service, so we do not need to overwrite this column.
                    if (Schema::hasColumn('users', 'legacy_referral_code')
                        && trim((string) ($user->legacy_referral_code ?? '')) === ''
                        && $current !== '') {
                        $updates['legacy_referral_code'] = $current;
                    }

                    DB::table('users')->where('id', $user->id)->update($updates);
                }
            });
    }

    public function down(): void
    {
        // Do not automatically rewrite referral codes on rollback. Previously
        // shared aliases remain resolvable by PlayerReferralService.
    }
};
