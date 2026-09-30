<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = 'billing_information';

        $missing = collect([
            'stripe_customer_id',
            'stripe_subscription_id',
            'stripe_invoice_id',
            'stripe_payment_intent_id',
            'stripe_payment_method_id',
            'stripe_recurring_price_id',
            'stripe_setup_price_id',
            'stripe_last_event_id',
            'stripe_last_event_at',
            'stripe_synced_at',
        ])->filter(fn (string $column): bool => ! Schema::hasColumn($tableName, $column))->all();

        if ($missing === []) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($missing): void {
            foreach ([
                'stripe_customer_id',
                'stripe_subscription_id',
                'stripe_invoice_id',
                'stripe_payment_intent_id',
                'stripe_payment_method_id',
                'stripe_recurring_price_id',
                'stripe_setup_price_id',
                'stripe_last_event_id',
            ] as $column) {
                if (in_array($column, $missing, true)) {
                    $table->string($column)->nullable()->index();
                }
            }

            if (in_array('stripe_last_event_at', $missing, true)) {
                $table->timestamp('stripe_last_event_at')->nullable();
            }

            if (in_array('stripe_synced_at', $missing, true)) {
                $table->timestamp('stripe_synced_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        $tableName = 'billing_information';
        $existing = collect([
            'stripe_customer_id',
            'stripe_subscription_id',
            'stripe_invoice_id',
            'stripe_payment_intent_id',
            'stripe_payment_method_id',
            'stripe_recurring_price_id',
            'stripe_setup_price_id',
            'stripe_last_event_id',
            'stripe_last_event_at',
            'stripe_synced_at',
        ])->filter(fn (string $column): bool => Schema::hasColumn($tableName, $column))->all();

        if ($existing !== []) {
            Schema::table($tableName, fn (Blueprint $table) => $table->dropColumn($existing));
        }
    }
};
