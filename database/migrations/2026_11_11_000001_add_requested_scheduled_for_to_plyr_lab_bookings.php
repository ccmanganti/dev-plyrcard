<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plyr_lab_bookings', function (Blueprint $table): void {
            $table->dateTime('requested_scheduled_for')->nullable()->after('scheduled_for');
        });
    }

    public function down(): void
    {
        Schema::table('plyr_lab_bookings', function (Blueprint $table): void {
            $table->dropColumn('requested_scheduled_for');
        });
    }
};
