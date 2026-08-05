<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table): void {
            $table->string('logo_path')->nullable()->after('zipcode');
            $table->string('website_url')->nullable()->after('logo_path');
            $table->string('ghl_business_id')->nullable()->after('website_url');
            $table->timestamp('ghl_synced_at')->nullable()->after('ghl_business_id');

            $table->index('name');
            $table->index('ghl_business_id');
        });
    }

    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table): void {
            $table->dropIndex(['name']);
            $table->dropIndex(['ghl_business_id']);
            $table->dropColumn([
                'logo_path',
                'website_url',
                'ghl_business_id',
                'ghl_synced_at',
            ]);
        });
    }
};
