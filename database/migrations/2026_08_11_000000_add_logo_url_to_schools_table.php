<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void  
    {
        if (! Schema::hasColumn('schools', 'logo_url')) {
            Schema::table('schools', function (Blueprint $table): void {
                $table->text('logo_url')->nullable()->after('zipcode');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('schools', 'logo_url')) {
            Schema::table('schools', function (Blueprint $table): void {
                $table->dropColumn('logo_url');
            });
        }
    }
};
