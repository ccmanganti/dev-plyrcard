<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'title')) {
                $table->string('title')->nullable()->after('last_name');
            }

            if (! Schema::hasColumn('users', 'club_manager_created_at')) {
                $table->timestamp('club_manager_created_at')->nullable()->after('title');
            }

            if (! Schema::hasColumn('users', 'coach_account_credentials_sent_at')) {
                $table->timestamp('coach_account_credentials_sent_at')->nullable()->after('club_manager_created_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            foreach ([
                'coach_account_credentials_sent_at',
                'club_manager_created_at',
                'title',
            ] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
