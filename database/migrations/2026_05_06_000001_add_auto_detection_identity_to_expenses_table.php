<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            if (!Schema::hasColumn('expenses', 'source')) {
                $table->enum('source', ['MANUAL', 'AA', 'NOTIFICATION'])->default('MANUAL')->after('category_id');
            }
            if (!Schema::hasColumn('expenses', 'reference_id')) {
                $table->string('reference_id')->nullable()->after('source');
            }
            if (!Schema::hasColumn('expenses', 'hash')) {
                $table->string('hash', 40)->nullable()->after('reference_id');
            }
        });

        if (DB::getDriverName() === 'mysql' && Schema::hasColumn('expenses', 'source')) {
            DB::table('expenses')->where('source', 'SMS')->update(['source' => 'NOTIFICATION']);
            DB::statement("ALTER TABLE expenses MODIFY source ENUM('MANUAL','AA','NOTIFICATION') NOT NULL DEFAULT 'MANUAL'");
        }

        Schema::table('expenses', function (Blueprint $table) {
            try {
                $table->index(['user_id', 'source'], 'expenses_user_source_enum_idx');
            } catch (Throwable $e) {
            }

            try {
                $table->index(['user_id', 'reference_id'], 'expenses_user_reference_id_idx');
            } catch (Throwable $e) {
            }

            try {
                $table->unique('hash', 'expenses_hash_unique');
            } catch (Throwable $e) {
            }
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            foreach (['expenses_user_source_enum_idx', 'expenses_user_reference_id_idx', 'expenses_hash_unique'] as $index) {
                try {
                    $table->dropIndex($index);
                } catch (Throwable $e) {
                }
            }

            $columns = [];
            foreach (['reference_id', 'hash'] as $column) {
                if (Schema::hasColumn('expenses', $column)) {
                    $columns[] = $column;
                }
            }
            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
