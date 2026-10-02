<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('credit_service_requests')) {
            return;
        }

        Schema::create('credit_service_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('request_token', 100)->unique();
            $table->string('item_key', 80);
            $table->string('item_name', 160);
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedInteger('unit_price_points');
            $table->string('modifier', 40)->nullable();
            $table->unsignedInteger('points_spent');
            $table->text('notes')->nullable();
            $table->string('status', 40)->default('submitted');
            $table->unsignedBigInteger('credit_point_transaction_id')->nullable()->index();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_service_requests');
    }
};
