<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [            
            [
                'uuid' => Str::uuid(),
                'name' => 'Salary',
                'description' => 'Regular salary or wages from employment',
                'icon' => 'work',
                'type' => 'income',
                'colour_code' => '#28a745',
                'is_system' => true,
            ],
            [
                'uuid' => Str::uuid(),
                'name' => 'Freelance',
                'description' => 'Income from freelance work or consulting',
                'icon' => 'laptop',
                'type' => 'income',
                'colour_code' => '#17a2b8',
                'is_system' => true,
            ],
            [
                'uuid' => Str::uuid(),
                'name' => 'Investment',
                'description' => 'Returns from investments, dividends, or interest',
                'icon' => 'trending_up',
                'type' => 'income',
                'colour_code' => '#20c997',
                'is_system' => true,
            ],
            [
                'uuid' => Str::uuid(),
                'name' => 'Business',
                'description' => 'Income from business operations',
                'icon' => 'business',
                'type' => 'income',
                'colour_code' => '#6f42c1',
                'is_system' => true,
            ],
            [
                'uuid' => Str::uuid(),
                'name' => 'Gift',
                'description' => 'Money received as gifts',
                'icon' => 'card_giftcard',
                'type' => 'income',
                'colour_code' => '#fd7e14',
                'is_system' => true,
            ],            // Expense Categories
            [
                'uuid' => Str::uuid(),
                'name' => 'Food & Dining',
                'description' => 'Groceries, restaurants, and food expenses',
                'icon' => 'restaurant',
                'type' => 'expense',
                'colour_code' => '#dc3545',
                'is_system' => true,
            ],
            [
                'uuid' => Str::uuid(),
                'name' => 'Transportation',
                'description' => 'Car expenses, public transport, fuel, parking',
                'icon' => 'directions_car',
                'type' => 'expense',
                'colour_code' => '#6c757d',
                'is_system' => true,
            ],
            [
                'uuid' => Str::uuid(),
                'name' => 'Housing',
                'description' => 'Rent, mortgage, utilities, home maintenance',
                'icon' => 'home',
                'type' => 'expense',
                'colour_code' => '#495057',
                'is_system' => true,
            ],
            [
                'uuid' => Str::uuid(),
                'name' => 'Healthcare',
                'description' => 'Medical expenses, insurance, medications',
                'icon' => 'local_hospital',
                'type' => 'expense',
                'colour_code' => '#e83e8c',
                'is_system' => true,
            ],
            [
                'uuid' => Str::uuid(),
                'name' => 'Entertainment',
                'description' => 'Movies, games, hobbies, subscriptions',
                'icon' => 'sports_esports',
                'type' => 'expense',
                'colour_code' => '#6f42c1',
                'is_system' => true,
            ],
            [
                'uuid' => Str::uuid(),
                'name' => 'Shopping',
                'description' => 'Clothing, electronics, general shopping',
                'icon' => 'shopping_bag',
                'type' => 'expense',
                'colour_code' => '#fd7e14',
                'is_system' => true,
            ],
            [
                'uuid' => Str::uuid(),
                'name' => 'Education',
                'description' => 'Books, courses, tuition, training',
                'icon' => 'school',
                'type' => 'expense',
                'colour_code' => '#20c997',
                'is_system' => true,
            ],
            [
                'uuid' => Str::uuid(),
                'name' => 'Travel',
                'description' => 'Vacation, business trips, accommodation',
                'icon' => 'flight',
                'type' => 'expense',
                'colour_code' => '#17a2b8',
                'is_system' => true,
            ],
            [
                'uuid' => Str::uuid(),
                'name' => 'Insurance',
                'description' => 'Life, health, car, home insurance premiums',
                'icon' => 'security',
                'type' => 'expense',
                'colour_code' => '#343a40',
                'is_system' => true,
            ],
            [
                'uuid' => Str::uuid(),
                'name' => 'Taxes',
                'description' => 'Income tax, property tax, other taxes',
                'icon' => 'calculate',
                'type' => 'expense',
                'colour_code' => '#6c757d',
                'is_system' => true,
            ],
            [
                'uuid' => Str::uuid(),
                'name' => 'Bills & Utilities',
                'description' => 'Electricity, water, internet, phone bills',
                'icon' => 'receipt',
                'type' => 'expense',
                'colour_code' => '#ffc107',
                'is_system' => true,
            ],
            [
                'uuid' => Str::uuid(),
                'name' => 'Personal Care',
                'description' => 'Haircuts, cosmetics, gym memberships',
                'icon' => 'person',
                'type' => 'expense',
                'colour_code' => '#e83e8c',
                'is_system' => true,
            ],            // Transfer Categories
            [
                'uuid' => Str::uuid(),
                'name' => 'Account Transfer',
                'description' => 'Transfers between own accounts',
                'icon' => 'swap_horiz',
                'type' => 'transfer',
                'colour_code' => '#007bff',
                'is_system' => true,
            ],
            [
                'uuid' => Str::uuid(),
                'name' => 'Savings',
                'description' => 'Transfer to savings account',
                'icon' => 'savings',
                'type' => 'transfer',
                'colour_code' => '#28a745',
                'is_system' => true,
            ],

            // General Categories
            [
                'uuid' => Str::uuid(),
                'name' => 'Other Income',
                'description' => 'Miscellaneous income not categorized elsewhere',
                'icon' => 'add_circle',
                'type' => 'income',
                'colour_code' => '#6c757d',
                'is_system' => true,
            ],
            [
                'uuid' => Str::uuid(),
                'name' => 'Other Expense',
                'description' => 'Miscellaneous expenses not categorized elsewhere',
                'icon' => 'remove_circle',
                'type' => 'expense',
                'colour_code' => '#6c757d',
                'is_system' => true,
            ],
        ];

        foreach ($categories as $category) {
            DB::table('categories')->insert([
                'uuid' => $category['uuid'],
                'name' => $category['name'],
                'description' => $category['description'],
                'icon' => $category['icon'],
                'type' => $category['type'],
                'colour_code' => $category['colour_code'],
                'is_system' => $category['is_system'],
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
        }
    }
}