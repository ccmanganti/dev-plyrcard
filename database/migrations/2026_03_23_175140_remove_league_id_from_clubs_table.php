<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clubs', function (Blueprint $table) {
            if (Schema::hasColumn('clubs', 'league_id')) {
                $table->dropForeign(['league_id']);
                $table->dropColumn('league_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('clubs', function (Blueprint $table) {
            if (! Schema::hasColumn('clubs', 'league_id')) {
                $table->unsignedBigInteger('league_id')->nullable()->after('name');

                $table->foreign('league_id')
                    ->references('id')
                    ->on('leagues')
                    ->nullOnDelete();
            }
        });
    }
};