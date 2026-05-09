<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\PosUser;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class PosReportDataSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::where('email', 'admin@qashierwise.com')->first();

        if (! $user) {
            $this->command->error('User admin@qashierwise.com tidak ditemukan. Jalankan AdminDemoDataSeeder terlebih dahulu.');

            return;
        }

        $store = Store::where('user_id', $user->id)->first();

        if (! $store) {
            $this->command->error('Store tidak ditemukan. Jalankan AdminDemoDataSeeder terlebih dahulu.');

            return;
        }

        $posUser = PosUser::where('store_id', $store->id)->first();

        if (! $posUser) {
            $managerRole = Role::where('name', 'Manager')->where('guard_name', 'sanctum')->first();

            $posUser = PosUser::create([
                'user_id'   => $user->id,
                'store_id'  => $store->id,
                'role_id'   => $managerRole?->id,
                'is_active' => true,
            ]);
        }

        $products = Product::where('user_id', $user->id)->get();

        if ($products->isEmpty()) {
            $this->command->error('Produk tidak ditemukan. Jalankan AdminDemoDataSeeder terlebih dahulu.');

            return;
        }

        $paymentMethods = [
            Payment::METHOD_CASH,
            Payment::METHOD_CASH,
            Payment::METHOD_CASH,
            Payment::METHOD_QRIS,
            Payment::METHOD_QRIS,
            Payment::METHOD_TRANSFER,
            Payment::METHOD_CARD,
        ];

        $customerNames = [
            'Budi Santoso', 'Siti Rahayu', 'Ahmad Fauzi', 'Dewi Lestari',
            'Riko Pratama', 'Nia Kurniawati', 'Hendra Wijaya', 'Maya Sari',
            'Fajar Nugroho', 'Indah Permata',
        ];

        $totalOrders = 0;

        // Buat data 30 hari ke belakang
        for ($daysAgo = 29; $daysAgo >= 0; $daysAgo--) {
            $date = Carbon::now()->subDays($daysAgo);

            // Variasikan jumlah order per hari (3-12 order)
            $ordersPerDay = rand(3, 12);

            for ($i = 0; $i < $ordersPerDay; $i++) {
                $orderTime = $date->copy()->setTime(rand(8, 21), rand(0, 59), rand(0, 59));
                $orderNumber = 'ORD-' . $orderTime->format('Ymd') . '-' . str_pad($totalOrders + 1, 4, '0', STR_PAD_LEFT);

                // Pilih 1-4 produk acak per order
                $selectedProducts = $products->random(min(rand(1, 4), $products->count()));

                $subtotal = 0;
                $itemsData = [];

                foreach ($selectedProducts as $product) {
                    $quantity = rand(1, 3);
                    $unitPrice = $product->price;
                    $itemSubtotal = $unitPrice * $quantity;
                    $subtotal += $itemSubtotal;

                    $itemsData[] = [
                        'product_id' => $product->id,
                        'quantity'   => $quantity,
                        'unit_price' => $unitPrice,
                        'subtotal'   => $itemSubtotal,
                    ];
                }

                $taxAmount      = round($subtotal * 0.11);
                $discountAmount = 0;
                $total          = $subtotal + $taxAmount - $discountAmount;

                $order = Order::create([
                    'store_id'        => $store->id,
                    'pos_user_id'     => $posUser->id,
                    'order_number'    => $orderNumber,
                    'status'          => Order::STATUS_PAID,
                    'source'          => Order::SOURCE_POS,
                    'customer_name'   => $customerNames[array_rand($customerNames)],
                    'subtotal'        => $subtotal,
                    'tax_amount'      => $taxAmount,
                    'discount_amount' => $discountAmount,
                    'total'           => $total,
                    'created_at'      => $orderTime,
                    'updated_at'      => $orderTime,
                ]);

                foreach ($itemsData as $item) {
                    OrderItem::create(array_merge($item, ['order_id' => $order->id]));
                }

                $method = $paymentMethods[array_rand($paymentMethods)];

                Payment::create([
                    'order_id' => $order->id,
                    'method'   => $method,
                    'amount'   => $total,
                    'status'   => Payment::STATUS_PAID,
                    'paid_at'  => $orderTime,
                ]);

                $totalOrders++;
            }
        }

        $this->command->info('');
        $this->command->info('=== PosReportDataSeeder selesai ===');
        $this->command->info("User  : {$user->email}");
        $this->command->info("Store : {$store->name}");
        $this->command->info("Total order dibuat : {$totalOrders}");
        $this->command->info('Data laporan siap dilihat di /dashboard/pos/reports');
    }
}
