<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AnalyticsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_super_admin_can_read_analytics(): void
    {
        $this->getJson('/api/admin/analytics')->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create(['email' => 'merchant@example.com']));
        $this->getJson('/api/admin/analytics')->assertForbidden();
    }

    public function test_report_counts_merchants_activity_and_payment_evidence(): void
    {
        $now = now()->startOfDay();
        $this->travelTo($now);
        $admin = User::factory()->create(['email' => User::SUPER_ADMIN_EMAIL, 'is_master_admin' => true]);
        $paid = User::factory()->create(['is_master_admin' => true]);
        $unverified = User::factory()->create(['is_master_admin' => true]);
        $unpaid = User::factory()->create(['is_master_admin' => true]);
        User::factory()->create(['is_master_admin' => false]);

        $subscriptionId = DB::table('subscriptions')->insertGetId([
            'user_id' => $paid->id, 'plan_name' => 'pro', 'status' => 'active',
            'current_period_start' => $now->copy()->subDays(10),
            'current_period_end' => $now->copy()->addDays(20),
            'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::table('subscriptions')->insert([
            'user_id' => $unverified->id, 'plan_name' => 'pro', 'status' => 'active',
            'current_period_start' => $now->copy()->subDays(10),
            'current_period_end' => $now->copy()->addDays(20),
            'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::table('subscription_payments')->insert([
            'subscription_id' => $subscriptionId, 'user_id' => $paid->id,
            'order_id' => 'test-paid-1', 'plan_name' => 'pro', 'gross_amount' => 100000,
            'currency' => 'IDR', 'status' => 'settlement',
            'transaction_time' => $now->copy()->subDays(5),
            'created_at' => $now, 'updated_at' => $now,
        ]);

        $storeId = DB::table('stores')->insertGetId([
            'user_id' => $unpaid->id, 'name' => 'Test Store', 'code' => 'ANALYTICS',
            'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::table('orders')->insert([
            'store_id' => $storeId, 'order_number' => 'analytics-order-1',
            'status' => 'pending', 'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::table('whatsapp_accounts')->insert([
            'user_id' => $paid->id, 'phone_number_id' => 'analytics-phone-1',
            'business_account_id' => 'analytics-business-1', 'access_token' => 'test',
            'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
        ]);

        Sanctum::actingAs($admin);
        $this->getJson('/api/admin/analytics')->assertOk()
            ->assertJsonPath('data.stats.registered', 3)
            ->assertJsonPath('data.stats.active', 1)
            ->assertJsonPath('data.stats.paid', 1)
            ->assertJsonPath('data.stats.unverified', 1)
            ->assertJsonPath('data.stats.unpaid', 1)
            ->assertJsonPath('data.stats.whatsapp_active', 1)
            ->assertJsonCount(12, 'data.months');
    }
}
