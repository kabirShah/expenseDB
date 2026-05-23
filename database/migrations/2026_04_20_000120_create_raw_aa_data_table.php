<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('raw_aa_data', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('consent_id')->constrained('consents')->cascadeOnDelete();
            $table->json('payload');
            $table->boolean('processed')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'processed']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('raw_aa_data');
    }
};
