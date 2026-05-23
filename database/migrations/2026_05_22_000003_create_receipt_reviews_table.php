<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('receipt_reviews')) {
            return;
        }

        Schema::create('receipt_reviews', function (Blueprint $table) {
            $table->id();
            $table->uuid('receipt_id');

            // snapshot of manual edit state + statuses
            $table->json('manual_override_json')->nullable();
            $table->json('field_status_json')->nullable();
            $table->json('version_notes')->nullable();

            $table->unsignedTinyInteger('confidence')->nullable();
            $table->string('status')->default('draft'); // draft|requires_review|confirmed

            $table->unsignedTinyInteger('confirmed_by_user_id')->nullable();

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
        Schema::dropIfExists('receipt_reviews');
    }
};

