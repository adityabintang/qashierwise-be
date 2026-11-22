<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use App\Models\Table;
use App\Models\User;
use Illuminate\Database\Seeder;

class AdminDemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::where('email', 'admin@qashierwise.com')->first();

        if (! $user) {
            $this->command->error('User admin@qashierwise.com tidak ditemukan. Jalankan UserSeeder terlebih dahulu.');

            return;
        }

        $this->seedStoresAndTables($user);
        $categories = $this->seedCategories($user);
        $this->seedProducts($user, $categories);

        $this->command->info('');
        $this->command->info('=== AdminDemoDataSeeder selesai ===');
        $this->command->info('User     : ' . $user->email);
        $this->command->info('Toko     : ' . Store::where('user_id', $user->id)->count());
        $this->command->info('Meja     : ' . Table::where('user_id', $user->id)->count());
        $this->command->info('Kategori : ' . Category::where('user_id', $user->id)->count());
        $this->command->info('Produk   : ' . Product::where('user_id', $user->id)->count());
    }

    private function seedStoresAndTables(User $user): void
    {
        $stores = [
            [
                'code'    => 'ADM-PUSAT',
                'name'    => 'Kafe Qashierwise Pusat',
                'address' => 'Jl. Sudirman No. 1, Jakarta Pusat',
                'phone'   => '021-12345678',
                'tables'  => [
                    ['number' => 'M01', 'capacity' => 2],
                    ['number' => 'M02', 'capacity' => 4],
                    ['number' => 'M03', 'capacity' => 4],
                    ['number' => 'M04', 'capacity' => 6],
                    ['number' => 'M05', 'capacity' => 6],
                    ['number' => 'M06', 'capacity' => 8],
                ],
            ],
            [
                'code'    => 'ADM-CAB01',
                'name'    => 'Kafe Qashierwise Cabang Selatan',
                'address' => 'Jl. Fatmawati No. 55, Jakarta Selatan',
                'phone'   => '021-87654321',
                'tables'  => [
                    ['number' => 'M01', 'capacity' => 2],
                    ['number' => 'M02', 'capacity' => 2],
                    ['number' => 'M03', 'capacity' => 4],
                    ['number' => 'M04', 'capacity' => 4],
                    ['number' => 'M05', 'capacity' => 6],
                ],
            ],
        ];

        foreach ($stores as $storeData) {
            $tables = $storeData['tables'];
            unset($storeData['tables']);

            $store = Store::firstOrCreate(
                ['code' => $storeData['code']],
                array_merge($storeData, [
                    'user_id'   => $user->id,
                    'is_active' => true,
                ])
            );

            foreach ($tables as $tableData) {
                Table::firstOrCreate(
                    ['store_id' => $store->id, 'number' => $tableData['number']],
                    array_merge($tableData, [
                        'user_id' => $user->id,
                        'status'  => Table::STATUS_AVAILABLE,
                    ])
                );
            }

            $this->command->info("Toko '{$store->name}' siap dengan " . count($tables) . ' meja.');
        }
    }

    private function seedCategories(User $user): array
    {
        $categoriesData = [
            [
                'slug'        => 'makanan-utama-adm',
                'name'        => 'Makanan Utama',
                'description' => 'Nasi, mie, dan hidangan utama lainnya',
            ],
            [
                'slug'        => 'minuman-panas-adm',
                'name'        => 'Minuman Panas',
                'description' => 'Kopi, teh, dan minuman hangat',
            ],
            [
                'slug'        => 'minuman-dingin-adm',
                'name'        => 'Minuman Dingin',
                'description' => 'Jus, es, dan minuman segar',
            ],
            [
                'slug'        => 'camilan-adm',
                'name'        => 'Camilan',
                'description' => 'Gorengan dan kudapan ringan',
            ],
            [
                'slug'        => 'paket-spesial-adm',
                'name'        => 'Paket Spesial',
                'description' => 'Paket hemat kombinasi makanan dan minuman',
            ],
        ];

        $result = [];
        foreach ($categoriesData as $data) {
            $category = Category::firstOrCreate(
                ['slug' => $data['slug']],
                array_merge($data, [
                    'user_id'   => $user->id,
                    'is_active' => true,
                ])
            );
            $result[$data['slug']] = $category;
        }

        return $result;
    }

    private function seedProducts(User $user, array $categories): void
    {
        $makanan  = $categories['makanan-utama-adm'];
        $minPanas = $categories['minuman-panas-adm'];
        $minDingin = $categories['minuman-dingin-adm'];
        $camilan  = $categories['camilan-adm'];
        $paket    = $categories['paket-spesial-adm'];

        $products = [
            // Makanan Utama
            [
                'category_id'    => $makanan->id,
                'sku'            => 'ADM-MK-001',
                'name'           => 'Nasi Goreng Spesial',
                'description'    => 'Nasi goreng dengan telur, ayam, dan sayuran pilihan',
                'price'          => 35000,
                'stock_quantity' => 50,
            ],
            [
                'category_id'    => $makanan->id,
                'sku'            => 'ADM-MK-002',
                'name'           => 'Mie Goreng Spesial',
                'description'    => 'Mie goreng dengan telur, bakso, dan sayuran',
                'price'          => 32000,
                'stock_quantity' => 50,
            ],
            [
                'category_id'    => $makanan->id,
                'sku'            => 'ADM-MK-003',
                'name'           => 'Nasi Uduk Komplit',
                'description'    => 'Nasi uduk dengan ayam goreng, tempe orek, dan sambal',
                'price'          => 38000,
                'stock_quantity' => 30,
            ],
            [
                'category_id'    => $makanan->id,
                'sku'            => 'ADM-MK-004',
                'name'           => 'Ayam Bakar Madu',
                'description'    => 'Ayam bakar dengan bumbu madu dan rempah pilihan',
                'price'          => 48000,
                'stock_quantity' => 25,
            ],
            [
                'category_id'    => $makanan->id,
                'sku'            => 'ADM-MK-005',
                'name'           => 'Soto Ayam',
                'description'    => 'Soto ayam kuning dengan soun, telur, dan pelengkap',
                'price'          => 30000,
                'stock_quantity' => 40,
            ],

            // Minuman Panas
            [
                'category_id'    => $minPanas->id,
                'sku'            => 'ADM-MP-001',
                'name'           => 'Kopi Tubruk',
                'description'    => 'Kopi hitam tradisional',
                'price'          => 12000,
                'stock_quantity' => 100,
            ],
            [
                'category_id'    => $minPanas->id,
                'sku'            => 'ADM-MP-002',
                'name'           => 'Teh Manis',
                'description'    => 'Teh hangat dengan gula pilihan',
                'price'          => 8000,
                'stock_quantity' => 100,
            ],
            [
                'category_id'    => $minPanas->id,
                'sku'            => 'ADM-MP-003',
                'name'           => 'Kopi Susu Panas',
                'description'    => 'Espresso dengan susu steamed',
                'price'          => 22000,
                'stock_quantity' => 100,
            ],
            [
                'category_id'    => $minPanas->id,
                'sku'            => 'ADM-MP-004',
                'name'           => 'Coklat Panas',
                'description'    => 'Dark chocolate dengan susu hangat',
                'price'          => 25000,
                'stock_quantity' => 80,
            ],

            // Minuman Dingin
            [
                'category_id'    => $minDingin->id,
                'sku'            => 'ADM-MD-001',
                'name'           => 'Es Teh Manis',
                'description'    => 'Teh manis dengan es batu',
                'price'          => 10000,
                'stock_quantity' => 100,
            ],
            [
                'category_id'    => $minDingin->id,
                'sku'            => 'ADM-MD-002',
                'name'           => 'Es Jeruk Peras',
                'description'    => 'Jeruk peras segar dengan es batu',
                'price'          => 18000,
                'stock_quantity' => 80,
            ],
            [
                'category_id'    => $minDingin->id,
                'sku'            => 'ADM-MD-003',
                'name'           => 'Jus Alpukat',
                'description'    => 'Jus alpukat segar dengan susu dan gula',
                'price'          => 28000,
                'stock_quantity' => 50,
            ],
            [
                'category_id'    => $minDingin->id,
                'sku'            => 'ADM-MD-004',
                'name'           => 'Es Kopi Susu',
                'description'    => 'Kopi susu dingin dengan es batu',
                'price'          => 25000,
                'stock_quantity' => 100,
            ],
            [
                'category_id'    => $minDingin->id,
                'sku'            => 'ADM-MD-005',
                'name'           => 'Jus Mangga',
                'description'    => 'Jus mangga segar tanpa tambahan gula',
                'price'          => 22000,
                'stock_quantity' => 50,
            ],

            // Camilan
            [
                'category_id'    => $camilan->id,
                'sku'            => 'ADM-CM-001',
                'name'           => 'Pisang Goreng Keju',
                'description'    => 'Pisang goreng crispy dengan taburan keju',
                'price'          => 18000,
                'stock_quantity' => 60,
            ],
            [
                'category_id'    => $camilan->id,
                'sku'            => 'ADM-CM-002',
                'name'           => 'Kentang Goreng',
                'description'    => 'Kentang goreng renyah dengan saus sambal',
                'price'          => 20000,
                'stock_quantity' => 60,
            ],
            [
                'category_id'    => $camilan->id,
                'sku'            => 'ADM-CM-003',
                'name'           => 'Tahu Crispy',
                'description'    => 'Tahu goreng crispy dengan saus kacang',
                'price'          => 15000,
                'stock_quantity' => 60,
            ],
            [
                'category_id'    => $camilan->id,
                'sku'            => 'ADM-CM-004',
                'name'           => 'Singkong Goreng',
                'description'    => 'Singkong goreng gurih dengan saus sambal',
                'price'          => 12000,
                'stock_quantity' => 50,
            ],

            // Paket Spesial
            [
                'category_id'    => $paket->id,
                'sku'            => 'ADM-PKT-001',
                'name'           => 'Paket Hemat A',
                'description'    => 'Nasi Goreng + Es Teh Manis',
                'price'          => 42000,
                'stock_quantity' => 30,
            ],
            [
                'category_id'    => $paket->id,
                'sku'            => 'ADM-PKT-002',
                'name'           => 'Paket Hemat B',
                'description'    => 'Ayam Bakar Madu + Nasi + Es Jeruk Peras',
                'price'          => 65000,
                'stock_quantity' => 30,
            ],
            [
                'category_id'    => $paket->id,
                'sku'            => 'ADM-PKT-003',
                'name'           => 'Paket Keluarga',
                'description'    => '4 Nasi Goreng + 4 Es Teh Manis + 2 Camilan',
                'price'          => 175000,
                'stock_quantity' => 15,
            ],
        ];

        foreach ($products as $productData) {
            Product::firstOrCreate(
                ['sku' => $productData['sku']],
                array_merge($productData, [
                    'user_id'   => $user->id,
                    'is_active' => true,
                ])
            );
        }
    }
}
