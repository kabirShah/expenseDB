<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop ONLY receipt duplicate/child tables that are not used by current code paths.
        // If any environment still depends on these tables, revert this migration.

        Schema::dropIfExists('receipt_activity');
        Schema::dropIfExists('receipt_versions');
        Schema::dropIfExists('receipt_reviews');
        Schema::dropIfExists('receipt_items');
    }

    public function down(): void
    {
        // Recreate tables is intentionally not implemented.
        // This migration is meant to be run only when you are sure the tables are unused.
        // If you need rollback, restore from previous migration state.
    }
};

