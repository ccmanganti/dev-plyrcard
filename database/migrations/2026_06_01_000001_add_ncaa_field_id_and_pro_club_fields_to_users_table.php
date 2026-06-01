<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'ncaa_field_id')) {
                $table->string('ncaa_field_id')->nullable()->after('gpa');
            }

            if (! Schema::hasColumn('users', 'pro_club_name')) {
                $table->string('pro_club_name')->nullable()->after('club_league_id');
            }

            if (! Schema::hasColumn('users', 'pro_club_logo')) {
                $table->string('pro_club_logo')->nullable()->after('pro_club_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $columns = [
                'ncaa_field_id',
                'pro_club_name',
                'pro_club_logo',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
