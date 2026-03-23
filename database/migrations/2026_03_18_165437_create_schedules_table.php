<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedules', function (Blueprint $table) {
            $table->id();

            $table->foreignId('club_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('created_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('title')->nullable();
            $table->string('opponent')->nullable();
            $table->date('game_date')->nullable();
            $table->time('game_time')->nullable();
            $table->string('location')->nullable();
            $table->string('venue')->nullable();

            $table->string('status')->default('upcoming');
            $table->boolean('is_home')->nullable();

            $table->string('result')->nullable();
            $table->string('score')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedules');
    }
};