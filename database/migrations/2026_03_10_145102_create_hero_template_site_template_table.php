<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hero_template_site_template', function (Blueprint $table) {
            $table->id();

            $table->foreignId('hero_template_id')->constrained()->cascadeOnDelete();
            $table->foreignId('site_template_id')->constrained()->cascadeOnDelete();

            $table->timestamps();

            $table->unique(['hero_template_id', 'site_template_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hero_template_site_template');
    }
};