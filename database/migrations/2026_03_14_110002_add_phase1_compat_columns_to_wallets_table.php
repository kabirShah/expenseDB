<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wallets', function (Blueprint $table) {
            if (!Schema::hasColumn('wallets', 'color')) {
                $table->string('color', 10)->nullable()->after('balance');
            }
            if (!Schema::hasColumn('wallets', 'icon')) {
                $table->string('icon', 50)->nullable()->after('color');
            }
            if (!Schema::hasColumn('wallets', 'is_default')) {
                $table->boolean('is_default')->default(false)->after('icon');
            }
        });
    }

    public function down(): void
    {
        Schema::table('wallets', function (Blueprint $table) {
            $toDrop = [];
            foreach (['color', 'icon', 'is_default'] as $column) {
                if (Schema::hasColumn('wallets', $column)) {
                    $toDrop[] = $column;
                }
            }
            if (!empty($toDrop)) {
                $table->dropColumn($toDrop);
            }
        });
    }
};
