<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plyr_lab_trainers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('display_name')->nullable();
            $table->string('headline')->nullable();
            $table->string('location')->nullable();
            $table->json('specialties')->nullable();
            $table->text('bio')->nullable();
            $table->unsignedSmallInteger('booking_duration_minutes')->default(60);
            $table->boolean('is_active')->default(true);
            $table->text('plyomat_api_key')->nullable();
            $table->string('plyomat_base_url')->nullable();
            $table->string('plyomat_create_athlete_path')->nullable();
            $table->string('plyomat_stats_path')->nullable();
            $table->string('plyomat_auth_header')->default('Authorization');
            $table->string('plyomat_auth_prefix')->default('Bearer');
            $table->string('plyomat_account_id')->nullable();
            $table->timestamps();
        });

        Schema::create('plyr_lab_bookings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('player_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('trainer_id')->constrained('plyr_lab_trainers')->cascadeOnDelete();
            $table->dateTime('scheduled_for')->nullable();
            $table->string('status')->default('pending')->index();
            $table->text('notes')->nullable();
            $table->string('plyomat_athlete_id')->nullable()->index();
            $table->dateTime('plyomat_provisioned_at')->nullable();
            $table->dateTime('plyomat_last_synced_at')->nullable();
            $table->json('plyomat_stats')->nullable();
            $table->text('last_sync_error')->nullable();
            $table->timestamps();

            $table->index(['player_user_id', 'created_at']);
            $table->index(['trainer_id', 'scheduled_for']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plyr_lab_bookings');
        Schema::dropIfExists('plyr_lab_trainers');
    }
};
