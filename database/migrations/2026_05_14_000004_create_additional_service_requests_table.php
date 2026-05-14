<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('additional_service_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('service_key');
            $table->string('service_name')->nullable();
            $table->string('listed_price')->nullable();
            $table->text('notes')->nullable();
            $table->string('status')->default('new');
            $table->string('ghl_contact_id')->nullable()->index();
            $table->string('ghl_sync_status')->nullable();
            $table->json('ghl_sync_response')->nullable();
            $table->timestamp('ghl_synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('additional_service_requests');
    }
};
