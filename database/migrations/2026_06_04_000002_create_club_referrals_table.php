<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('club_referrals')) {
            return;
        }

        Schema::create('club_referrals', function (Blueprint $table): void {
            $table->id();
            $table->string('token')->unique();
            $table->foreignId('club_manager_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('league_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('club_league_id')->nullable()->constrained('club_leagues')->nullOnDelete();
            $table->string('team_name')->nullable();
            $table->string('sport')->nullable();
            $table->string('gender')->nullable();
            $table->string('invited_email')->nullable()->index();
            $table->string('invited_name')->nullable();
            $table->text('invite_url')->nullable();
            $table->string('status')->default('active')->index();
            $table->timestamp('clicked_at')->nullable();
            $table->timestamp('registered_at')->nullable();
            $table->foreignId('registered_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('utm_payload')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['club_id', 'league_id', 'team_name']);
            $table->index(['club_manager_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('club_referrals');
    }
};
