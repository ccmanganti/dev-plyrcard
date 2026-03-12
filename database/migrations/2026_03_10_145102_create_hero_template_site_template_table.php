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

            $table->foreign('hero_template_id', 'htst_hero_fk')->references('id')->on('hero_templates')->cascadeOnDelete();
            $table->foreign('site_template_id', 'htst_site_fk')->references('id')->on('site_templates')->cascadeOnDelete();

            $table->timestamps();

            $table->unique(['hero_template_id', 'site_template_id'], 'hero_site_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hero_template_site_template');
    }
};