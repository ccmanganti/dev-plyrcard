<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('school_user_exclusivity')) {
            Schema::create('school_user_exclusivity', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['school_id', 'user_id'], 'school_user_exclusivity_unique');
                $table->index(['user_id', 'school_id'], 'school_user_exclusivity_user_idx');
            });
        }

        if (! Schema::hasTable('coach_user_exclusivity')) {
            Schema::create('coach_user_exclusivity', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('coach_id')->constrained('coaches')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['coach_id', 'user_id'], 'coach_user_exclusivity_unique');
                $table->index(['user_id', 'coach_id'], 'coach_user_exclusivity_user_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('coach_user_exclusivity');
        Schema::dropIfExists('school_user_exclusivity');
    }
};
