<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('club_referrals')) {
            return;
        }

        Schema::table('club_referrals', function (Blueprint $table): void {
            if (! Schema::hasColumn('club_referrals', 'token')) {
                $table->string('token')->nullable()->unique()->after('id');
            }

            if (! Schema::hasColumn('club_referrals', 'invite_url')) {
                $table->text('invite_url')->nullable()->after('token');
            }

            if (! Schema::hasColumn('club_referrals', 'click_count')) {
                $table->unsignedInteger('click_count')->default(0)->after('invite_url');
            }

            if (! Schema::hasColumn('club_referrals', 'sent_at')) {
                $table->timestamp('sent_at')->nullable()->after('click_count');
            }

            if (! Schema::hasColumn('club_referrals', 'clicked_at')) {
                $table->timestamp('clicked_at')->nullable()->after('sent_at');
            }

            if (! Schema::hasColumn('club_referrals', 'accepted_at')) {
                $table->timestamp('accepted_at')->nullable()->after('clicked_at');
            }

            if (! Schema::hasColumn('club_referrals', 'registered_user_id')) {
                $table->foreignId('registered_user_id')->nullable()->after('accepted_at')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('club_referrals', 'expires_at')) {
                $table->timestamp('expires_at')->nullable()->after('registered_user_id');
            }

            if (! Schema::hasColumn('club_referrals', 'meta')) {
                $table->json('meta')->nullable()->after('expires_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('club_referrals')) {
            return;
        }

        Schema::table('club_referrals', function (Blueprint $table): void {
            foreach ([
                'meta',
                'expires_at',
                'registered_user_id',
                'accepted_at',
                'clicked_at',
                'sent_at',
                'click_count',
                'invite_url',
                'token',
            ] as $column) {
                if (Schema::hasColumn('club_referrals', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
