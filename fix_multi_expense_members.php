<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

if (!Schema::hasColumn('multi_expense_members', 'multi_expense_id')) {
    Schema::table('multi_expense_members', function (Blueprint $table) {
        $table->foreignId('multi_expense_id')->constrained('multi_expenses')->onDelete('cascade');
        $table->foreignId('user_id')->constrained()->onDelete('cascade');
        $table->decimal('amount_owed', 10, 2);
        $table->decimal('amount_paid', 10, 2)->default(0);
        $table->enum('status', ['pending', 'partially_paid', 'settled'])->default('pending');
        $table->uuid('multi_expense_member_id')->unique();
    });
    echo "Missing columns added to multi_expense_members table.\n";
} else {
    echo "Columns already exist in multi_expense_members table.\n";
}

echo "Fix completed.\n";
