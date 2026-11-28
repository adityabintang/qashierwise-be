<?php

namespace App\Services;

use App\Events\MessageStatusUpdated;
use App\Events\NewWhatsAppMessage;
use App\Exceptions\WhatsAppBroadcastException;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppBroadcast;
use App\Models\WhatsAppBroadcastRecipient;
use App\Models\WhatsAppContact;
use Carbon\CarbonInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WhatsAppBroadcastSender
{
    private const RESTRICTIONS = [4, 17, 32, 368, 80007, 80008, 130429, 131031, 131048, 131064];

    public function __construct(
        private WhatsAppAccountService $accounts,
        private WhatsAppBroadcastService $broadcasts,
        private WhatsAppBroadcastMeta $meta,
        private WhatsAppBroadcastQuota $quota,
    ) {}

    public function process(WhatsAppBroadcast $broadcast): bool
    {
        $account = $this->accounts->getActiveAccount($broadcast->user_id);
        if (! $account || $account->id !== $broadcast->whatsapp_account_id || $account->phone_number_id !== $broadcast->phone_number_id) {
            $this->stop($broadcast, 'account_changed');

            return false;
        }

        try {
            $snapshot = $this->meta->quota($account);
            $template = $this->meta->template($account, $broadcast->template_id);
        } catch (WhatsAppBroadcastException $e) {
            if ($e->errorCode === 'BROADCAST_ACCOUNT_RESTRICTED') {
                $this->stop($broadcast, 'account_restricted');
            }

            return false;
        }

        if ($snapshot['portfolio'] !== $broadcast->portfolio_id) {
            $this->stop($broadcast, 'portfolio_changed');

            return false;
        }
        if (! $template || $broadcast->template_hash !== $this->broadcasts->hash($template)) {
            $this->stop($broadcast, 'template_changed');

            return false;
        }

        for ($i = 0; $i < 5; $i++) {
            $recipient = $this->claim($broadcast, $snapshot['limit']);
            if (! $recipient) {
                return false;
            }
            if ($recipient->status === 'sending' && ! $this->send($account, $broadcast, $recipient)) {
                return false;
            }
        }

        return true;
    }

    private function claim(WhatsAppBroadcast $broadcast, ?int $limit): ?WhatsAppBroadcastRecipient
    {
        return $this->quota->lock($broadcast->portfolio_id, function () use ($broadcast, $limit) {
            $recipient = $broadcast->recipients()->where('status', 'queued')->orderBy('id')->first();
            if (! $recipient || $broadcast->recipients()->where('status', 'sending')->exists()) {
                return null;
            }

            $account = $this->accounts->getActiveAccount($broadcast->user_id);
            if (! $account || $account->id !== $broadcast->whatsapp_account_id || $account->phone_number_id !== $broadcast->phone_number_id) {
                $this->stopQueued($broadcast, 'account_changed');

                return null;
            }

            $contact = $this->contact($broadcast, $recipient);
            if (! $contact || $this->broadcasts->optedOut($broadcast->user_id, $broadcast->phone_number_id, $recipient->wa_id)) {
                $recipient->update(['status' => 'skipped', 'reason' => $contact ? 'opted_out' : 'contact_changed']);

                return $recipient;
            }

            if ($limit !== null && $this->quota->usage($broadcast->portfolio_id) > $limit) {
                $this->stopQueued($broadcast, 'quota_reduced');

                return null;
            }

            $recipient->update(['status' => 'sending', 'attempted_at' => now()]);

            return $recipient;
        });
    }

    private function send(WhatsAppAccount $account, WhatsAppBroadcast $broadcast, WhatsAppBroadcastRecipient $recipient): bool
    {
        try {
            $response = $this->meta->client($account)->post($account->phone_number_id.'/messages', [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => $recipient->wa_id,
                'type' => 'template',
                'template' => $broadcast->template_payload,
                'biz_opaque_callback_data' => $recipient->callback_id,
            ]);
        } catch (ConnectionException) {
            $this->review($recipient);

            return true;
        }

        $messageId = $response->json('messages.0.id');
        if ($response->successful() && is_string($messageId) && $messageId !== '') {
            DB::transaction(function () use ($broadcast, $recipient, $messageId) {
                $current = WhatsAppBroadcastRecipient::lockForUpdate()->find($recipient->id);
                if ($current && in_array($current->status, ['sending', 'review'], true)) {
                    $this->record($broadcast, $current, $messageId, 'accepted');
                }
            }, 3);

            return true;
        }

        $code = (int) $response->json('error.code');
        $restricted = $response->status() === 429 || in_array($code, self::RESTRICTIONS, true);
        if (($response->clientError() || $restricted) && ($code || $restricted)) {
            $reason = $code ? 'meta_'.$code : 'meta_rate_limited';
            $this->quota->lock($broadcast->portfolio_id, function () use ($broadcast, $recipient, $reason, $restricted) {
                WhatsAppBroadcastRecipient::whereKey($recipient->id)->where('status', 'sending')
                    ->update(['status' => 'failed', 'reason' => $reason]);
                if ($restricted) {
                    $this->stopQueued($broadcast, 'account_restricted');
                }
            });

            return ! $restricted;
        }

        $this->review($recipient);

        return true;
    }

    private function review(WhatsAppBroadcastRecipient $recipient): void
    {
        WhatsAppBroadcastRecipient::whereKey($recipient->id)->where('status', 'sending')
            ->update(['status' => 'review', 'reason' => 'send_result_unknown']);
    }

    public function receipt(WhatsAppAccount $account, array $receipt): bool
    {
        $messageId = $receipt['id'] ?? null;
        $status = $receipt['status'] ?? null;
        if (! is_string($messageId) || ! in_array($status, ['sent', 'delivered', 'read', 'failed'], true)) {
            return false;
        }

        $callback = $receipt['biz_opaque_callback_data'] ?? null;
        $recipient = WhatsAppBroadcastRecipient::whereHas('broadcast', fn ($broadcast) => $broadcast->where('whatsapp_account_id', $account->id)
            ->where('user_id', $account->user_id)->where('phone_number_id', $account->phone_number_id))
            ->where(function ($query) use ($messageId, $callback) {
                $query->where('message_id', $messageId);
                if (is_string($callback) && Str::isUuid($callback)) {
                    $query->orWhere('callback_id', $callback);
                }
            })->first();

        if (! $recipient) {
            return false;
        }

        $broadcast = $recipient->broadcast;
        $this->quota->lock($broadcast->portfolio_id, function () use ($broadcast, $recipient, $receipt, $messageId, $status) {
            $current = WhatsAppBroadcastRecipient::lockForUpdate()->find($recipient->id);
            $previous = match ($status) {
                'sent' => ['sending', 'review', 'accepted'],
                'failed' => ['sending', 'review', 'accepted'],
                'delivered' => ['sending', 'review', 'accepted', 'failed'],
                'read' => ['sending', 'review', 'accepted', 'failed', 'delivered'],
            };
            if (! $current || ! in_array($current->status, $previous, true)
                || ($current->message_id !== null && $current->message_id !== $messageId)
                || (($receipt['recipient_id'] ?? $current->wa_id) !== $current->wa_id)) {
                return;
            }

            $code = (int) data_get($receipt, 'errors.0.code');
            $timestamp = isset($receipt['timestamp']) ? now()->timestamp((int) $receipt['timestamp']) : now();
            $this->record($broadcast, $current, $messageId, $status === 'sent' ? 'accepted' : $status, $timestamp, $code);
            if ($status === 'failed' && in_array($code, self::RESTRICTIONS, true)) {
                $this->stopQueued($broadcast, 'account_restricted');
            }
        });

        return true;
    }

    private function record(WhatsAppBroadcast $broadcast, WhatsAppBroadcastRecipient $recipient, string $id, string $status, ?CarbonInterface $timestamp = null, int $error = 0): void
    {
        $sentAt = $recipient->accepted_at ?? $recipient->attempted_at ?? now();
        $recipient->update([
            'message_id' => $id,
            'status' => $status,
            'accepted_at' => $sentAt,
            'reason' => $status === 'failed' ? ($error ? 'meta_'.$error : 'meta_delivery_failed') : null,
        ]);

        $contact = $this->contact($broadcast, $recipient);
        if (! $contact) {
            return;
        }

        $message = WhatsAppMessageHistory::persistLive($id, [
            'user_id' => $broadcast->user_id,
            'phone_number_id' => $broadcast->phone_number_id,
            'contact_id' => $contact->id,
            'direction' => 'outgoing',
            'type' => 'template',
            'content' => json_encode(['template_name' => $broadcast->template_name, 'language' => $broadcast->language, 'preview' => $broadcast->preview], JSON_THROW_ON_ERROR),
            'metadata' => ['broadcast_id' => $broadcast->id],
            'status' => 'sent',
            'is_read' => true,
            'sent_at' => $sentAt,
        ], true);

        if (! $message || $message->is_revoked) {
            return;
        }

        $new = $message->wasRecentlyCreated || $message->wasChanged('is_history_import');
        $updates = ['status' => $status === 'accepted' ? 'sent' : $status];
        if (in_array($status, ['delivered', 'read'], true)) {
            $updates[$status.'_at'] = $timestamp;
        }
        $message->update($updates);

        if ($new) {
            WhatsAppMessageHistory::updateContactPreview($contact, $message);
            DB::afterCommit(fn () => rescue(fn () => NewWhatsAppMessage::dispatch($message, $contact)));
        } elseif ($message->wasChanged('status')) {
            DB::afterCommit(fn () => rescue(fn () => MessageStatusUpdated::dispatch($message)));
        }
    }

    private function contact(WhatsAppBroadcast $broadcast, WhatsAppBroadcastRecipient $recipient): ?WhatsAppContact
    {
        return $this->broadcasts->contacts($broadcast->user_id, $broadcast->phone_number_id)
            ->whereIn('wa_id', [$recipient->wa_id, '+'.$recipient->wa_id])
            ->whereKey($recipient->contact_id)->first();
    }

    private function stop(WhatsAppBroadcast $broadcast, string $reason): void
    {
        $this->quota->lock($broadcast->portfolio_id, fn () => $this->stopQueued($broadcast, $reason));
    }

    private function stopQueued(WhatsAppBroadcast $broadcast, string $reason): void
    {
        if ($broadcast->recipients()->where('status', 'queued')->update(['status' => 'skipped', 'reason' => $reason])) {
            $broadcast->update(['stop_reason' => $reason]);
        }
    }
}
