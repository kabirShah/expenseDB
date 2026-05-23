<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            if (!Schema::hasColumn('transactions', 'wallet_id')) {
                $table->foreignId('wallet_id')->nullable()->constrained('wallets')->nullOnDelete()->after('user_id');
            }
            if (!Schema::hasColumn('transactions', 'category_id')) {
                $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete()->after('wallet_id');
            }
            if (!Schema::hasColumn('transactions', 'note')) {
                $table->text('note')->nullable()->after('amount');
            }
            if (!Schema::hasColumn('transactions', 'payment_method')) {
                $table->string('payment_method')->nullable()->after('method');
            }
            if (!Schema::hasColumn('transactions', 'reference_no')) {
                $table->string('reference_no')->nullable()->after('payment_method');
            }
            if (!Schema::hasColumn('transactions', 'source_app')) {
                $table->string('source_app')->nullable()->after('reference_no');
            }
            if (!Schema::hasColumn('transactions', 'receipt_image')) {
                $table->string('receipt_image')->nullable()->after('source_app');
            }
            if (!Schema::hasColumn('transactions', 'entry_type')) {
                $table->string('entry_type')->nullable()->after('receipt_image');
            }
            if (!Schema::hasColumn('transactions', 'batch_id')) {
                $table->string('batch_id', 36)->nullable()->after('entry_type');
            }
            if (!Schema::hasColumn('transactions', 'recurring_id')) {
                $table->unsignedBigInteger('recurring_id')->nullable()->after('batch_id');
            }
            if (!Schema::hasColumn('transactions', 'latitude')) {
                $table->decimal('latitude', 10, 7)->nullable()->after('recurring_id');
            }
            if (!Schema::hasColumn('transactions', 'longitude')) {
                $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            }
        });

        Schema::table('notifications', function (Blueprint $table) {
            if (!Schema::hasColumn('notifications', 'body')) {
                $table->text('body')->nullable()->after('message');
            }
            if (!Schema::hasColumn('notifications', 'data')) {
                $table->json('data')->nullable()->after('type');
            }
        });
    }

    public function down(): void
    {
        // Intentionally non-destructive for compatibility.
    }
};
