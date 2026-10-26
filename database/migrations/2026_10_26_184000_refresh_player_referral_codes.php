<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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

        if (! Schema::hasColumn('users', 'legacy_referral_code')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->string('legacy_referral_code', 64)->nullable()->after('referral_code');
                $table->index('legacy_referral_code', 'users_legacy_ref_code_idx');
            });
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

                    $newCode = $initials . '-' . max(1, (int) $user->id) . '-L' . max(0, (int) ($user->league_id ?? 0));
                    $current = strtoupper(trim((string) ($user->referral_code ?? '')));

                    if ($current === $newCode) {
                        continue;
                    }

                    DB::table('users')->where('id', $user->id)->update([
                        // Preserve the previously shared code as a fallback alias.
                        'legacy_referral_code' => $current !== ''
                            ? $current
                            : ($user->legacy_referral_code ?? null),
                        'referral_code' => $newCode,
                    ]);
                }
            });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('users', 'legacy_referral_code')) {
            return;
        }

        DB::table('users')
            ->select(['id', 'legacy_referral_code'])
            ->whereNotNull('legacy_referral_code')
            ->orderBy('id')
            ->chunkById(500, function ($users): void {
                foreach ($users as $user) {
                    $legacy = strtoupper(trim((string) $user->legacy_referral_code));
                    if ($legacy !== '') {
                        DB::table('users')->where('id', $user->id)->update(['referral_code' => $legacy]);
                    }
                }
            });

        Schema::table('users', function (Blueprint $table): void {
            try {
                $table->dropIndex('users_legacy_ref_code_idx');
            } catch (\Throwable) {
                // Safe for partially applied migrations.
            }
            $table->dropColumn('legacy_referral_code');
        });
    }
};
