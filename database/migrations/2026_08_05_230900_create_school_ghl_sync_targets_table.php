<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_ghl_sync_targets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('representative_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('api_key_hash', 64);
            $table->string('location_id');
            $table->string('normalized_name');
            $table->string('ghl_business_id')->nullable();
            $table->string('status', 30)->default('pending');
            $table->string('last_action', 30)->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->unsignedBigInteger('last_counted_run_id')->nullable();
            $table->timestamps();

            $table->unique(
                ['school_id', 'api_key_hash', 'location_id'],
                'school_ghl_target_unique'
            );
            $table->index(
                ['location_id', 'normalized_name'],
                'school_ghl_location_name_index'
            );
            $table->index(['status', 'location_id']);
            $table->index('ghl_business_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_ghl_sync_targets');
    }
};
