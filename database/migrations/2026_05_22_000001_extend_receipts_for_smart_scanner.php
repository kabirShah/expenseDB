<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Extend receipts table WITHOUT breaking existing data/columns.
        Schema::table('receipts', function (Blueprint $table) {
            // Status + confidence
            if (!Schema::hasColumn('receipts', 'status')) {
                $table->string('status')->nullable()->after('linked_expense_id');
            }
            if (!Schema::hasColumn('receipts', 'confidence')) {
                $table->unsignedTinyInteger('confidence')->nullable()->after('status');
            }

            // OCR fields
            if (!Schema::hasColumn('receipts', 'raw_ocr')) {
                $table->longText('raw_ocr')->nullable()->after('raw_text');
            }
            if (!Schema::hasColumn('receipts', 'ocr_json')) {
                $table->json('ocr_json')->nullable()->after('raw_ocr');
            }
            if (!Schema::hasColumn('receipts', 'parsed_json')) {
                $table->json('parsed_json')->nullable()->after('ocr_json');
            }
            if (!Schema::hasColumn('receipts', 'manual_override_json')) {
                $table->json('manual_override_json')->nullable()->after('parsed_json');
            }
            if (!Schema::hasColumn('receipts', 'field_status_json')) {
                $table->json('field_status_json')->nullable()->after('manual_override_json');
            }

            // Processed image url
            if (!Schema::hasColumn('receipts', 'processed_image_url')) {
                $table->text('processed_image_url')->nullable()->after('file_url');
            }

            // Receipt hash (duplicate prevention)
            if (!Schema::hasColumn('receipts', 'receipt_hash')) {
                $table->string('receipt_hash', 64)->nullable()->after('receipt_id');
                $table->index('receipt_hash');
            }
        });
    }

    public function down(): void
    {
        Schema::table('receipts', function (Blueprint $table) {
            if (Schema::hasColumn('receipts', 'status')) {
                $table->dropColumn('status');
            }
            if (Schema::hasColumn('receipts', 'confidence')) {
                $table->dropColumn('confidence');
            }
            if (Schema::hasColumn('receipts', 'raw_ocr')) {
                $table->dropColumn('raw_ocr');
            }
            if (Schema::hasColumn('receipts', 'ocr_json')) {
                $table->dropColumn('ocr_json');
            }
            if (Schema::hasColumn('receipts', 'parsed_json')) {
                $table->dropColumn('parsed_json');
            }
            if (Schema::hasColumn('receipts', 'manual_override_json')) {
                $table->dropColumn('manual_override_json');
            }
            if (Schema::hasColumn('receipts', 'field_status_json')) {
                $table->dropColumn('field_status_json');
            }
            if (Schema::hasColumn('receipts', 'processed_image_url')) {
                $table->dropColumn('processed_image_url');
            }
            if (Schema::hasColumn('receipts', 'receipt_hash')) {
                $table->dropColumn('receipt_hash');
            }
        });
    }
};


