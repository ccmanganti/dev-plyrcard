<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('coach_database_tracking_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('athlete_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('ghl_location_id')->default('');
            $table->string('coach_contact_id')->nullable();
            $table->string('school_business_id')->nullable();
            $table->uuid('campaign_uuid')->nullable();
            $table->uuid('message_uuid')->nullable();
            $table->string('template_id')->nullable();
            $table->string('event_type', 40);
            $table->string('platform', 32)->default('website');
            $table->string('source', 80)->default('tracked_link');
            $table->text('destination_url')->nullable();
            $table->string('visitor_hash', 64)->nullable();
            $table->string('ip_hash', 64)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->text('referer')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at')->useCurrent();
            $table->timestamps();

            $table->index(['athlete_user_id', 'event_type', 'occurred_at'], 'cdte_athlete_event_time_idx');
            $table->index(['athlete_user_id', 'platform', 'occurred_at'], 'cdte_athlete_platform_time_idx');
            $table->index(['athlete_user_id', 'coach_contact_id'], 'cdte_athlete_coach_idx');
            $table->index(['athlete_user_id', 'school_business_id'], 'cdte_athlete_school_idx');
            $table->index('message_uuid');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coach_database_tracking_events');
    }
};
