<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'points_available')) {
            Schema::table('users', fn (Blueprint $table) => $table->unsignedInteger('points_available')->default(0)->index());
        }
        if (! Schema::hasTable('credit_point_transactions')) {
            Schema::create('credit_point_transactions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('idempotency_key')->unique();
                $table->string('type', 32)->index();
                $table->unsignedInteger('points');
                $table->string('grant_id')->nullable()->index();
                $table->string('item_key')->nullable()->index();
                $table->unsignedInteger('qty')->default(1);
                $table->unsignedInteger('catalog_version')->default(1);
                $table->unsignedInteger('unit_price')->nullable();
                $table->string('modifier')->nullable();
                $table->text('reason')->nullable();
                $table->string('actor')->nullable();
                $table->string('deliverable_id')->nullable()->index();
                $table->string('source_type')->nullable()->index();
                $table->string('source_id')->nullable()->index();
                $table->json('meta')->nullable();
                $table->timestamps();
                $table->index(['user_id', 'created_at']);
            });
        }
    }
    public function down(): void
    {
        Schema::dropIfExists('credit_point_transactions');
        if (Schema::hasColumn('users', 'points_available')) {
            Schema::table('users', fn (Blueprint $table) => $table->dropColumn('points_available'));
        }
    }
};
