<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

// Check if the migration is already in the migrations table
$migrationExists = DB::table('migrations')
    ->where('migration', '2025_08_19_033610_add_google_id_to_users_table')
    ->exists();

if (!$migrationExists) {
    // Insert the migration as completed
    DB::table('migrations')->insert([
        'migration' => '2025_08_19_033610_add_google_id_to_users_table',
        'batch' => 13
    ]);
    echo "Migration marked as completed.\n";
} else {
    echo "Migration already exists in the table.\n";
}

echo "Done.\n";
