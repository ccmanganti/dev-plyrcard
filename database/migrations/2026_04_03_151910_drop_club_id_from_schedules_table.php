<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            if (Schema::hasColumn('schedules', 'club_id')) {
                try {
                    $table->dropForeign(['club_id']);
                } catch (\Throwable $e) {
                    // Ignore if no foreign key exists.
                }

                $table->dropColumn('club_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->foreignId('club_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();
        });
    }
};