<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;     // ✅ IMPORT MODEL
use Illuminate\Support\Str;  // ✅ IMPORT STR FOR SLUGS

class CategoriesSeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            "Food & Drinks" => [
                "Groceries",
                "Fruits & Vegetables",
                "Snacks",
                "Coffee & Tea",
                "Restaurants",
                "Fast Food",
                "Bakery",
                "Sweets & Ice Cream"
            ],
            "Transportation" => [
                "Fuel",
                "Taxi / Auto",
                "Public Transport",
                "Parking",
                "Car Service & Maintenance",
                "Bike Service & Repair"
            ],
            "Shopping" => [
                "Clothing",
                "Shoes",
                "Electronics",
                "Home Essentials",
                "Online Shopping",
                "Accessories"
            ],
            "Bills & Utilities" => [
                "Electricity Bill",
                "Water Bill",
                "Gas Bill",
                "Internet",
                "Mobile Recharge",
                "DTH / TV Subscription",
                "Home Maintenance"
            ],
            "Housing" => [
                "Rent",
                "EMI",
                "Property Tax",
                "Home Repairs",
                "Furnitures"
            ],
            "Health & Fitness" => [
                "Hospital Bills",
                "Medicines",
                "Doctor Consultation",
                "Gym",
                "Yoga",
                "Health Insurance"
            ],
            "Entertainment" => [
                "Movies",
                "Events",
                "Music",
                "Gaming",
                "OTT Subscription"
            ],
            "Travel" => [
                "Flight Tickets",
                "Train Tickets",
                "Hotel",
                "Travel Food",
                "Cab",
                "Travel Shopping"
            ],
            "Education" => [
                "School Fees",
                "College Fees",
                "Books & Stationery",
                "Coaching Classes",
                "Courses"
            ],
            "Personal Care" => [
                "Salon",
                "Spa",
                "Cosmetics",
                "Skincare",
                "Haircut"
            ],
            "Savings & Investments" => [
                "Bank Savings",
                "Fixed Deposit",
                "Mutual Funds",
                "Stock Market",
                "Gold",
                "Crypto"
            ],
            "Insurance" => [
                "Health Insurance",
                "Life Insurance",
                "Vehicle Insurance",
                "Home Insurance"
            ],
            "Loans" => [
                "Personal Loan EMI",
                "Home Loan EMI",
                "Car Loan EMI",
                "Education Loan EMI"
            ],
            "Gifts & Donations" => [
                "Gifts",
                "Charity",
                "Temple",
                "Fundraising"
            ],
            "Business & Work" => [
                "Office Supplies",
                "Client Meetings",
                "Business Travel",
                "Freelance Tools",
                "Software Subscriptions"
            ],
            "Pets" => [
                "Pet Food",
                "Veterinary",
                "Pet Accessories"
            ],
            "Miscellaneous" => [
                "Unknown Expense",
                "Cash Withdrawal",
                "Other"
            ]
        ];

        foreach ($categories as $main => $subs) {

            // Create main category
            $mainCategory = Category::create([
                'name' => $main,
                'slug' => Str::slug($main),
                'parent_id' => null
            ]);

            // Create subcategories
            foreach ($subs as $sub) {
                Category::create([
                    'name' => $sub,
                    'slug' => Str::slug($sub),
                    'parent_id' => $mainCategory->id
                ]);
            }
        }
    }
}
