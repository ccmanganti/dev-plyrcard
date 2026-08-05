<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coach_ghl_sync_targets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('coach_id')->constrained()->cascadeOnDelete();
            $table->foreignId('representative_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('api_key_hash', 64);
            $table->string('location_id');
            $table->json('account_user_ids')->nullable();
            $table->string('school_name_snapshot')->nullable();
            $table->string('coach_email_snapshot');
            $table->string('ghl_contact_id')->nullable();
            $table->string('ghl_business_id')->nullable();
            $table->string('status', 30)->default('pending');
            $table->string('matched_by', 30)->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['coach_id', 'api_key_hash', 'location_id'], 'coach_ghl_target_unique');
            $table->index(['status', 'location_id']);
            $table->index('coach_email_snapshot');
            $table->index('school_name_snapshot');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coach_ghl_sync_targets');
    }
};
