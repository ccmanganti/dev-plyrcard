<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coaches', function (Blueprint $table): void {
            $table->string('division')->nullable()->index()->after('sport');
            $table->string('conference')->nullable()->index()->after('division');
            $table->string('verification_status', 100)->nullable()->after('conference');
            $table->string('confidence_level', 100)->nullable()->after('verification_status');
            $table->text('audit_notes')->nullable()->after('confidence_level');
        });
    }

    public function down(): void
    {
        Schema::table('coaches', function (Blueprint $table): void {
            $table->dropColumn(['division', 'conference', 'verification_status', 'confidence_level', 'audit_notes']);
        });
    }
};
