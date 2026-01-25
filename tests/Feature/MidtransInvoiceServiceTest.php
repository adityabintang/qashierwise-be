<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\MidtransInvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MidtransInvoiceServiceTest extends TestCase
{
    use RefreshDatabase;

    private MidtransInvoiceService $invoiceService;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Configure Midtrans for testing
        config([
            'subscription.midtrans.server_key' => 'SB-Mid-server-test-key',
            'subscription.midtrans.is_production' => false,
            'subscription.invoice.enabled' => true,
            'subscription.invoice.due_days' => 7,
        ]);

        $this->invoiceService = new MidtransInvoiceService;
        $this->user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'testuser@example.com',
        ]);
    }

    /** @test */
    public function test_creates_invoice_successfully(): void
    {
        Http::fake([
            'api.sandbox.midtrans.com/v1/invoices' => Http::response([
                'id' => 'inv-123456789',
                'order_id' => 'INV-SUB-ORDER-123',
                'invoice_number' => 'INV-20260124-ABCD1234',
                'status' => 'pending',
                'gross_amount' => 350000,
                'pdf_url' => 'https://assets.midtrans.com/invoices/pdf/inv-123456789',
                'customer_details' => [
                    'id' => (string) $this->user->id,
                    'name' => $this->user->name,
                    'email' => $this->user->email,
                ],
            ], 201),
        ]);

        $result = $this->invoiceService->createSubscriptionInvoice(
            $this->user,
            'SUB-ORDER-123',
            'Pro',
            '1 Bulan',
            350000,
            'IDR'
        );

        $this->assertNotNull($result);
        $this->assertEquals('inv-123456789', $result['id']);
        $this->assertEquals('pending', $result['status']);
        $this->assertArrayHasKey('pdf_url', $result);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/v1/invoices') &&
                   $request->hasHeader('Authorization') &&
                   $request['customer_details']['email'] === $this->user->email;
        });
    }

    /** @test */
    public function test_returns_null_when_invoice_disabled(): void
    {
        config(['subscription.invoice.enabled' => false]);

        // Recreate service to pick up new config
        $invoiceService = new MidtransInvoiceService;

        $result = $invoiceService->createSubscriptionInvoice(
            $this->user,
            'SUB-ORDER-123',
            'Pro',
            '1 Bulan',
            350000
        );

        $this->assertNull($result);
        Http::assertNothingSent();
    }

    /** @test */
    public function test_returns_null_when_server_key_not_configured(): void
    {
        config(['subscription.midtrans.server_key' => '']);

        // Recreate service to pick up new config
        $invoiceService = new MidtransInvoiceService;

        $result = $invoiceService->createSubscriptionInvoice(
            $this->user,
            'SUB-ORDER-123',
            'Pro',
            '1 Bulan',
            350000
        );

        $this->assertNull($result);
    }

    /** @test */
    public function test_handles_api_failure_gracefully(): void
    {
        Http::fake([
            'api.sandbox.midtrans.com/v1/invoices' => Http::response([
                'error' => 'Invalid request',
            ], 400),
        ]);

        $result = $this->invoiceService->createSubscriptionInvoice(
            $this->user,
            'SUB-ORDER-123',
            'Pro',
            '1 Bulan',
            350000
        );

        $this->assertNull($result);
    }

    /** @test */
    public function test_get_invoice_successfully(): void
    {
        Http::fake([
            'api.sandbox.midtrans.com/v1/invoices/*' => Http::response([
                'id' => 'inv-123456789',
                'invoice_number' => 'INV-20260124-ABCD1234',
                'status' => 'paid',
                'gross_amount' => 350000,
                'pdf_url' => 'https://assets.midtrans.com/invoices/pdf/inv-123456789',
            ], 200),
        ]);

        $result = $this->invoiceService->getInvoice('inv-123456789');

        $this->assertNotNull($result);
        $this->assertEquals('inv-123456789', $result['id']);
        $this->assertEquals('paid', $result['status']);
    }

    /** @test */
    public function test_void_invoice_successfully(): void
    {
        Http::fake([
            'api.sandbox.midtrans.com/v1/invoices/*/void' => Http::response([
                'id' => 'inv-123456789',
                'status' => 'voided',
            ], 200),
        ]);

        $result = $this->invoiceService->voidInvoice('inv-123456789');

        $this->assertTrue($result);
    }

    /** @test */
    public function test_void_invoice_returns_false_on_failure(): void
    {
        Http::fake([
            'api.sandbox.midtrans.com/v1/invoices/*/void' => Http::response([
                'error' => 'Invoice cannot be voided',
            ], 400),
        ]);

        $result = $this->invoiceService->voidInvoice('inv-123456789');

        $this->assertFalse($result);
    }

    /** @test */
    public function test_is_configured_returns_correct_status(): void
    {
        $this->assertTrue($this->invoiceService->isConfigured());

        config(['subscription.midtrans.server_key' => '']);
        $invoiceService = new MidtransInvoiceService;

        $this->assertFalse($invoiceService->isConfigured());
    }

    /** @test */
    public function test_invoice_payload_contains_correct_customer_details(): void
    {
        Http::fake([
            'api.sandbox.midtrans.com/v1/invoices' => Http::response([
                'id' => 'inv-123456789',
                'status' => 'pending',
            ], 201),
        ]);

        $this->invoiceService->createSubscriptionInvoice(
            $this->user,
            'SUB-ORDER-123',
            'Pro',
            '3 Bulan',
            1050000,
            'IDR'
        );

        Http::assertSent(function ($request) {
            $body = $request->data();

            return $body['customer_details']['id'] === (string) $this->user->id &&
                   $body['customer_details']['name'] === $this->user->name &&
                   $body['customer_details']['email'] === $this->user->email &&
                   $body['payment_type'] === 'payment_link' &&
                   count($body['item_details']) === 1 &&
                   $body['item_details'][0]['price'] === 1050000;
        });
    }
}
