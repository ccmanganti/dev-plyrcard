<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('locker_room_referrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('friend_name');
            $table->string('friend_email')->nullable();
            $table->string('friend_phone')->nullable();
            $table->text('message')->nullable();
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
        Schema::dropIfExists('locker_room_referrals');
    }
};
