<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('payment_transactions')) return;
        $cols = ['stripe_invoice_id','stripe_payment_intent_id','stripe_charge_id','stripe_subscription_id','stripe_customer_id','stripe_payload'];
        Schema::table('payment_transactions', function (Blueprint $table) use ($cols): void {
            foreach (array_slice($cols,0,5) as $column) {
                if (! Schema::hasColumn('payment_transactions',$column)) $table->string($column)->nullable()->index();
            }
            if (! Schema::hasColumn('payment_transactions','stripe_payload')) $table->json('stripe_payload')->nullable();
        });
    }
    public function down(): void
    {
        if (! Schema::hasTable('payment_transactions')) return;
        $existing = collect(['stripe_invoice_id','stripe_payment_intent_id','stripe_charge_id','stripe_subscription_id','stripe_customer_id','stripe_payload'])
            ->filter(fn ($column) => Schema::hasColumn('payment_transactions',$column))->all();
        if ($existing) Schema::table('payment_transactions', fn (Blueprint $table) => $table->dropColumn($existing));
    }
};
