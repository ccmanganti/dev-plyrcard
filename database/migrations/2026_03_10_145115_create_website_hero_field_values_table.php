<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('website_hero_field_values', function (Blueprint $table) {
            $table->id();

            $table->foreignId('website_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hero_template_field_id')->constrained()->cascadeOnDelete();

            $table->longText('value')->nullable();
            $table->json('meta')->nullable();

            $table->timestamps();

            $table->unique(
                ['website_id', 'hero_template_field_id'],
                'whfv_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_hero_field_values');
    }
};