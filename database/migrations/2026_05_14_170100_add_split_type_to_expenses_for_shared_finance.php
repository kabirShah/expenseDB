<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('expenses') || Schema::hasColumn('expenses', 'split_type')) {
            return;
        }

        Schema::table('expenses', function (Blueprint $table) {
            $table->string('split_type', 30)->nullable()->after('group_id');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('expenses') || !Schema::hasColumn('expenses', 'split_type')) {
            return;
        }

        Schema::table('expenses', function (Blueprint $table) {
            $table->dropColumn('split_type');
        });
    }
};
