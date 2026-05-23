<?php

use App\Support\MigrationHelpers;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        MigrationHelpers::addColumnIfMissing('expenses', 'aa_transaction_id', function (Blueprint $table) {
            $table->foreignId('aa_transaction_id')
                ->nullable()
                ->constrained('aa_transactions')
                ->onDelete('set null');
        });

        MigrationHelpers::addColumnIfMissing('expenses', 'source', function (Blueprint $table) {
            $table->enum('source', ['MANUAL', 'SMS', 'AA'])
                ->default('MANUAL')
                ->after('category_id');
        });

        if (Schema::hasColumn('expenses', 'source')) {
            try {
                Schema::table('expenses', function (Blueprint $table) {
                    $table->index('source');
                    $table->index(['user_id', 'source']);
                });
            } catch (\Throwable $e) {
            }
        }
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            if (Schema::hasColumn('expenses', 'aa_transaction_id')) {
                try {
                    $table->dropForeign(['aa_transaction_id']);
                } catch (\Throwable $e) {
                }
            }

            $columns = [];
            foreach (['aa_transaction_id', 'source'] as $column) {
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