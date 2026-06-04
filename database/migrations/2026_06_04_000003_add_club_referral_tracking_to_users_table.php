<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'club_referral_id')) {
                $table->foreignId('club_referral_id')->nullable()->after('club_league_id')->constrained('club_referrals')->nullOnDelete();
            }

            if (! Schema::hasColumn('users', 'registration_source')) {
                $table->string('registration_source')->nullable()->after('club_referral_id');
            }

            if (! Schema::hasColumn('users', 'utm_club_id')) {
                $table->foreignId('utm_club_id')->nullable()->after('registration_source')->constrained('clubs')->nullOnDelete();
            }

            if (! Schema::hasColumn('users', 'utm_league_id')) {
                $table->foreignId('utm_league_id')->nullable()->after('utm_club_id')->constrained('leagues')->nullOnDelete();
            }

            if (! Schema::hasColumn('users', 'utm_team_name')) {
                $table->string('utm_team_name')->nullable()->after('utm_league_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            foreach ([
                'utm_team_name',
                'utm_league_id',
                'utm_club_id',
                'registration_source',
                'club_referral_id',
            ] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
