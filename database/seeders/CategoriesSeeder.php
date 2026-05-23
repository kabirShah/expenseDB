<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class CategoriesSeeder extends Seeder
{
    public function run(): void
    {
        if (Schema::hasColumn('categories', 'user_id')) {
            $defaults = [
                ['name' => 'Food', 'icon' => 'restaurant', 'color' => '#FF6B6B', 'type' => 'expense'],
                ['name' => 'Travel', 'icon' => 'airplane', 'color' => '#4ECDC4', 'type' => 'expense'],
                ['name' => 'Food & Dining', 'icon' => 'restaurant', 'color' => '#FF6B6B', 'type' => 'expense'],
                ['name' => 'Transport', 'icon' => 'car', 'color' => '#4ECDC4', 'type' => 'expense'],
                ['name' => 'Shopping', 'icon' => 'bag', 'color' => '#45B7D1', 'type' => 'expense'],
                ['name' => 'Entertainment', 'icon' => 'game-controller', 'color' => '#96CEB4', 'type' => 'expense'],
                ['name' => 'Health', 'icon' => 'medical', 'color' => '#FFEAA7', 'type' => 'expense'],
                ['name' => 'Education', 'icon' => 'school', 'color' => '#DDA0DD', 'type' => 'expense'],
                ['name' => 'Bills & Utilities', 'icon' => 'flash', 'color' => '#98D8C8', 'type' => 'expense'],
                ['name' => 'Grocery', 'icon' => 'cart', 'color' => '#F7DC6F', 'type' => 'expense'],
                ['name' => 'Salary', 'icon' => 'cash', 'color' => '#58D68D', 'type' => 'income'],
                ['name' => 'Other Income', 'icon' => 'wallet', 'color' => '#85C1E9', 'type' => 'income'],
                ['name' => 'Bills', 'icon' => 'receipt', 'color' => '#98D8C8', 'type' => 'expense'],
                ['name' => 'Others', 'icon' => 'ellipsis-horizontal', 'color' => '#BDC3C7', 'type' => 'both'],
                ['name' => 'Other', 'icon' => 'ellipsis-h', 'color' => '#BDC3C7', 'type' => 'both'],
            ];

            foreach ($defaults as $category) {
                Category::query()->firstOrCreate(
                    ['name' => $category['name'], 'user_id' => null],
                    array_filter([
                        'icon' => $category['icon'],
                        'color' => $category['color'],
                        'type' => $category['type'],
                        'is_default' => true,
                        'slug' => Schema::hasColumn('categories', 'slug') ? Str::slug($category['name']) : null,
                        'parent_id' => Schema::hasColumn('categories', 'parent_id') ? null : null,
                    ], static fn ($value) => $value !== null)
                );
            }

            return;
        }

        $categories = [
            'Food & Drinks' => [
                'Groceries',
                'Fruits & Vegetables',
                'Snacks',
                'Coffee & Tea',
                'Restaurants',
                'Fast Food',
                'Bakery',
                'Sweets & Ice Cream',
            ],
            'Transportation' => [
                'Fuel',
                'Taxi / Auto',
                'Public Transport',
                'Parking',
                'Car Service & Maintenance',
                'Bike Service & Repair',
            ],
            'Shopping' => [
                'Clothing',
                'Shoes',
                'Electronics',
                'Home Essentials',
                'Online Shopping',
                'Accessories',
            ],
            'Bills & Utilities' => [
                'Electricity Bill',
                'Water Bill',
                'Gas Bill',
                'Internet',
                'Mobile Recharge',
                'DTH / TV Subscription',
                'Home Maintenance',
            ],
            'Housing' => [
                'Rent',
                'EMI',
                'Property Tax',
                'Home Repairs',
                'Furnitures',
            ],
            'Health & Fitness' => [
                'Hospital Bills',
                'Medicines',
                'Doctor Consultation',
                'Gym',
                'Yoga',
                'Health Insurance',
            ],
            'Entertainment' => [
                'Movies',
                'Events',
                'Music',
                'Gaming',
                'OTT Subscription',
            ],
            'Travel' => [
                'Flight Tickets',
                'Train Tickets',
                'Hotel',
                'Travel Food',
                'Cab',
                'Travel Shopping',
            ],
            'Education' => [
                'School Fees',
                'College Fees',
                'Books & Stationery',
                'Coaching Classes',
                'Courses',
            ],
            'Personal Care' => [
                'Salon',
                'Spa',
                'Cosmetics',
                'Skincare',
                'Haircut',
            ],
            'Savings & Investments' => [
                'Bank Savings',
                'Fixed Deposit',
                'Mutual Funds',
                'Stock Market',
                'Gold',
                'Crypto',
            ],
            'Insurance' => [
                'Health Insurance',
                'Life Insurance',
                'Vehicle Insurance',
                'Home Insurance',
            ],
            'Loans' => [
                'Personal Loan EMI',
                'Home Loan EMI',
                'Car Loan EMI',
                'Education Loan EMI',
            ],
            'Gifts & Donations' => [
                'Gifts',
                'Charity',
                'Temple',
                'Fundraising',
            ],
            'Business & Work' => [
                'Office Supplies',
                'Client Meetings',
                'Business Travel',
                'Freelance Tools',
                'Software Subscriptions',
            ],
            'Pets' => [
                'Pet Food',
                'Veterinary',
                'Pet Accessories',
            ],
            'Miscellaneous' => [
                'Unknown Expense',
                'Cash Withdrawal',
                'Other',
            ],
        ];

        foreach ($categories as $main => $subs) {
            $mainCategory = Category::create([
                'name' => $main,
                'slug' => Str::slug($main),
                'parent_id' => null,
            ]);

            foreach ($subs as $sub) {
                Category::create([
                    'name' => $sub,
                    'slug' => Str::slug($sub),
                    'parent_id' => $mainCategory->id,
                ]);
            }
        }
    }
}
