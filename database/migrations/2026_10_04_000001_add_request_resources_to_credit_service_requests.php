<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('credit_service_requests', 'request_resources')) {
            Schema::table('credit_service_requests', function (Blueprint $table): void {
                $table->json('request_resources')->nullable()->after('notes');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('credit_service_requests', 'request_resources')) {
            Schema::table('credit_service_requests', function (Blueprint $table): void {
                $table->dropColumn('request_resources');
            });
        }
    }
};
