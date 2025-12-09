<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('receipts', function (Blueprint $table) {
            $table->id();
            $table->uuid('receipt_id')->unique();
            $table->unsignedBigInteger('user_id');

            $table->string('title')->nullable();
            $table->string('file_url');                // uploaded image PDF/JPG etc
            $table->text('raw_text')->nullable();      // OCR result
            $table->json('parsed_items')->nullable();  // parsed items array
            $table->decimal('total_amount', 10, 2)->default(0);
            
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('receipts');
    }
};
