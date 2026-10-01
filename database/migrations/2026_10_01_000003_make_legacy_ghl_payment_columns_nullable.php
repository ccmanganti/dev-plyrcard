<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('payment_transactions')) {
            return;
        }

        // payment_transactions used to be GHL-only. Stripe transactions do not
        // have GHL IDs/timestamps/payloads, so every provider-specific GHL field
        // must be optional now that the table is a shared payment ledger.
        Schema::table('payment_transactions', function (Blueprint $table): void {
            if (Schema::hasColumn('payment_transactions', 'ghl_location_id')) {
                $table->string('ghl_location_id')->nullable()->change();
            }
            if (Schema::hasColumn('payment_transactions', 'ghl_contact_id')) {
                $table->string('ghl_contact_id')->nullable()->change();
            }
            if (Schema::hasColumn('payment_transactions', 'ghl_transaction_id')) {
                $table->string('ghl_transaction_id')->nullable()->change();
            }
            if (Schema::hasColumn('payment_transactions', 'ghl_order_id')) {
                $table->string('ghl_order_id')->nullable()->change();
            }
            if (Schema::hasColumn('payment_transactions', 'ghl_subscription_id')) {
                $table->string('ghl_subscription_id')->nullable()->change();
            }
            if (Schema::hasColumn('payment_transactions', 'ghl_charge_id')) {
                $table->string('ghl_charge_id')->nullable()->change();
            }
            if (Schema::hasColumn('payment_transactions', 'ghl_created_at')) {
                $table->dateTime('ghl_created_at')->nullable()->change();
            }
            if (Schema::hasColumn('payment_transactions', 'ghl_updated_at')) {
                $table->dateTime('ghl_updated_at')->nullable()->change();
            }
            if (Schema::hasColumn('payment_transactions', 'ghl_payload')) {
                $table->json('ghl_payload')->nullable()->change();
            }
        });
    }

    public function down(): void
    {
        // Intentionally no-op. Once Stripe rows exist, restoring NOT NULL GHL
        // fields would make those valid Stripe transactions impossible to store.
    }
};
