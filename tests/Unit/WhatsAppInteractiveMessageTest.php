<?php

namespace Tests\Unit;

use App\Events\NewWhatsAppMessage;
use App\Models\WhatsAppMessage;
use Tests\TestCase;

class WhatsAppInteractiveMessageTest extends TestCase
{
    public function test_cloud_api_catalog_options_are_in_history_and_broadcast_payloads(): void
    {
        $message = new WhatsAppMessage([
            'type' => 'interactive',
            'direction' => 'outgoing',
            'content' => json_encode([
                'header' => ['text' => 'Menu'],
                'body' => ['text' => 'Pilih makanan'],
                'action' => ['sections' => [[
                    'title' => 'Makanan',
                    'product_items' => [['product_retailer_id' => 'SKU-1', 'name' => 'Nasi Goreng']],
                ]]],
            ]),
        ]);
        $message->created_at = '2026-06-10 12:00:00';

        $details = $message->toArray()['interactive_details'];

        $this->assertSame('Menu', $details['header']);
        $this->assertSame('Pilih makanan', $message->body);
        $this->assertSame('Nasi Goreng', $details['sections'][0]['rows'][0]['name']);
        $this->assertSame($details, NewWhatsAppMessage::messagePayload($message)['interactive_details']);
    }

    public function test_saved_list_options_and_customer_replies_are_readable(): void
    {
        $menu = new WhatsAppMessage([
            'type' => 'interactive',
            'direction' => 'outgoing',
            'content' => json_encode([
                'body' => 'Pilih kategori',
                'button_text' => 'Lihat kategori',
                'sections' => [['rows' => [['id' => 'food', 'title' => 'Makanan', 'description' => 'Menu utama']]]],
            ]),
        ]);
        $reply = new WhatsAppMessage([
            'type' => 'interactive',
            'direction' => 'incoming',
            'content' => 'Makanan',
            'metadata' => ['id' => 'food', 'title' => 'Makanan', 'description' => 'Menu utama'],
        ]);
        $button = new WhatsAppMessage([
            'type' => 'button',
            'direction' => 'incoming',
            'content' => 'Konfirmasi',
        ]);

        $this->assertSame('Lihat kategori', $menu->interactive_details['button_text']);
        $this->assertSame('Makanan', $menu->interactive_details['sections'][0]['rows'][0]['title']);
        $this->assertSame(['selection' => 'Makanan', 'description' => 'Menu utama'], $reply->interactive_details);
        $this->assertSame('Konfirmasi', $button->interactive_details['selection']);
    }

    public function test_details_can_be_recovered_from_unsupported_message_metadata(): void
    {
        $list = new WhatsAppMessage([
            'type' => 'unsupported',
            'direction' => 'outgoing',
            'metadata' => ['interactive' => [
                'body' => ['text' => 'Pilih metode'],
                'action' => ['buttons' => [['reply' => ['id' => 'pickup', 'title' => 'Ambil sendiri']]]],
            ]],
        ]);
        $cart = new WhatsAppMessage([
            'type' => 'unsupported',
            'direction' => 'incoming',
            'metadata' => ['original_type' => 'order', 'product_items' => [
                ['product_retailer_id' => 'SKU-1', 'quantity' => 2],
            ]],
        ]);

        $this->assertSame('Ambil sendiri', $list->interactive_details['buttons'][0]['reply']['title']);
        $this->assertSame('Pesanan katalog', $cart->interactive_details['selection']);
        $this->assertSame(2, $cart->interactive_details['sections'][0]['rows'][0]['quantity']);
        $this->assertNull((new WhatsAppMessage(['type' => 'unsupported']))->interactive_details);
    }
}
