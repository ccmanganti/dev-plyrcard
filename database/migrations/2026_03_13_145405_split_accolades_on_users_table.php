<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->longText('academic_accolades')->nullable()->after('position');
            $table->longText('sports_accolades')->nullable()->after('academic_accolades');
        });

        DB::table('users')
            ->whereNotNull('accolades')
            ->update([
                'sports_accolades' => DB::raw('accolades'),
            ]);

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('accolades');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->longText('accolades')->nullable()->after('position');
        });

        DB::table('users')
            ->update([
                'accolades' => DB::raw('COALESCE(sports_accolades, academic_accolades)'),
            ]);

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['academic_accolades', 'sports_accolades']);
        });
    }
};