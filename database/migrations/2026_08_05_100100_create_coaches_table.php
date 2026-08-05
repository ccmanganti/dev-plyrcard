<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coaches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->nullable()->constrained()->nullOnDelete();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('display_name');
            $table->string('email')->nullable()->unique();
            $table->string('secondary_email')->nullable();
            $table->string('phone')->nullable();
            $table->string('title')->nullable();
            $table->string('sport', 100)->index();
            $table->string('city')->nullable();
            $table->string('state')->nullable()->index();
            $table->string('country')->nullable()->default('United States');
            $table->string('website_url')->nullable();
            $table->text('notes')->nullable();
            $table->string('ghl_contact_id')->nullable()->index();
            $table->string('ghl_location_id')->nullable()->index();
            $table->timestamp('ghl_synced_at')->nullable();
            $table->string('ghl_sync_status', 50)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->string('source', 50)->default('manual');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['sport', 'is_active']);
            $table->index(['school_id', 'sport']);
            $table->index(['last_name', 'first_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coaches');
    }
};
