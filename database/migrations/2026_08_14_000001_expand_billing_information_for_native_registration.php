<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billing_information', function (Blueprint $table): void {
            $table->string('plan_key', 50)->nullable()->index();
            $table->string('billing_cycle', 30)->nullable();
            $table->char('currency', 3)->default('USD');
            $table->unsignedInteger('recurring_amount_cents')->nullable();
            $table->unsignedInteger('setup_fee_cents')->nullable();
            $table->unsignedInteger('initial_amount_cents')->nullable();

            $table->string('payment_status', 50)->nullable()->index();
            $table->string('subscription_status', 50)->nullable()->index();
            $table->string('payment_provider', 50)->nullable();
            $table->string('payment_brand', 50)->nullable();

            $table->string('requested_domain')->nullable()->index();
            $table->string('requested_handle')->nullable()->index();
            $table->json('registration_meta')->nullable();

            $table->string('ghl_location_id')->nullable()->index();
            $table->string('ghl_invoice_id')->nullable()->index();
            $table->string('ghl_invoice_schedule_id')->nullable()->index();
            $table->string('ghl_subscription_id')->nullable()->index();
            $table->string('ghl_transaction_id')->nullable()->index();
            $table->string('ghl_payment_method_id')->nullable();
            $table->string('ghl_customer_id')->nullable();
            $table->string('ghl_last_webhook_id')->nullable()->index();

            $table->timestamp('ghl_payment_completed_at')->nullable();
            $table->timestamp('ghl_last_event_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('billing_information', function (Blueprint $table): void {
            $table->dropColumn([
                'plan_key',
                'billing_cycle',
                'currency',
                'recurring_amount_cents',
                'setup_fee_cents',
                'initial_amount_cents',
                'payment_status',
                'subscription_status',
                'payment_provider',
                'payment_brand',
                'requested_domain',
                'requested_handle',
                'registration_meta',
                'ghl_location_id',
                'ghl_invoice_id',
                'ghl_invoice_schedule_id',
                'ghl_subscription_id',
                'ghl_transaction_id',
                'ghl_payment_method_id',
                'ghl_customer_id',
                'ghl_last_webhook_id',
                'ghl_payment_completed_at',
                'ghl_last_event_at',
            ]);
        });
    }
};
