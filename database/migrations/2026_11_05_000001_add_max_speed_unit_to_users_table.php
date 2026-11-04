<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'max_speed_unit')) {
                $table->string('max_speed_unit', 8)->default('mph')->after('max_speed');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'max_speed_unit')) {
                $table->dropColumn('max_speed_unit');
            }
        });
    }
};
