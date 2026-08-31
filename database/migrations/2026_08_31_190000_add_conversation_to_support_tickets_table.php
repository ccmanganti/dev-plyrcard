<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('support_tickets', 'conversation')) {
            Schema::table('support_tickets', function (Blueprint $table) {
                $table->json('conversation')->nullable()->after('message');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('support_tickets', 'conversation')) {
            Schema::table('support_tickets', function (Blueprint $table) {
                $table->dropColumn('conversation');
            });
        }
    }
};
