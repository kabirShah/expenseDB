<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('receipt_activity')) {
            return;
        }

        Schema::create('receipt_activity', function (Blueprint $table) {
            $table->id();
            $table->uuid('receipt_id');

            $table->unsignedBigInteger('actor_user_id')->nullable();
            $table->string('event_type'); // uploaded|ocr|parse|review|confirm|expense
            $table->json('payload')->nullable();

            $table->timestamps();

            $table->index('receipt_id');

            $table->foreign('receipt_id')
                ->references('receipt_id')
                ->on('receipts')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receipt_activity');
    }
};

