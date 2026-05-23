<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('receipt_versions')) {
            return;
        }

        Schema::create('receipt_versions', function (Blueprint $table) {
            $table->id();
            $table->uuid('receipt_id');

            // type: ocr|ai_parse|manual
            $table->string('version_type')->default('manual');

            $table->json('ocr_json')->nullable();
            $table->text('raw_ocr')->nullable();
            $table->json('parsed_json')->nullable();
            $table->json('manual_override_json')->nullable();
            $table->json('field_status_json')->nullable();

            $table->unsignedTinyInteger('confidence')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('receipt_id');

            $table->foreign('receipt_id')
                ->references('receipt_id')
                ->on('receipts')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receipt_versions');
    }
};

