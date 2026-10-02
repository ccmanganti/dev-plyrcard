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
            if (! Schema::hasColumn('credit_service_requests', 'credits_returned')) {
                $table->unsignedInteger('credits_returned')->default(0)->after('points_spent');
            }

            if (! Schema::hasColumn('credit_service_requests', 'admin_notes')) {
                $table->text('admin_notes')->nullable()->after('status');
            }

            if (! Schema::hasColumn('credit_service_requests', 'managed_by_user_id')) {
                $table->foreignId('managed_by_user_id')
                    ->nullable()
                    ->after('admin_notes')
                    ->constrained('users')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('credit_service_requests', 'reviewed_at')) {
                $table->timestamp('reviewed_at')->nullable()->after('managed_by_user_id');
            }

            if (! Schema::hasColumn('credit_service_requests', 'completed_at')) {
                $table->timestamp('completed_at')->nullable()->after('reviewed_at');
            }

            if (! Schema::hasColumn('credit_service_requests', 'admin_contacted_at')) {
                $table->timestamp('admin_contacted_at')->nullable()->after('completed_at');
            }

            if (! Schema::hasColumn('credit_service_requests', 'last_admin_subject')) {
                $table->string('last_admin_subject', 255)->nullable()->after('admin_contacted_at');
            }

            if (! Schema::hasColumn('credit_service_requests', 'last_admin_message')) {
                $table->text('last_admin_message')->nullable()->after('last_admin_subject');
            }

            if (! Schema::hasColumn('credit_service_requests', 'email_alerted_at')) {
                $table->timestamp('email_alerted_at')->nullable()->after('last_admin_message');
            }

            if (! Schema::hasColumn('credit_service_requests', 'email_alert_status')) {
                $table->string('email_alert_status', 40)->nullable()->after('email_alerted_at');
            }

            if (! Schema::hasColumn('credit_service_requests', 'email_alert_error')) {
                $table->text('email_alert_error')->nullable()->after('email_alert_status');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('credit_service_requests')) {
            return;
        }

        Schema::table('credit_service_requests', function (Blueprint $table): void {
            if (Schema::hasColumn('credit_service_requests', 'managed_by_user_id')) {
                $table->dropConstrainedForeignId('managed_by_user_id');
            }

            foreach ([
                'credits_returned',
                'admin_notes',
                'reviewed_at',
                'completed_at',
                'admin_contacted_at',
                'last_admin_subject',
                'last_admin_message',
                'email_alerted_at',
                'email_alert_status',
                'email_alert_error',
            ] as $column) {
                if (Schema::hasColumn('credit_service_requests', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
