<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receipts', function (Blueprint $table) {
            $table->id();

            // UUID (external reference)
            $table->uuid('receipt_id')->unique();

            // Relations
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('linked_expense_id')->nullable(); // 🔥 KEY FEATURE

            // Receipt Core
            $table->string('title')->nullable();
            $table->string('file_url');                 // stored image
            $table->text('raw_text')->nullable();       // OCR output
            $table->json('parsed_items')->nullable();   // structured items
            $table->decimal('total_amount', 10, 2)->default(0);

            // Optional metadata (future ready 🚀)
            $table->string('vendor_name')->nullable();  // e.g. Reliance, Amazon
            $table->string('currency')->default('INR');
            $table->date('receipt_date')->nullable();   // extracted date

            $table->timestamps();

            /*
            |-----------------------------------------
            | FOREIGN KEYS
            |-----------------------------------------
            */
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');

            $table->foreign('linked_expense_id')
                ->references('id')
                ->on('expenses')
                ->onDelete('set null'); // keep receipt even if expense deleted

            /*
            |-----------------------------------------
            | INDEXES (performance ⚡)
            |-----------------------------------------
            */
            $table->index('user_id');
            $table->index('linked_expense_id');
            $table->index('receipt_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receipts');
    }
};