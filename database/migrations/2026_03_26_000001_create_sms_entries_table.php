<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('sender')->nullable();
            $table->text('sms_body');
            $table->json('parsed_data')->nullable();
            $table->foreignId('transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
            $table->enum('status', ['pending', 'confirmed', 'ignored'])->default('pending');
            $table->boolean('is_financial')->default(false);
            $table->timestamp('received_at')->nullable()->index();
            $table->string('source_app', 100)->nullable();
            $table->string('external_id', 191)->nullable()->index();
            $table->timestamps();

            $table->unique(['user_id', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_entries');
    }
};
