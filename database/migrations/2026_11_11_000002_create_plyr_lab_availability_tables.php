<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plyr_lab_trainer_availabilities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('trainer_id')->constrained('plyr_lab_trainers')->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week');
            $table->boolean('is_available')->default(true);
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->timestamps();

            $table->unique(['trainer_id', 'day_of_week'], 'plyrlab_trainer_day_unique');
        });

        Schema::create('plyr_lab_trainer_blocks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('trainer_id')->constrained('plyr_lab_trainers')->cascadeOnDelete();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->boolean('all_day')->default(true);
            $table->string('reason')->nullable();
            $table->timestamps();

            $table->index(['trainer_id', 'starts_at']);
            $table->index(['trainer_id', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plyr_lab_trainer_blocks');
        Schema::dropIfExists('plyr_lab_trainer_availabilities');
    }
};
