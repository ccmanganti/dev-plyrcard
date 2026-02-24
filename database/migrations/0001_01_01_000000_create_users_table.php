<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('personal_email')->unique()->nullable();
            $table->string('email')->unique();
            $table->string('phone')->unique()->nullable();
            $table->string('country')->nullable();
            $table->string('state')->nullable();
            $table->string('city')->nullable();
            $table->string('street')->nullable();
            $table->float('gpa')->nullable();
            $table->year('year')->nullable();
            $table->year('birth')->nullable();
            $table->integer('jersey_number')->nullable();
            $table->longText('accolades')->nullable();
            $table->boolean('natl_team_exp')->nullable();
            $table->string('team_name')->nullable();
            $table->string('ig_handle')->nullable();
            $table->string('x_handle')->nullable();
            $table->string('yt_url')->nullable();
            $table->longText('press')->nullable();
            $table->string('parent')->nullable();
            $table->string('parent_email')->nullable();
            $table->string('parent_phone')->nullable();
            $table->string('sec_parent')->nullable();
            $table->string('sec_parent_email')->nullable();
            $table->string('sec_parent_phone')->nullable();
            $table->string('club_coach')->nullable();
            $table->string('club_coach_email')->nullable();
            $table->string('club_coach_phone]')->nullable();
            $table->string('natl_coach')->nullable();
            $table->string('natl_coach_email')->nullable();
            $table->string('natl_coach_phone')->nullable();
            $table->string('tech_trainer')->nullable();
            $table->string('tech_trainer_email')->nullable();
            $table->string('tech_trainer_phone')->nullable();
            $table->string('snc_trainer')->nullable();
            $table->string('snc_trainer_email')->nullable();
            $table->string('snc_trainer_phone')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('domain')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->timestamp('deleted_at')->nullable();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
