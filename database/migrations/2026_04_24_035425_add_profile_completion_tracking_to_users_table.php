<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedTinyInteger('profile_completion_percentage')->default(0)->after('ghl_contact_id');
            $table->timestamp('profile_completion_threshold_sent_at')->nullable()->after('profile_completion_percentage');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'profile_completion_percentage',
                'profile_completion_threshold_sent_at',
            ]);
        });
    }
};