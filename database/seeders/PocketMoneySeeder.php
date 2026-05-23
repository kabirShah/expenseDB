<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PocketMoneySeeder extends Seeder
{
    public function run(): void
    {

        // -----------------------------------
        // Create 5 Users
        // -----------------------------------

        $users = [];

        for ($i = 1; $i <= 5; $i++) {

            $userId = DB::table('users')->insertGetId([
                'first_name' => "User",
                'last_name' => "$i",
                'email' => "user$i@test.com",
                'phone' => "900000000$i",
                'dob' => '1995-01-01',
                'gender' => 'Male',
                'password' => Hash::make('password'),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $users[] = $userId;
        }

        // -----------------------------------
        // Create Categories
        // -----------------------------------

        $foodId = DB::table('categories')->insertGetId([
            'name' => 'Food',
            'slug' => 'food',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $travelId = DB::table('categories')->insertGetId([
            'name' => 'Travel',
            'slug' => 'travel',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // -----------------------------------
        // Create Expenses (5 per user)
        // -----------------------------------

        foreach ($users as $userId) {

            for ($e = 1; $e <= 5; $e++) {

                DB::table('expenses')->insert([
                    'expense_id' => Str::uuid(),
                    'user_id' => $userId,
                    'category_id' => rand(0,1) ? $foodId : $travelId,
                    'transaction_type' => 'Cash',
                    'description' => "Expense $e for user $userId",
                    'amount' => rand(100,1000),
                    'date' => now(),
                    'notes' => 'Test expense',
                    'paid_by' => 'Self',
                    'location' => 'Ahmedabad',
                    'receipt_url' => null,
                    'status' => 'active',
                    'is_recurring' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

        }

        // -----------------------------------
        // Multi Expenses (5)
        // -----------------------------------

        foreach ($users as $userId) {

            for ($m = 1; $m <= 5; $m++) {

                DB::table('multi_expenses')->insert([
                    'user_id' => $userId,
                    'title' => "Bulk expense $m",
                    'total_amount' => rand(500,2000),
                    'description' => 'Multiple expense entry',
                    'category' => 'General',
                    'multi_expense_id' => Str::uuid(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

            }

        }

        // -----------------------------------
        // User Balances (5)
        // -----------------------------------

        for ($b = 1; $b <= 5; $b++) {

            DB::table('user_balances')->insert([
                'user_id' => $users[0],
                'owes_to_user_id' => $users[1],
                'amount' => rand(100,500),
                'currency_id' => null,
                'original_amount' => null,
                'exchange_rate' => 1,
                'group_id' => null,
                'description' => 'Sample balance',
                'last_updated' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

    }
}