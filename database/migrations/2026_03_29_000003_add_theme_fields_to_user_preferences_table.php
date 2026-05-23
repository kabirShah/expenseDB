<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_preferences', function (Blueprint $table) {
            if (!Schema::hasColumn('user_preferences', 'theme_mode')) {
                $table->string('theme_mode', 20)->default('system')->after('storage_preference');
            }

            if (!Schema::hasColumn('user_preferences', 'use_system_theme')) {
                $table->boolean('use_system_theme')->default(true)->after('theme_mode');
            }
        });
    }

    public function down(): void
    {
        Schema::table('user_preferences', function (Blueprint $table) {
            $columns = [];

            if (Schema::hasColumn('user_preferences', 'theme_mode')) {
                $columns[] = 'theme_mode';
            }

            if (Schema::hasColumn('user_preferences', 'use_system_theme')) {
                $columns[] = 'use_system_theme';
            }

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
