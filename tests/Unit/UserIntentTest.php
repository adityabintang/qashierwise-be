<?php

namespace Tests\Unit;

use App\Enums\UserIntent;
use Tests\TestCase;

class UserIntentTest extends TestCase
{
    public function test_detects_greeting()
    {
        $this->assertEquals(UserIntent::GREETING, UserIntent::detect('Halo'));
        $this->assertEquals(UserIntent::GREETING, UserIntent::detect('Hi'));
        $this->assertEquals(UserIntent::GREETING, UserIntent::detect('Assalamualaikum'));
    }

    public function test_detects_view_menu()
    {
        $this->assertEquals(UserIntent::VIEW_MENU, UserIntent::detect('menunya apa aja?'));
        $this->assertEquals(UserIntent::VIEW_MENU, UserIntent::detect('ada apa?'));
        $this->assertEquals(UserIntent::VIEW_MENU, UserIntent::detect('daftar produk'));
    }

    public function test_detects_order()
    {
        $this->assertEquals(UserIntent::ORDER, UserIntent::detect('pesan dimsum 2'));
        $this->assertEquals(UserIntent::ORDER, UserIntent::detect('beli teh jumbo'));
        $this->assertEquals(UserIntent::ORDER, UserIntent::detect('mau nasi goreng'));
    }

    public function test_detects_view_cart()
    {
        $this->assertEquals(UserIntent::VIEW_CART, UserIntent::detect('lihat keranjang'));
        $this->assertEquals(UserIntent::VIEW_CART, UserIntent::detect('pesanan saya'));
    }

    public function test_detects_checkout()
    {
        $this->assertEquals(UserIntent::CHECKOUT, UserIntent::detect('checkout'));
        $this->assertEquals(UserIntent::CHECKOUT, UserIntent::detect('bayar'));
        $this->assertEquals(UserIntent::CHECKOUT, UserIntent::detect('konfirmasi pesanan'));
    }

    public function test_detects_business_info()
    {
        $this->assertEquals(UserIntent::BUSINESS_INFO, UserIntent::detect('jam buka?'));
        $this->assertEquals(UserIntent::BUSINESS_INFO, UserIntent::detect('alamat dimana?'));
        $this->assertEquals(UserIntent::BUSINESS_INFO, UserIntent::detect('nomor telepon'));
    }

    public function test_detects_off_topic()
    {
        $this->assertEquals(UserIntent::OFF_TOPIC, UserIntent::detect('siapa presiden indonesia?'));
        $this->assertEquals(UserIntent::OFF_TOPIC, UserIntent::detect('ibu kota jepang'));
        $this->assertEquals(UserIntent::OFF_TOPIC, UserIntent::detect('apa itu chatgpt?'));
    }

    public function test_needs_business_info()
    {
        $this->assertTrue(UserIntent::GREETING->needsBusinessInfo());
        $this->assertTrue(UserIntent::BUSINESS_INFO->needsBusinessInfo());
        $this->assertFalse(UserIntent::ORDER->needsBusinessInfo());
    }

    public function test_needs_product_list()
    {
        $this->assertTrue(UserIntent::VIEW_MENU->needsProductList());
        $this->assertTrue(UserIntent::SEARCH_PRODUCT->needsProductList());
        $this->assertFalse(UserIntent::GREETING->needsProductList());
    }

    public function test_needs_order_workflow()
    {
        $this->assertTrue(UserIntent::ORDER->needsOrderWorkflow());
        $this->assertTrue(UserIntent::VIEW_CART->needsOrderWorkflow());
        $this->assertTrue(UserIntent::CHECKOUT->needsOrderWorkflow());
        $this->assertFalse(UserIntent::GREETING->needsOrderWorkflow());
    }
}
