<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('locker_room_support_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('concern');
            $table->text('details');
            $table->string('status')->default('open');
            $table->string('ghl_contact_id')->nullable()->index();
            $table->string('ghl_sync_status')->nullable();
            $table->json('ghl_sync_response')->nullable();
            $table->timestamp('ghl_synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('locker_room_support_requests');
    }
};
