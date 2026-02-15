<?php

namespace Tests\Feature;

use App\Models\ReservationConfig;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReservationFormPaymentTypesTest extends TestCase
{
    use RefreshDatabase;

    public function test_form_hides_full_payment_when_disabled(): void
    {
        $merchant = User::factory()->create([
            'slug' => 'merchant-slug',
        ]);
        $store = Store::factory()->create([
            'user_id' => $merchant->id,
        ]);
        ReservationConfig::create([
            'user_id' => $merchant->id,
            'store_id' => $store->id,
            'is_active' => true,
            'available_slots' => [now()->addDays(2)->toDateString()],
            'guest_options' => [2, 4, 6, 8],
            'dp_percentage' => 50.0,
            'allow_full_payment' => false,
            'allow_dp_payment' => true,
            'reservation_fee' => 400000,
        ]);

        $response = $this->get('/reservations/form?merchantName='.$merchant->slug);

        $response->assertOk();
        $response->assertSee('DP (50%)');
        $response->assertDontSee('Bayar Lunas');
    }
}
