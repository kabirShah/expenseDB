<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('receipt_items')) {
            return;
        }

        Schema::create('receipt_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('receipt_id');

            $table->string('name');
            $table->unsignedInteger('qty')->default(1);

            $table->decimal('price', 12, 2)->default(0);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('tax', 12, 2)->default(0);
            $table->decimal('subtotal', 12, 2)->default(0);

            $table->decimal('total', 12, 2)->default(0);

            // OCR confidence per item (best-effort)
            $table->unsignedTinyInteger('confidence')->nullable();

            // Field-level status / mapping
            $table->json('meta')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['receipt_id']);

            $table->foreign('receipt_id')
                ->references('receipt_id')
                ->on('receipts')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receipt_items');
    }
};


