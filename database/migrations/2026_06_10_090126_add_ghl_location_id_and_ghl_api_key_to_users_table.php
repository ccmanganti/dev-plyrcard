<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('ghl_location_id')->nullable()->after('ghl_contact_id');
            $table->text('ghl_api_key')->nullable()->after('ghl_location_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'ghl_location_id',
                'ghl_api_key',
            ]);
        });
    }
};