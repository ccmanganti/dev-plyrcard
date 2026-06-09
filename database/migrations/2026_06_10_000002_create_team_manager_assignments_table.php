<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('team_manager_assignments')) {
            return;
        }

        Schema::create('team_manager_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('club_league_id')->nullable()->constrained('club_leagues')->nullOnDelete();
            $table->foreignId('league_id')->nullable()->constrained('leagues')->nullOnDelete();
            $table->string('team_name');
            $table->timestamps();

            $table->unique([
                'user_id',
                'club_id',
                'club_league_id',
                'team_name',
            ], 'team_manager_unique_assignment');

            $table->index(['club_id', 'team_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_manager_assignments');
    }
};
