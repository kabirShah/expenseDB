<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'name')) {
                $table->string('name')->nullable()->after('last_name');
            }
            if (!Schema::hasColumn('users', 'avatar')) {
                $table->string('avatar')->nullable()->after('profile_image');
            }
            if (!Schema::hasColumn('users', 'currency')) {
                $table->string('currency', 10)->default('INR')->after('avatar');
            }
            if (!Schema::hasColumn('users', 'pin')) {
                $table->string('pin')->nullable()->after('pin_code');
            }
            if (!Schema::hasColumn('users', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('pin_enabled');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $toDrop = [];
            foreach (['name', 'avatar', 'currency', 'pin', 'is_active'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $toDrop[] = $column;
                }
            }
            if (!empty($toDrop)) {
                $table->dropColumn($toDrop);
            }
        });
    }
};
