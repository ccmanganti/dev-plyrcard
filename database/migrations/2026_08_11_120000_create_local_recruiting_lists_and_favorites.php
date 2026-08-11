<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('favorite_schools', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'school_id']);
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('my_lists', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('slug', 120);
            $table->string('color', 20)->default('#ff6338');
            $table->boolean('is_system')->default(false);
            $table->unsignedInteger('sort_order')->default(100);
            $table->timestamps();
            $table->unique(['user_id', 'slug']);
            $table->index(['user_id', 'sort_order']);
        });

        Schema::create('my_list_schools', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('my_list_id')->constrained('my_lists')->cascadeOnDelete();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['my_list_id', 'school_id']);
            $table->index(['school_id', 'my_list_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('my_list_schools');
        Schema::dropIfExists('my_lists');
        Schema::dropIfExists('favorite_schools');
    }
};
