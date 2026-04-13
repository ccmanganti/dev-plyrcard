<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clubs', function (Blueprint $table) {
            $table->string('primary_color')->nullable()->after('logo');
            $table->string('secondary_color')->nullable()->after('primary_color');
            $table->string('city')->nullable()->after('secondary_color');
            $table->string('state')->nullable()->after('city');
            $table->foreignId('conference_id')
                ->nullable()
                ->after('state')
                ->constrained()
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('clubs', function (Blueprint $table) {
            $table->dropForeign(['conference_id']);
            $table->dropColumn([
                'primary_color',
                'secondary_color',
                'city',
                'state',
                'conference_id',
            ]);
        });
    }
};