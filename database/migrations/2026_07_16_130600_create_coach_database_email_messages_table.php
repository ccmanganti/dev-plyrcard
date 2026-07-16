<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('coach_database_email_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('athlete_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('ghl_location_id')->default('');
            $table->uuid('campaign_uuid')->nullable();
            $table->uuid('message_uuid')->unique();
            $table->string('ghl_message_id')->nullable();
            $table->string('coach_contact_id')->nullable();
            $table->string('school_business_id')->nullable();
            $table->string('template_id')->nullable();
            $table->string('recipient_email')->nullable();
            $table->text('subject');
            $table->longText('rendered_html');
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['athlete_user_id', 'sent_at'], 'cdem_athlete_sent_idx');
            $table->index(['athlete_user_id', 'coach_contact_id'], 'cdem_athlete_coach_idx');
            $table->index(['athlete_user_id', 'school_business_id'], 'cdem_athlete_school_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coach_database_email_messages');
    }
};
