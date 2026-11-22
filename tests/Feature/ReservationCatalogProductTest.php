<?php

namespace Tests\Feature;

use App\Models\AiAgent;
use App\Models\CatalogProduct;
use App\Models\ReservationConfig;
use App\Models\Store;
use App\Models\User;
use App\Models\WhatsAppAccount;
use App\Services\ReservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Reservation products are sourced from the merchant's BOUND Meta Catalog
 * (catalog_products scoped to ai_agents.catalog_id), not the deprecated master
 * Product table and not other catalogs the merchant may have synced.
 */
class ReservationCatalogProductTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Bind a Meta catalog to the merchant (active WA account + AiAgent), the way
     * CatalogService::getBoundCatalogId() resolves it.
     */
    private function bindCatalog(User $merchant, string $catalogId): void
    {
        $account = WhatsAppAccount::factory()->create([
            'user_id' => $merchant->id,
            'is_active' => true,
        ]);

        AiAgent::factory()->create([
            'whatsapp_account_id' => $account->id,
            'catalog_id' => $catalogId,
        ]);
    }

    public function test_public_form_lists_only_bound_catalog_products_configured_for_reservation(): void
    {
        $boundCatalog = '1759647168503815';

        $merchant = User::factory()->create(['slug' => 'warung-test']);
        $store = Store::factory()->create(['user_id' => $merchant->id]);
        $this->bindCatalog($merchant, $boundCatalog);

        $included = CatalogProduct::factory()->create([
            'user_id' => $merchant->id,
            'catalog_id' => $boundCatalog,
            'name' => 'Ayam Bakar Madu',
            'price' => 48000,
        ]);
        // Configured AND available, but lives in a DIFFERENT (unbound) catalog.
        $fromOtherCatalog = CatalogProduct::factory()->create([
            'user_id' => $merchant->id,
            'catalog_id' => '999999999999',
            'name' => 'Unbound Dish',
            'price' => 50000,
        ]);
        $unavailable = CatalogProduct::factory()->unavailable()->create([
            'user_id' => $merchant->id,
            'catalog_id' => $boundCatalog,
            'name' => 'Sold Out Dish',
        ]);

        ReservationConfig::factory()->create([
            'user_id' => $merchant->id,
            'store_id' => $store->id,
            'is_active' => true,
            'enable_menu_selection' => true,
            'available_products' => [$included->id, $fromOtherCatalog->id, $unavailable->id],
            'reminder_template_language' => 'id',
        ]);

        $response = $this->getJson('/reservations/products?merchantName=warung-test&store_id='.$store->id);

        $response->assertOk()->assertJson(['success' => true]);

        $names = collect($response->json('data'))->pluck('name')->all();
        $this->assertContains('Ayam Bakar Madu', $names);
        $this->assertNotContains('Unbound Dish', $names);   // not in the bound catalog
        $this->assertNotContains('Sold Out Dish', $names);  // unavailable
    }

    public function test_calculate_amounts_uses_bound_catalog_product_prices(): void
    {
        $boundCatalog = '1759647168503815';

        $merchant = User::factory()->create();
        $store = Store::factory()->create(['user_id' => $merchant->id]);
        $this->bindCatalog($merchant, $boundCatalog);

        $product = CatalogProduct::factory()->create([
            'user_id' => $merchant->id,
            'catalog_id' => $boundCatalog,
            'price' => 50000,
        ]);

        $config = ReservationConfig::factory()->create([
            'user_id' => $merchant->id,
            'store_id' => $store->id,
            'reservation_fee' => 20000,
            'dp_percentage' => 50,
            'reminder_template_language' => 'id',
        ]);

        $amounts = app(ReservationService::class)->calculateAmounts([
            'selected_products' => [['id' => $product->id, 'quantity' => 2]],
        ], $config);

        // 20000 fee + (50000 * 2) = 120000 total; DP 50% = 60000.
        $this->assertSame(120000.0, $amounts['total_amount']);
        $this->assertSame(60000.0, $amounts['dp_amount']);
        $this->assertSame(60000.0, $amounts['remaining_amount']);
    }

    public function test_calculate_amounts_ignores_products_outside_the_bound_catalog(): void
    {
        $boundCatalog = '1759647168503815';

        $merchant = User::factory()->create();
        $store = Store::factory()->create(['user_id' => $merchant->id]);
        $this->bindCatalog($merchant, $boundCatalog);

        // Same merchant, but a product in a different (unbound) catalog.
        $unboundProduct = CatalogProduct::factory()->create([
            'user_id' => $merchant->id,
            'catalog_id' => '999999999999',
            'price' => 999999,
        ]);

        $config = ReservationConfig::factory()->create([
            'user_id' => $merchant->id,
            'store_id' => $store->id,
            'reservation_fee' => 10000,
            'dp_percentage' => 50,
            'reminder_template_language' => 'id',
        ]);

        $amounts = app(ReservationService::class)->calculateAmounts([
            'selected_products' => [['id' => $unboundProduct->id, 'quantity' => 1]],
        ], $config);

        // Unbound product is not summed — only the base fee remains.
        $this->assertSame(10000.0, $amounts['total_amount']);
    }
}
