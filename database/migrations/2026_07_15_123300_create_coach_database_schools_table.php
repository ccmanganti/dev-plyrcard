<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coach_database_schools', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('ghl_location_id')->default('');
            $table->string('business_id');
            $table->string('name');
            $table->text('logo_url')->nullable();
            $table->string('conference')->nullable();
            $table->string('division')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->unsignedInteger('coach_count')->default(0);
            $table->string('head_coach_name')->nullable();
            $table->string('head_coach_title')->nullable();
            $table->string('head_coach_email')->nullable();
            $table->text('search_text')->nullable();
            $table->json('payload')->nullable();
            $table->string('source_cached_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'ghl_location_id', 'business_id'], 'coach_db_schools_user_location_business_unique');
            $table->index(['user_id', 'ghl_location_id', 'division'], 'coach_db_schools_division_index');
            $table->index(['user_id', 'ghl_location_id', 'conference'], 'coach_db_schools_conference_index');
            $table->index(['user_id', 'ghl_location_id', 'name'], 'coach_db_schools_name_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coach_database_schools');
    }
};
