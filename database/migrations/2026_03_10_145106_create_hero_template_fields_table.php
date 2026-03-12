<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hero_template_fields', function (Blueprint $table) {
            $table->id();

            $table->foreignId('hero_template_id')->constrained()->cascadeOnDelete();

            $table->string('name');
            $table->string('label');
            $table->string('type');
            $table->string('guide_image')->nullable();
            $table->boolean('is_required')->default(false);
            $table->integer('sort_order')->default(0);

            $table->json('options')->nullable();
            $table->json('default_value')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['hero_template_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hero_template_fields');
    }
};