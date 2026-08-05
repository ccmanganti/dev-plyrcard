<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coach_ghl_sync_runs', function (Blueprint $table): void {
            $table->unsignedInteger('school_created_count')->default(0)->after('unchanged_count');
            $table->unsignedInteger('school_updated_count')->default(0)->after('school_created_count');
            $table->unsignedInteger('school_unchanged_count')->default(0)->after('school_updated_count');
            $table->unsignedInteger('school_failed_count')->default(0)->after('school_unchanged_count');
        });
    }

    public function down(): void
    {
        Schema::table('coach_ghl_sync_runs', function (Blueprint $table): void {
            $table->dropColumn([
                'school_created_count',
                'school_updated_count',
                'school_unchanged_count',
                'school_failed_count',
            ]);
        });
    }
};
