<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('websites', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('site_template_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('hero_template_id')->nullable()->constrained()->nullOnDelete();

            $table->string('name')->nullable();
            $table->string('slug')->unique()->nullable();
            $table->string('domain')->nullable();

            $table->boolean('is_active')->default(true);
            $table->boolean('is_published')->default(false);

            $table->longText('project_json')->nullable();
            $table->longText('html')->nullable();
            $table->longText('css')->nullable();

            $table->string('primary_color', 7)->nullable();
            $table->string('secondary_color', 7)->nullable();
            $table->string('accent_color', 7)->nullable();
            $table->string('background_color', 7)->nullable();
            $table->string('surface_color', 7)->nullable();
            $table->string('text_primary_color', 7)->nullable();
            $table->string('text_secondary_color', 7)->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('websites');
    }
};