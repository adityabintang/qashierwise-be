<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\PosUser;
use App\Models\Product;
use App\Models\Store;
use App\Models\Table;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class PosTestDataSeeder extends Seeder
{
    /**
     * Seed test data for POS system.
     */
    public function run(): void
    {
        // Get Spatie roles (should already exist from RoleSeeder)
        $role = Role::where('name', 'Cashier')->where('guard_name', 'sanctum')->first();
        $managerRole = Role::where('name', 'Manager')->where('guard_name', 'sanctum')->first();

        if (! $role || ! $managerRole) {
            $this->command->error('Roles not found! Please run RoleSeeder first.');

            return;
        }

        // Create a store
        $store = Store::firstOrCreate(
            ['code' => 'MAIN001'],
            [
                'name' => 'Main Store',
                'address' => '123 Main Street',
                'phone' => '555-0100',
                'is_active' => true,
            ]
        );

        // Create tables for the store
        for ($i = 1; $i <= 5; $i++) {
            Table::firstOrCreate(
                ['store_id' => $store->id, 'number' => "T{$i}"],
                [
                    'capacity' => rand(2, 6),
                    'status' => 'available',
                ]
            );
        }

        // Get or create a user
        $user = User::first();
        if (! $user) {
            $user = User::create([
                'name' => 'Test User',
                'email' => 'test@example.com',
                'password' => bcrypt('password'),
            ]);
        }

        // Create a POS user
        $posUser = PosUser::firstOrCreate(
            ['user_id' => $user->id, 'store_id' => $store->id],
            [
                'role_id' => $managerRole->id,
                'is_active' => true,
            ]
        );

        // Assign role to user via Spatie
        $user->syncRoles([$managerRole->name]);

        // Create categories
        $categories = [
            ['name' => 'Beverages', 'description' => 'Drinks and beverages'],
            ['name' => 'Food', 'description' => 'Main dishes and snacks'],
            ['name' => 'Desserts', 'description' => 'Sweet treats'],
        ];

        foreach ($categories as $categoryData) {
            Category::firstOrCreate(
                ['name' => $categoryData['name']],
                array_merge($categoryData, ['is_active' => true])
            );
        }

        // Create products
        $beverages = Category::where('name', 'Beverages')->first();
        $food = Category::where('name', 'Food')->first();
        $desserts = Category::where('name', 'Desserts')->first();

        $products = [
            ['category_id' => $beverages->id, 'name' => 'Coffee', 'sku' => 'BEV-COF-001', 'price' => 25000, 'stock_quantity' => 100],
            ['category_id' => $beverages->id, 'name' => 'Tea', 'sku' => 'BEV-TEA-001', 'price' => 15000, 'stock_quantity' => 100],
            ['category_id' => $beverages->id, 'name' => 'Orange Juice', 'sku' => 'BEV-OJ-001', 'price' => 20000, 'stock_quantity' => 50],
            ['category_id' => $food->id, 'name' => 'Burger', 'sku' => 'FOOD-BUR-001', 'price' => 45000, 'stock_quantity' => 30],
            ['category_id' => $food->id, 'name' => 'Pizza', 'sku' => 'FOOD-PIZ-001', 'price' => 75000, 'stock_quantity' => 20],
            ['category_id' => $food->id, 'name' => 'Sandwich', 'sku' => 'FOOD-SAN-001', 'price' => 35000, 'stock_quantity' => 40],
            ['category_id' => $desserts->id, 'name' => 'Ice Cream', 'sku' => 'DES-ICE-001', 'price' => 20000, 'stock_quantity' => 50],
            ['category_id' => $desserts->id, 'name' => 'Cake Slice', 'sku' => 'DES-CAK-001', 'price' => 30000, 'stock_quantity' => 25],
        ];

        foreach ($products as $productData) {
            Product::firstOrCreate(
                ['name' => $productData['name']],
                array_merge($productData, ['is_active' => true])
            );
        }

        $this->command->info('POS test data seeded successfully!');
        $this->command->info("Store ID: {$store->id}");
        $this->command->info("POS User ID: {$posUser->id}");
        $this->command->info('Tables: '.Table::where('store_id', $store->id)->count());
        $this->command->info('Categories: '.Category::count());
        $this->command->info('Products: '.Product::count());
    }
}
