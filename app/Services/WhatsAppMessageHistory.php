<?php

namespace App\Services;

use App\Events\NewWhatsAppMessage;
use App\Models\WhatsAppContact;
use App\Models\WhatsAppMessage;
use Illuminate\Support\Facades\DB;
use Netflie\WhatsAppCloudApi\Request\MessageRequest;
use Netflie\WhatsAppCloudApi\Response;

class WhatsAppMessageHistory
{
    public static function updateContactPreview(WhatsAppContact $contact, WhatsAppMessage $message, ?string $preview = null): void
    {
        $sentAt = $message->sent_at ?? $message->created_at;

        WhatsAppContact::withoutGlobalScopes()->whereKey($contact->id)
            ->where(fn ($query) => $query->whereNull('last_message_at')->orWhere('last_message_at', '<=', $sentAt))
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('whatsapp_messages')
                ->where('contact_id', $contact->id)->where('sent_at', $sentAt)->where('id', '>', $message->id))
            ->update(['last_message_at' => $sentAt, 'last_message_text' => $preview ?? $message->body]);
        $contact->refresh();
    }

    public static function persistLive(string $messageId, array $attributes, bool $overwriteExisting = false): ?WhatsAppMessage
    {
        return DB::transaction(function () use ($messageId, $attributes, $overwriteExisting) {
            $messages = WhatsAppMessage::withoutGlobalScopes();
            $message = $messages->firstOrCreate(['message_id' => $messageId], $attributes);
            if ($message->wasRecentlyCreated) {
                return $message;
            }

            $message = $messages->lockForUpdate()->findOrFail($message->id);
            if ((int) $message->user_id !== (int) $attributes['user_id']
                || $message->phone_number_id !== $attributes['phone_number_id']) {
                return null;
            }

            if ($message->is_history_import) {
                $message->update($attributes + [
                    'is_history_import' => false,
                    'metadata' => null,
                    'read_at' => null,
                ]);
            } elseif ($overwriteExisting) {
                unset($attributes['status'], $attributes['is_read'], $attributes['sent_at']);
                $message->update($attributes);
            }

            return $message;
        }, 3);
    }

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

        $message = self::persistLive($messageId, [
            'user_id' => $contact->user_id,
            'phone_number_id' => $contact->phone_number_id,
            'contact_id' => $contact->id,
            'direction' => 'outgoing',
            'type' => $type,
            'content' => $content,
            'status' => 'sent',
            'is_read' => true,
            'sent_at' => now(),
        ], true);

        if (! $message || (! $message->wasRecentlyCreated && ! $message->wasChanged())) {
            return;
        }

        self::updateContactPreview($contact, $message);

        defer(fn () => rescue(fn () => NewWhatsAppMessage::dispatch($message, $contact)));
    }
}
