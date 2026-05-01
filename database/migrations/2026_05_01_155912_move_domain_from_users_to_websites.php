<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('websites', function (Blueprint $table) {
            if (! Schema::hasColumn('websites', 'domain')) {
                $table->string('domain')->nullable()->after('slug')->index();
            }
        });

        if (Schema::hasColumn('users', 'domain')) {
            DB::table('websites')
                ->join('users', 'websites.user_id', '=', 'users.id')
                ->whereNull('websites.domain')
                ->whereNotNull('users.domain')
                ->update([
                    'websites.domain' => DB::raw('users.domain'),
                ]);

            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('domain');
            });
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'domain')) {
                $table->string('domain')->nullable()->after('national_team_id')->index();
            }
        });

        DB::table('users')
            ->join('websites', 'websites.user_id', '=', 'users.id')
            ->whereNull('users.domain')
            ->whereNotNull('websites.domain')
            ->update([
                'users.domain' => DB::raw('websites.domain'),
            ]);

        Schema::table('websites', function (Blueprint $table) {
            if (Schema::hasColumn('websites', 'domain')) {
                $table->dropColumn('domain');
            }
        });
    }
};