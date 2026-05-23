<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->consolidateExpensesSchema();
        $this->consolidateTransactionsSchema();
    }

    private function consolidateExpensesSchema(): void
    {
        if (! Schema::hasTable('expenses')) {
            return;
        }

        Schema::table('expenses', function (Blueprint $table) {
            if (! Schema::hasColumn('expenses', 'wallet_id')) {
                $table->foreignId('wallet_id')
                    ->nullable()
                    ->after('user_id')
                    ->constrained('wallets')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('expenses', 'source_type')) {
                $table->string('source_type', 20)
                    ->default('manual')
                    ->after('wallet_id');
            }

            if (! Schema::hasColumn('expenses', 'source_ref_id')) {
                $table->unsignedBigInteger('source_ref_id')
                    ->nullable()
                    ->after('source_type');
            }

            if (! Schema::hasColumn('expenses', 'merchant_name')) {
                $table->string('merchant_name')
                    ->nullable()
                    ->after('category_name');
            }

            if (! Schema::hasColumn('expenses', 'payment_method')) {
                $table->string('payment_method', 50)
                    ->nullable()
                    ->after('merchant_name');
            }

            if (! Schema::hasColumn('expenses', 'currency')) {
                $table->string('currency', 3)
                    ->default('INR')
                    ->after('amount');
            }

            if (! Schema::hasColumn('expenses', 'expense_date')) {
                $table->dateTime('expense_date')
                    ->nullable()
                    ->after('date');
            }

            if (! Schema::hasColumn('expenses', 'raw_hash')) {
                $table->string('raw_hash', 64)
                    ->nullable()
                    ->after('receipt_url');
            }

            if (! Schema::hasColumn('expenses', 'duplicate_of')) {
                $table->unsignedBigInteger('duplicate_of')
                    ->nullable()
                    ->after('raw_hash');
            }

            if (! Schema::hasColumn('expenses', 'is_duplicate')) {
                $table->boolean('is_duplicate')
                    ->default(false)
                    ->after('duplicate_of');
            }

            if (! Schema::hasColumn('expenses', 'metadata')) {
                $table->json('metadata')
                    ->nullable()
                    ->after('is_duplicate');
            }

            if (! Schema::hasColumn('expenses', 'group_id')) {
                $table->foreignId('group_id')
                    ->nullable()
                    ->after('user_id')
                    ->constrained('expense_groups')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('expenses', 'split_type')) {
                $table->enum('split_type', ['equal', 'exact', 'percentage', 'shares'])
                    ->nullable()
                    ->after('group_id');
            }

            if (! Schema::hasColumn('expenses', 'payment_source')) {
                $table->string('payment_source', 20)
                    ->nullable()
                    ->after('payment_method');
            }

            if (! Schema::hasColumn('expenses', 'aa_transaction_id')) {
                $table->foreignId('aa_transaction_id')
                    ->nullable()
                    ->after('payment_source')
                    ->constrained('aa_transactions')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('expenses', 'source')) {
                $table->enum('source', ['MANUAL', 'AA', 'NOTIFICATION'])
                    ->default('MANUAL')
                    ->after('category_id');
            }

            if (! Schema::hasColumn('expenses', 'reference_id')) {
                $table->string('reference_id')
                    ->nullable()
                    ->after('source');
            }

            if (! Schema::hasColumn('expenses', 'hash')) {
                $table->string('hash', 40)
                    ->nullable()
                    ->after('reference_id');
            }

            if (! Schema::hasColumn('expenses', 'duplicate_key')) {
                $table->string('duplicate_key', 191)
                    ->nullable();
                $table->index('duplicate_key');
            }

            if (! Schema::hasColumn('expenses', 'shared_metadata')) {
                $table->json('shared_metadata')
                    ->nullable()
                    ->after('metadata');
            }

            if (! Schema::hasColumn('expenses', 'linked_transaction_id')) {
                $table->foreignId('linked_transaction_id')
                    ->nullable()
                    ->after('group_id')
                    ->constrained('transactions')
                    ->nullOnDelete();
            }
        });

        if (DB::getDriverName() === 'mysql' && Schema::hasColumn('expenses', 'source')) {
            DB::statement("ALTER TABLE expenses MODIFY source ENUM('MANUAL','AA','NOTIFICATION') NOT NULL DEFAULT 'MANUAL'");
        }
    }

    private function consolidateTransactionsSchema(): void
    {
        if (! Schema::hasTable('transactions')) {
            return;
        }

        Schema::table('transactions', function (Blueprint $table) {
            if (! Schema::hasColumn('transactions', 'wallet_id')) {
                $table->foreignId('wallet_id')
                    ->nullable()
                    ->after('user_id')
                    ->constrained('wallets')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('transactions', 'category_id')) {
                $table->foreignId('category_id')
                    ->nullable()
                    ->after('wallet_id')
                    ->constrained('categories')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('transactions', 'note')) {
                $table->text('note')
                    ->nullable()
                    ->after('amount');
            }

            if (! Schema::hasColumn('transactions', 'payment_method')) {
                $table->string('payment_method')
                    ->nullable()
                    ->after('method');
            }

            if (! Schema::hasColumn('transactions', 'reference_no')) {
                $table->string('reference_no')
                    ->nullable()
                    ->after('payment_method');
            }

            if (! Schema::hasColumn('transactions', 'source_app')) {
                $table->string('source_app')
                    ->nullable()
                    ->after('reference_no');
            }

            if (! Schema::hasColumn('transactions', 'receipt_image')) {
                $table->string('receipt_image')
                    ->nullable()
                    ->after('source_app');
            }

            if (! Schema::hasColumn('transactions', 'entry_type')) {
                $table->string('entry_type')
                    ->nullable()
                    ->after('receipt_image');
            }

            if (! Schema::hasColumn('transactions', 'batch_id')) {
                $table->string('batch_id', 36)
                    ->nullable()
                    ->after('entry_type');
            }

            if (! Schema::hasColumn('transactions', 'recurring_id')) {
                $table->unsignedBigInteger('recurring_id')
                    ->nullable()
                    ->after('batch_id');
            }

            if (! Schema::hasColumn('transactions', 'latitude')) {
                $table->decimal('latitude', 10, 7)
                    ->nullable()
                    ->after('recurring_id');
            }

            if (! Schema::hasColumn('transactions', 'longitude')) {
                $table->decimal('longitude', 10, 7)
                    ->nullable()
                    ->after('latitude');
            }

            if (! Schema::hasColumn('transactions', 'account_id')) {
                $table->foreignId('account_id')
                    ->nullable()
                    ->after('user_id')
                    ->constrained('accounts')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('transactions', 'merchant')) {
                $table->string('merchant')
                    ->nullable()
                    ->after('amount');
            }

            if (! Schema::hasColumn('transactions', 'reference_id')) {
                $table->string('reference_id')
                    ->nullable()
                    ->after('merchant');
            }

            if (! Schema::hasColumn('transactions', 'raw_data')) {
                $table->json('raw_data')
                    ->nullable()
                    ->after('reference_id');
            }

            if (! Schema::hasColumn('transactions', 'source_type')) {
                $table->string('source_type', 20)
                    ->default('manual')
                    ->after('entry_type');
            }

            if (! Schema::hasColumn('transactions', 'merchant_name')) {
                $table->string('merchant_name')
                    ->nullable()
                    ->after('source_type');
            }

            if (! Schema::hasColumn('transactions', 'raw_text')) {
                $table->text('raw_text')
                    ->nullable()
                    ->after('raw_data');
            }

            if (! Schema::hasColumn('transactions', 'parent_id')) {
                $table->foreignId('parent_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('transactions')
                    ->nullOnDelete();
            }
        });

        if (DB::getDriverName() === 'mysql' && Schema::hasColumn('transactions', 'reference_id')) {
            try {
                Schema::table('transactions', function (Blueprint $table) {
                    $table->index(['user_id', 'reference_id'], 'transactions_user_reference_idx');
                });
            } catch (\Throwable $e) {
            }
        }
    }

    public function down(): void
    {
        // Consolidation migration is intentionally non-destructive for existing schema.
    }
};
