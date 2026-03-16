<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('plyrcard_image')->nullable()->after('domain');
            $table->string('player_image')->nullable()->after('plyrcard_image');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'plyrcard_image',
                'player_image',
            ]);
        });
    }
};