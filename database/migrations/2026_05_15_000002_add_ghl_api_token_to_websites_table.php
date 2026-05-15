<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('websites')) {
            return;
        }

        Schema::table('websites', function (Blueprint $table) {
            if (! Schema::hasColumn('websites', 'ghl_api_token')) {
                $table->text('ghl_api_token')->nullable()->after('ghl_location_id');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('websites') || ! Schema::hasColumn('websites', 'ghl_api_token')) {
            return;
        }

        Schema::table('websites', function (Blueprint $table) {
            $table->dropColumn('ghl_api_token');
        });
    }
};
