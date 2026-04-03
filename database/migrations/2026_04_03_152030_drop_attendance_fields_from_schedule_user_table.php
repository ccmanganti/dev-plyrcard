<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedule_user', function (Blueprint $table) {
            if (Schema::hasColumn('schedule_user', 'will_come')) {
                $table->dropColumn('will_come');
            }

            if (Schema::hasColumn('schedule_user', 'responded_at')) {
                $table->dropColumn('responded_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('schedule_user', function (Blueprint $table) {
            $table->boolean('will_come')->default(true);
            $table->timestamp('responded_at')->nullable();
        });
    }
};
