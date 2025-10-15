<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            // Add expense relationship
            if (!Schema::hasColumn('invoices', 'expense_id')) {
                $table->unsignedBigInteger('expense_id')->nullable()->after('user_id');
                $table->foreign('expense_id')->references('id')->on('expenses')->onDelete('set null');
            }
            
            // Add transaction relationship
            if (!Schema::hasColumn('invoices', 'transaction_id')) {
                $table->unsignedBigInteger('transaction_id')->nullable()->after('expense_id');
                $table->foreign('transaction_id')->references('id')->on('transactions')->onDelete('set null');
            }
            
            // Add merchant information
            if (!Schema::hasColumn('invoices', 'merchant_name')) {
                $table->string('merchant_name')->nullable()->after('description');
            }
            
            if (!Schema::hasColumn('invoices', 'merchant_address')) {
                $table->text('merchant_address')->nullable()->after('merchant_name');
            }
            
            if (!Schema::hasColumn('invoices', 'merchant_gstin')) {
                $table->string('merchant_gstin', 15)->nullable()->after('merchant_address');
            }
            
            // Add invoice number
            if (!Schema::hasColumn('invoices', 'invoice_number')) {
                $table->string('invoice_number')->nullable()->after('merchant_gstin');
            }
            
            // Add tax information
            if (!Schema::hasColumn('invoices', 'tax_amount')) {
                $table->decimal('tax_amount', 10, 2)->default(0)->after('amount');
            }
            
            if (!Schema::hasColumn('invoices', 'discount_amount')) {
                $table->decimal('discount_amount', 10, 2)->default(0)->after('tax_amount');
            }
            
            // Add OCR processed data
            if (!Schema::hasColumn('invoices', 'ocr_data')) {
                $table->json('ocr_data')->nullable()->after('discount_amount')->comment('OCR extracted data');
            }
            
            // Add verification status
            if (!Schema::hasColumn('invoices', 'verification_status')) {
                $table->string('verification_status')->default('pending')->after('ocr_data')->comment('pending, verified, rejected');
            }
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['expense_id']);
            $table->dropForeign(['transaction_id']);
            $table->dropColumn([
                'expense_id',
                'transaction_id',
                'merchant_name',
                'merchant_address',
                'merchant_gstin',
                'invoice_number',
                'tax_amount',
                'discount_amount',
                'ocr_data',
                'verification_status'
            ]);
        });
    }
};
