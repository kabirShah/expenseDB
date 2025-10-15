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
        Schema::table('expenses', function (Blueprint $table) {
            // Add UUID for better external reference
            if (!Schema::hasColumn('expenses', 'expense_id')) {
                $table->uuid('expense_id')->unique()->after('id');
            }
            
            // Add notes field
            if (!Schema::hasColumn('expenses', 'notes')) {
                $table->text('notes')->nullable()->after('date');
            }
            
            // Add paid_by field
            if (!Schema::hasColumn('expenses', 'paid_by')) {
                $table->string('paid_by')->nullable()->after('notes')->comment('Who paid for this expense');
            }
            
            // Add location field for geotagging
            if (!Schema::hasColumn('expenses', 'location')) {
                $table->string('location')->nullable()->after('paid_by');
            }
            
            // Add receipt attachment field
            if (!Schema::hasColumn('expenses', 'receipt_url')) {
                $table->string('receipt_url')->nullable()->after('location');
            }
            
            // Add status field
            if (!Schema::hasColumn('expenses', 'status')) {
                $table->string('status')->default('active')->after('receipt_url')->comment('active, archived, deleted');
            }
            
            // Add recurrence fields
            if (!Schema::hasColumn('expenses', 'is_recurring')) {
                $table->boolean('is_recurring')->default(false)->after('status');
            }
            
            if (!Schema::hasColumn('expenses', 'recurrence_pattern')) {
                $table->string('recurrence_pattern')->nullable()->after('is_recurring')->comment('daily, weekly, monthly, yearly');
            }
            
            if (!Schema::hasColumn('expenses', 'next_recurrence_date')) {
                $table->date('next_recurrence_date')->nullable()->after('recurrence_pattern');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropColumn([
                'expense_id',
                'notes',
                'paid_by',
                'location',
                'receipt_url',
                'status',
                'is_recurring',
                'recurrence_pattern',
                'next_recurrence_date'
            ]);
        });
    }
};
