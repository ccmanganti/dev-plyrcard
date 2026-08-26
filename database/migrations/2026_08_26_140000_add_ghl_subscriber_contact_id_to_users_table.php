<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'ghl_subscriber_contact_id')) {
                $table->string('ghl_subscriber_contact_id')->nullable()->after('ghl_contact_id')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'ghl_subscriber_contact_id')) {
                $table->dropIndex(['ghl_subscriber_contact_id']);
                $table->dropColumn('ghl_subscriber_contact_id');
            }
        });
    }
};
