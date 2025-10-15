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
        Schema::table('settlements', function (Blueprint $table) {
            $table->unsignedBigInteger('currency_id')->nullable()->after('amount');
            $table->decimal('interest_rate', 5, 2)->default(0.0)->after('currency_id'); // Annual interest rate
            $table->date('due_date')->nullable()->after('interest_rate');
            $table->decimal('interest_accrued', 15, 2)->default(0.0)->after('due_date');
            $table->timestamp('interest_last_calculated')->nullable()->after('interest_accrued');
            $table->string('payment_method')->nullable()->after('interest_last_calculated');
            $table->json('metadata')->nullable()->after('payment_method'); // Additional settlement data

            $table->foreign('currency_id')->references('id')->on('currencies')->onDelete('set null');
            $table->index(['due_date', 'status']);
            $table->index(['currency_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('settlements', function (Blueprint $table) {
            $table->dropForeign(['currency_id']);
            $table->dropIndex(['due_date', 'status']);
            $table->dropIndex(['currency_id']);
            $table->dropColumn([
                'currency_id',
                'interest_rate',
                'due_date',
                'interest_accrued',
                'interest_last_calculated',
                'payment_method',
                'metadata'
            ]);
        });
    }
};
