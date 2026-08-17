<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'phone')) {
            return;
        }

        foreach (Schema::getIndexes('users') as $index) {
            $columns = array_values($index['columns'] ?? []);
            $isUnique = (bool) ($index['unique'] ?? false);
            $name = $index['name'] ?? null;

            if (! $isUnique || $columns !== ['phone'] || ! $name) {
                continue;
            }

            Schema::table('users', function (Blueprint $table) use ($name): void {
                $table->dropUnique($name);
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'phone')) {
            return;
        }

        $hasDuplicatePhones = DB::table('users')
            ->whereNotNull('phone')
            ->where('phone', '<>', '')
            ->select('phone')
            ->groupBy('phone')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($hasDuplicatePhones || Schema::hasIndex('users', 'users_phone_unique')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->unique('phone');
        });
    }
};
