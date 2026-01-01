<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Reservation;
use App\Models\Table;
use App\Models\User;
use App\Services\WhatsAppFlowEncryptionService;
use App\Services\WhatsAppFlowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WhatsAppFlowEndpointTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    /**
     * Test that endpoint returns error when not configured.
     */
    public function test_endpoint_returns_error_when_not_configured(): void
    {
        $response = $this->postJson('/api/whatsapp/flow/endpoint', [
            'encrypted_aes_key' => 'test',
            'encrypted_flow_data' => 'test',
            'initial_vector' => 'test',
        ]);

        $response->assertStatus(500);
        $response->assertJson(['error' => 'Endpoint not configured']);
    }

    /**
     * Test that endpoint returns error when missing parameters.
     */
    public function test_endpoint_returns_error_when_missing_parameters(): void
    {
        // Mock the encryption service as configured
        $this->mock(WhatsAppFlowEncryptionService::class, function ($mock) {
            $mock->shouldReceive('isConfigured')->andReturn(true);
        });

        $response = $this->postJson('/api/whatsapp/flow/endpoint', []);

        $response->assertStatus(400);
        $response->assertJson(['error' => 'Missing encryption parameters']);
    }

    /**
     * Test available dates helper method.
     */
    public function test_get_available_dates_returns_correct_format(): void
    {
        $service = app(WhatsAppFlowService::class);
        $dates = $service->getAvailableDates(7);

        $this->assertCount(7, $dates);
        $this->assertArrayHasKey('id', $dates[0]);
        $this->assertArrayHasKey('title', $dates[0]);

        // Check date format is Y-m-d
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $dates[0]['id']);
    }

    /**
     * Test available time slots returns all slots when no reservations.
     */
    public function test_get_available_time_slots_returns_all_when_no_reservations(): void
    {
        $service = app(WhatsAppFlowService::class);
        $times = $service->getAvailableTimeSlots('2026-01-15', $this->user->id);

        // Should have slots from 10:00 to 21:00 (12 hours)
        $this->assertCount(12, $times);
        $this->assertEquals('10:00', $times[0]['id']);
        $this->assertEquals('21:00', $times[11]['id']);
    }

    /**
     * Test available time slots excludes booked slots.
     */
    public function test_get_available_time_slots_excludes_booked_slots(): void
    {
        // Create a reservation at 12:00
        Reservation::factory()->create([
            'user_id' => $this->user->id,
            'reservation_date' => '2026-01-15',
            'reservation_time' => '12:00',
            'status' => 'confirmed',
        ]);

        $service = app(WhatsAppFlowService::class);
        $times = $service->getAvailableTimeSlots('2026-01-15', $this->user->id);

        // Find the 12:00 slot
        $bookedSlot = collect($times)->firstWhere('id', '12:00');

        $this->assertNotNull($bookedSlot);
        $this->assertFalse($bookedSlot['enabled']);
        $this->assertStringContainsString('Terisi', $bookedSlot['title']);
    }

    /**
     * Test products for checkbox returns correct format.
     */
    public function test_get_products_for_checkbox_returns_correct_format(): void
    {
        Product::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Nasi Goreng',
            'price' => 25000,
            'is_active' => true,
        ]);

        $service = app(WhatsAppFlowService::class);
        $products = $service->getProductsForCheckbox($this->user->id);

        $this->assertCount(1, $products);
        $this->assertArrayHasKey('id', $products[0]);
        $this->assertArrayHasKey('title', $products[0]);
        $this->assertStringContainsString('Nasi Goreng', $products[0]['title']);
        $this->assertStringContainsString('Rp25.000', $products[0]['title']);
    }

    /**
     * Test tables for dropdown returns only available tables.
     */
    public function test_get_tables_for_dropdown_returns_only_available(): void
    {
        Table::factory()->create([
            'user_id' => $this->user->id,
            'number' => '1',
            'capacity' => 4,
            'status' => Table::STATUS_AVAILABLE,
        ]);

        Table::factory()->create([
            'user_id' => $this->user->id,
            'number' => '2',
            'capacity' => 6,
            'status' => Table::STATUS_OCCUPIED,
        ]);

        $service = app(WhatsAppFlowService::class);
        $tables = $service->getTablesForDropdown($this->user->id);

        // Should only return the available table
        $this->assertCount(1, $tables);
        $this->assertStringContainsString('Meja 1', $tables[0]['title']);
        $this->assertStringContainsString('4 orang', $tables[0]['title']);
    }

    /**
     * Test calculate total sums products and adds table fee.
     */
    public function test_calculate_total_includes_table_fee(): void
    {
        $product1 = Product::factory()->create([
            'user_id' => $this->user->id,
            'price' => 25000,
            'is_active' => true,
        ]);

        $product2 = Product::factory()->create([
            'user_id' => $this->user->id,
            'price' => 30000,
            'is_active' => true,
        ]);

        $service = app(WhatsAppFlowService::class);
        $total = $service->calculateTotal([$product1->id, $product2->id], $this->user->id);

        // Menu: 25000 + 30000 = 55000, Table fee: 100000, Total: 155000
        $this->assertEquals(155000, $total);
    }

    /**
     * Test calculate deposit returns 50%.
     */
    public function test_calculate_deposit_returns_fifty_percent(): void
    {
        $service = app(WhatsAppFlowService::class);

        $this->assertEquals(50000, $service->calculateDeposit(100000));
        $this->assertEquals(77500, $service->calculateDeposit(155000)); // Rounded up
    }

    /**
     * Test event types returns correct options.
     */
    public function test_get_event_types_returns_all_options(): void
    {
        $service = app(WhatsAppFlowService::class);
        $types = $service->getEventTypes();

        $this->assertCount(6, $types);

        $ids = array_column($types, 'id');
        $this->assertContains('regular', $ids);
        $this->assertContains('birthday', $ids);
        $this->assertContains('meeting', $ids);
        $this->assertContains('anniversary', $ids);
        $this->assertContains('family', $ids);
        $this->assertContains('other', $ids);
    }

    /**
     * Test format currency returns correct Indonesian format.
     */
    public function test_format_currency_returns_indonesian_format(): void
    {
        $service = app(WhatsAppFlowService::class);

        $this->assertEquals('Rp100.000', $service->formatCurrency(100000));
        $this->assertEquals('Rp1.500.000', $service->formatCurrency(1500000));
        $this->assertEquals('Rp0', $service->formatCurrency(0));
    }
}
