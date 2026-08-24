<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('billing_information', 'ghl_order_id')) {
            Schema::table('billing_information', function (Blueprint $table): void {
                $table->string('ghl_order_id')->nullable()->index()->after('ghl_invoice_schedule_id');
            });
        }

        if (! Schema::hasColumn('billing_information', 'amount_paid_cents')) {
            Schema::table('billing_information', function (Blueprint $table): void {
                $table->unsignedBigInteger('amount_paid_cents')->nullable()->after('initial_amount_cents');
            });
        }

        if (! Schema::hasColumn('billing_information', 'amount_refunded_cents')) {
            Schema::table('billing_information', function (Blueprint $table): void {
                $table->unsignedBigInteger('amount_refunded_cents')->nullable()->after('amount_paid_cents');
            });
        }

        if (! Schema::hasColumn('billing_information', 'payment_mode')) {
            Schema::table('billing_information', function (Blueprint $table): void {
                $table->string('payment_mode', 50)->nullable()->after('payment_provider');
            });
        }

        if (! Schema::hasColumn('billing_information', 'payment_live_mode')) {
            Schema::table('billing_information', function (Blueprint $table): void {
                $table->boolean('payment_live_mode')->nullable()->after('payment_mode');
            });
        }

        if (! Schema::hasColumn('billing_information', 'payment_synced_at')) {
            Schema::table('billing_information', function (Blueprint $table): void {
                $table->timestamp('payment_synced_at')->nullable()->after('ghl_last_event_at');
            });
        }
    }

    public function down(): void
    {
        $columns = collect([
            'ghl_order_id',
            'amount_paid_cents',
            'amount_refunded_cents',
            'payment_mode',
            'payment_live_mode',
            'payment_synced_at',
        ])->filter(fn (string $column): bool => Schema::hasColumn('billing_information', $column))->all();

        if ($columns !== []) {
            Schema::table('billing_information', function (Blueprint $table) use ($columns): void {
                $table->dropColumn($columns);
            });
        }
    }
};
