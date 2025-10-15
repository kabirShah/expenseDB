<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('debit_cards', function (Blueprint $table) {
            $table->decimal('debit_limit', 15, 2)->nullable();
            $table->timestamp('added_date')->default(DB::raw('CURRENT_TIMESTAMP'));
        });
    }

    public function down(): void
    {
        Schema::table('debit_cards', function (Blueprint $table) {
            $table->dropColumn(['debit_limit', 'added_date']);
        });
    }
};
