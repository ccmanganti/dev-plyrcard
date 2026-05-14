<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('websites')) {
            return;
        }

        if (! Schema::hasColumn('websites', 'domain')) {
            Schema::table('websites', function (Blueprint $table) {
                $table->string('domain')->nullable()->after('slug');
            });
        }

        /*
         * Only copy users.domain if that old column still exists.
         * Your current local database does not have users.domain anymore,
         * so this block will safely be skipped.
         */
        if (
            Schema::hasTable('users') &&
            Schema::hasColumn('users', 'domain') &&
            Schema::hasColumn('websites', 'user_id') &&
            Schema::hasColumn('websites', 'domain')
        ) {
            $websites = DB::table('websites')
                ->join('users', 'websites.user_id', '=', 'users.id')
                ->whereNull('websites.domain')
                ->whereNotNull('users.domain')
                ->select('websites.id as website_id', 'users.domain as user_domain')
                ->get();

            foreach ($websites as $website) {
                DB::table('websites')
                    ->where('id', $website->website_id)
                    ->update([
                        'domain' => $website->user_domain,
                    ]);
            }
        }

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'domain')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('domain');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        if (! Schema::hasColumn('users', 'domain')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('domain')->nullable();
            });
        }

        if (
            Schema::hasTable('websites') &&
            Schema::hasColumn('websites', 'domain') &&
            Schema::hasColumn('websites', 'user_id') &&
            Schema::hasColumn('users', 'domain')
        ) {
            $users = DB::table('users')
                ->join('websites', 'users.id', '=', 'websites.user_id')
                ->whereNull('users.domain')
                ->whereNotNull('websites.domain')
                ->select('users.id as user_id', 'websites.domain as website_domain')
                ->get();

            foreach ($users as $user) {
                DB::table('users')
                    ->where('id', $user->user_id)
                    ->update([
                        'domain' => $user->website_domain,
                    ]);
            }
        }

        if (Schema::hasTable('websites') && Schema::hasColumn('websites', 'domain')) {
            Schema::table('websites', function (Blueprint $table) {
                $table->dropColumn('domain');
            });
        }
    }
};