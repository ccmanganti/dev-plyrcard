<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'email_verified_at')) {
                $table->timestamp('email_verified_at')->nullable()->after('email');
            }
            if (! Schema::hasColumn('users', 'email_verification_sent_at')) {
                $table->timestamp('email_verification_sent_at')->nullable()->after('email_verified_at');
            }
            if (! Schema::hasColumn('users', 'team_id')) {
                $table->foreignId('team_id')->nullable()->after('club_league_id')->constrained('teams')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'team_id')) {
                $table->dropConstrainedForeignId('team_id');
            }
            if (Schema::hasColumn('users', 'email_verification_sent_at')) {
                $table->dropColumn('email_verification_sent_at');
            }
        });
    }
};
