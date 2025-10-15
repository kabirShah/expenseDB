<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('parser_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('event_id')->unique();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('bank_name'); // HDFC, Kotak, ICICI, etc.
            $table->string('file_name');
            $table->string('file_path');
            $table->string('file_type')->default('pdf'); // pdf, csv, excel
            $table->string('status')->default('pending'); // pending, processing, completed, failed
            $table->integer('total_transactions')->default(0);
            $table->integer('parsed_transactions')->default(0);
            $table->integer('failed_transactions')->default(0);
            $table->json('metadata')->nullable(); // parsing options, bank-specific settings
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['bank_name', 'status']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parser_events');
    }
};
