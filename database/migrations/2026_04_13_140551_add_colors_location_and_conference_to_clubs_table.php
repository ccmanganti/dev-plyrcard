<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clubs', function (Blueprint $table) {
            if (!Schema::hasColumn('clubs', 'primary_color')) {
                $table->string('primary_color')->nullable()->after('logo');
            }

            if (!Schema::hasColumn('clubs', 'secondary_color')) {
                $table->string('secondary_color')->nullable()->after('primary_color');
            }

            if (!Schema::hasColumn('clubs', 'city')) {
                $table->string('city')->nullable()->after('secondary_color');
            }

            if (!Schema::hasColumn('clubs', 'state')) {
                $table->string('state')->nullable()->after('city');
            }

            if (!Schema::hasColumn('clubs', 'conference_id')) {
                $table->foreignId('conference_id')
                    ->nullable()
                    ->after('state')
                    ->constrained('conferences')
                    ->nullOnDelete();
            }
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