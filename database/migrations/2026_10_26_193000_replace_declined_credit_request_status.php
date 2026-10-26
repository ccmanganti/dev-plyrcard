<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('credit_service_requests') || ! Schema::hasColumn('credit_service_requests', 'status')) {
            return;
        }

        DB::table('credit_service_requests')
            ->where('status', 'declined')
            ->update([
                'status' => 'pending_information',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('credit_service_requests') || ! Schema::hasColumn('credit_service_requests', 'status')) {
            return;
        }

        DB::table('credit_service_requests')
            ->where('status', 'pending_information')
            ->update([
                'status' => 'declined',
                'updated_at' => now(),
            ]);
    }
};
