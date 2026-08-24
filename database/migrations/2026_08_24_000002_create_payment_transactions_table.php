<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payment_transactions')) {
            return;
        }

        Schema::create('payment_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('billing_information_id')->nullable()->constrained('billing_information')->nullOnDelete();
            $table->string('plan_key', 50)->nullable()->index();

            $table->string('ghl_location_id')->nullable()->index();
            $table->string('ghl_contact_id')->nullable()->index();
            $table->string('ghl_transaction_id')->unique();
            $table->string('ghl_order_id')->nullable()->index();
            $table->string('ghl_subscription_id')->nullable()->index();
            $table->string('ghl_charge_id')->nullable()->index();

            $table->string('status', 50)->nullable()->index();
            $table->char('currency', 3)->nullable();
            $table->unsignedBigInteger('amount_cents')->default(0);
            $table->unsignedBigInteger('refunded_amount_cents')->default(0);
            $table->string('payment_provider', 80)->nullable();
            $table->string('payment_mode', 50)->nullable();
            $table->boolean('live_mode')->nullable()->index();

            // Safe display metadata only. Never store full card numbers or CVC.
            $table->string('card_brand', 50)->nullable();
            $table->string('card_last_four', 4)->nullable();

            $table->string('entity_type', 80)->nullable();
            $table->string('entity_id')->nullable()->index();
            $table->string('source_type', 100)->nullable();
            $table->string('source_sub_type', 100)->nullable();
            $table->string('source_name')->nullable();

            $table->timestamp('ghl_created_at')->nullable()->index();
            $table->timestamp('ghl_updated_at')->nullable();
            $table->timestamp('paid_at')->nullable()->index();
            $table->timestamp('synced_at')->nullable()->index();
            $table->json('ghl_payload')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'paid_at']);
            $table->index(['billing_information_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_transactions');
    }
};
