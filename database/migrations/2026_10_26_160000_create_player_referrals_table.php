<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'referral_code')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->string('referral_code', 64)->nullable()->after('email');
                $table->unique('referral_code', 'users_ref_code_uq');
            });
        }

        // Backfill deterministic codes for existing players so referral links work
        // immediately after deployment, not only after a player revisits Dashboard.
        if (Schema::hasColumn('users', 'referral_code')) {
            DB::table('users')
                ->select(['id', 'first_name'])
                ->whereNull('referral_code')
                ->orderBy('id')
                ->chunkById(500, function ($users): void {
                    foreach ($users as $user) {
                        $first = Str::of((string) ($user->first_name ?: 'PLYR'))
                            ->ascii()
                            ->upper()
                            ->replaceMatches('/[^A-Z0-9]/', '')
                            ->limit(20, '')
                            ->toString();
                        $first = $first !== '' ? $first : 'PLYR';
                        $suffix = strtoupper(base_convert((string) max(1, (int) $user->id), 10, 36));

                        DB::table('users')
                            ->where('id', $user->id)
                            ->update(['referral_code' => $first . '-' . $suffix]);
                    }
                });
        }

        if (! Schema::hasTable('player_referrals')) {
            Schema::create('player_referrals', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('inviter_user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('invitee_user_id')->unique('plyr_ref_invitee_uq')->constrained('users')->cascadeOnDelete();
                $table->string('referral_code', 64);
                $table->string('invitee_plan_key', 32)->default('free');
                $table->string('status', 24)->default('registered');
                $table->timestamp('registered_at')->nullable();
                $table->timestamp('qualified_at')->nullable();
                $table->unsignedInteger('inviter_points_awarded')->default(0);
                $table->unsignedInteger('invitee_points_awarded')->default(0);
                $table->json('meta')->nullable();
                $table->timestamps();

                $table->index(['inviter_user_id', 'status'], 'plyr_ref_inviter_status_idx');
                $table->index('referral_code', 'plyr_ref_code_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('player_referrals');

        if (Schema::hasColumn('users', 'referral_code')) {
            Schema::table('users', function (Blueprint $table): void {
                try {
                    $table->dropUnique('users_ref_code_uq');
                } catch (\Throwable) {
                    // The index may not exist on a partially applied migration.
                }
                $table->dropColumn('referral_code');
            });
        }
    }
};
