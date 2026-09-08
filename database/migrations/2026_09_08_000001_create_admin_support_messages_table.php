<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_support_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('admin_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('concern', 80);
            $table->string('channel', 24);
            $table->string('subject')->nullable();
            $table->longText('template_subject')->nullable();
            $table->longText('template_message')->nullable();
            $table->longText('rendered_message');
            $table->json('variables')->nullable();
            $table->string('recipient_email')->nullable();
            $table->string('recipient_phone')->nullable();
            $table->string('email_status', 32)->nullable();
            $table->string('sms_status', 32)->nullable();
            $table->string('provider_contact_id')->nullable();
            $table->json('provider_message_ids')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'sent_at']);
            $table->index(['concern', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_support_messages');
    }
};
