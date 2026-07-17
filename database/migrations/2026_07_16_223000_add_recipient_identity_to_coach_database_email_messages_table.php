<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coach_database_email_messages', function (Blueprint $table): void {
            if (! Schema::hasColumn('coach_database_email_messages', 'recipient_name')) {
                $table->string('recipient_name')->nullable()->after('recipient_email');
            }
            if (! Schema::hasColumn('coach_database_email_messages', 'school_name')) {
                $table->string('school_name')->nullable()->after('recipient_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('coach_database_email_messages', function (Blueprint $table): void {
            $drop = [];
            if (Schema::hasColumn('coach_database_email_messages', 'recipient_name')) $drop[] = 'recipient_name';
            if (Schema::hasColumn('coach_database_email_messages', 'school_name')) $drop[] = 'school_name';
            if ($drop !== []) $table->dropColumn($drop);
        });
    }
};
