<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coach_database_school_memberships', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('ghl_location_id', 191)->default('');
            $table->string('business_id', 191);
            $table->string('list_key', 191);
            $table->timestamps();

            $table->unique(
                ['user_id', 'ghl_location_id', 'business_id', 'list_key'],
                'coach_school_membership_unique'
            );

            $table->index(
                ['user_id', 'ghl_location_id', 'list_key'],
                'coach_school_membership_list_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coach_database_school_memberships');
    }
};
