<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            if (!Schema::hasColumn('categories', 'user_id')) {
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete()->after('id');
            }
            if (!Schema::hasColumn('categories', 'icon')) {
                $table->string('icon', 50)->nullable()->after('name');
            }
            if (!Schema::hasColumn('categories', 'color')) {
                $table->string('color', 10)->nullable()->after('icon');
            }
            if (!Schema::hasColumn('categories', 'type')) {
                $table->string('type', 20)->default('expense')->after('color');
            }
            if (!Schema::hasColumn('categories', 'is_default')) {
                $table->boolean('is_default')->default(false)->after('type');
            }
        });
    }

    public function down(): void
    {
        // compatibility migration; no destructive rollback
    }
};
