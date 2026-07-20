<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coach_database_email_templates', function (Blueprint $table): void {
            if (! Schema::hasColumn('coach_database_email_templates', 'is_locked')) {
                $table->boolean('is_locked')->default(false)->after('is_sample')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('coach_database_email_templates', function (Blueprint $table): void {
            if (Schema::hasColumn('coach_database_email_templates', 'is_locked')) {
                $table->dropColumn('is_locked');
            }
        });
    }
};