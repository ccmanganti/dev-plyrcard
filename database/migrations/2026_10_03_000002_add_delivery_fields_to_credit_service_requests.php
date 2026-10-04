<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('credit_service_requests')) {
            return;
        }

        Schema::table('credit_service_requests', function (Blueprint $table): void {
            if (! Schema::hasColumn('credit_service_requests', 'delivery_file_path')) {
                $table->string('delivery_file_path')->nullable()->after('last_admin_message');
            }

            if (! Schema::hasColumn('credit_service_requests', 'delivery_url')) {
                $table->text('delivery_url')->nullable()->after('delivery_file_path');
            }

            if (! Schema::hasColumn('credit_service_requests', 'delivery_notes')) {
                $table->text('delivery_notes')->nullable()->after('delivery_url');
            }

            if (! Schema::hasColumn('credit_service_requests', 'provided_at')) {
                $table->timestamp('provided_at')->nullable()->after('delivery_notes');
            }

            if (! Schema::hasColumn('credit_service_requests', 'provided_by_user_id')) {
                $table->foreignId('provided_by_user_id')
                    ->nullable()
                    ->after('provided_at')
                    ->constrained('users')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('credit_service_requests')) {
            return;
        }

        Schema::table('credit_service_requests', function (Blueprint $table): void {
            if (Schema::hasColumn('credit_service_requests', 'provided_by_user_id')) {
                $table->dropConstrainedForeignId('provided_by_user_id');
            }

            foreach ([
                'delivery_file_path',
                'delivery_url',
                'delivery_notes',
                'provided_at',
            ] as $column) {
                if (Schema::hasColumn('credit_service_requests', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
