<?php

namespace App\Services;

use App\Events\NewWhatsAppMessage;
use App\Models\WhatsAppContact;
use App\Models\WhatsAppMessage;
use Netflie\WhatsAppCloudApi\Request\MessageRequest;
use Netflie\WhatsAppCloudApi\Response;

class WhatsAppMessageHistory
{
    public static function recordText(WhatsAppContact $contact, Response $response, string $text): void
    {
        rescue(fn () => self::record($contact, $response, 'text', $text));
    }

    public static function recordInteractive(WhatsAppContact $contact, Response $response, array $productNames = []): void
    {
        rescue(function () use ($contact, $response, $productNames) {
            $request = $response->request();
            if (! $request instanceof MessageRequest) {
                return;
            }

            $interactive = $request->body()['interactive'] ?? null;
            if (! $interactive) {
                return;
            }

            foreach ($interactive['action']['sections'] ?? [] as $sectionIndex => $section) {
                foreach ($section['product_items'] ?? [] as $itemIndex => $item) {
                    $sku = $item['product_retailer_id'];
                    $interactive['action']['sections'][$sectionIndex]['product_items'][$itemIndex]['name'] = $productNames[$sku] ?? $sku;
                }
            }

            self::record($contact, $response, 'interactive', json_encode($interactive, JSON_THROW_ON_ERROR));
        });
    }

    private static function record(WhatsAppContact $contact, Response $response, string $type, string $content): void
    {
        $messageId = $response->decodedBody()['messages'][0]['id'] ?? null;
        if (! $messageId) {
            return;
        }

        $message = WhatsAppMessage::withoutGlobalScopes()->firstOrCreate(
            [
                'message_id' => $messageId,
                'user_id' => $contact->user_id,
                'phone_number_id' => $contact->phone_number_id,
                'contact_id' => $contact->id,
            ],
            [
                'direction' => 'outgoing',
                'type' => $type,
                'content' => $content,
                'status' => 'sent',
                'is_read' => true,
                'sent_at' => now(),
            ]
        );

        if (! $message->wasRecentlyCreated) {
            $message->update(['type' => $type, 'content' => $content]);
            if (! $message->wasChanged(['type', 'content'])) {
                return;
            }
        }

        $contact->update([
            'last_message_at' => $message->sent_at,
            'last_message_text' => $message->body,
        ]);

        defer(fn () => rescue(fn () => NewWhatsAppMessage::dispatch($message, $contact)));
    }
}
