<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('coach_database_email_templates')) {
            Schema::create('coach_database_email_templates', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->nullable()->index();
                $table->string('name');
                $table->string('subject');
                $table->string('preview_text')->nullable();
                $table->longText('body_html');
                $table->string('graphic_url')->nullable();
                $table->json('attachments')->nullable();
                $table->boolean('is_sample')->default(false)->index();
                $table->boolean('is_locked')->default(false)->index();
                $table->boolean('is_active')->default(true)->index();
                $table->integer('sort_order')->default(0);
                $table->timestamps();
                $table->softDeletes();

                $table->index(['user_id', 'is_active', 'sort_order'], 'cd_email_templates_user_active_sort_idx');
            });

            return;
        }

        Schema::table('coach_database_email_templates', function (Blueprint $table): void {
            if (! Schema::hasColumn('coach_database_email_templates', 'is_locked')) {
                $table->boolean('is_locked')->default(false)->after('is_sample')->index();
            }

            if (! Schema::hasColumn('coach_database_email_templates', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('is_locked')->index();
            }

            if (! Schema::hasColumn('coach_database_email_templates', 'sort_order')) {
                $table->integer('sort_order')->default(0)->after('is_active');
            }

            if (! Schema::hasColumn('coach_database_email_templates', 'deleted_at')) {
                $table->softDeletes();
            }
        });
    }

    public function down(): void
    {
        // Do not drop this table on rollback because it may already contain user-created templates.
        // This migration is intentionally safe for production.
    }
};