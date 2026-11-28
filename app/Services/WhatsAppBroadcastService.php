<?php

namespace App\Services;

use App\Exceptions\WhatsAppBroadcastException;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppBroadcast;
use App\Models\WhatsAppContact;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class WhatsAppBroadcastService
{
    public function __construct(private WhatsAppBroadcastMeta $meta, private WhatsAppBroadcastQuota $quota) {}

    public function options(WhatsAppAccount $account): array
    {
        $snapshot = $this->meta->quota($account);
        $this->quota->register($account, $snapshot['portfolio']);
        $limit = $snapshot['limit'];

        return [
            'account_id' => $account->id,
            'templates' => $this->meta->templates($account),
            'quota' => [
                'tier' => $snapshot['tier'],
                'remaining' => $limit === null ? null : max(0, $limit - $this->quota->usage($snapshot['portfolio'])),
            ],
        ];
    }

    public function create(WhatsAppAccount $account, array $input): WhatsAppBroadcast
    {
        $ids = array_map('intval', $input['contact_ids']);
        sort($ids);
        $variables = $input['variables'] ?? [];
        ksort($variables);
        foreach ($variables as &$section) {
            ksort($section);
        }
        unset($section);
        $hash = $this->hash([$ids, $input['template_id'], $variables]);
        $key = strtolower($input['idempotency_key']);

        $existing = $this->findRequest($account, $key, $hash);
        if ($existing) {
            return $existing;
        }

        $snapshot = $this->meta->quota($account);
        $template = collect($this->meta->templates($account))->firstWhere('id', '===', $input['template_id']);
        if (! $template) {
            throw ValidationException::withMessages(['template_id' => 'Template tidak tersedia untuk akun WhatsApp aktif.']);
        }
        $payload = $this->meta->payload($template, $variables);
        $portfolio = $snapshot['portfolio'];
        $this->quota->register($account, $portfolio);

        return $this->quota->lock($portfolio, function () use ($account, $key, $ids, $hash, $snapshot, $template, $payload, $portfolio) {
            $current = WhatsAppAccount::withoutGlobalScopes()->whereKey($account->id)
                ->where('user_id', $account->user_id)->where('is_active', true)->lockForUpdate()->first();
            if (! $current || $current->phone_number_id !== $account->phone_number_id
                || $this->meta->wabaId($current) !== $this->meta->wabaId($account)) {
                throw new WhatsAppBroadcastException('BROADCAST_ACCOUNT_CHANGED', 'Akun WhatsApp aktif telah berubah.');
            }

            $existing = $this->findRequest($account, $key, $hash);
            if ($existing) {
                return $existing;
            }

            $contacts = collect();
            foreach (array_chunk($ids, 500) as $chunk) {
                $contacts = $contacts->concat($this->contacts($account->user_id, $account->phone_number_id)
                    ->whereKey($chunk)->orderBy('id')->get());
            }
            if ($contacts->count() !== count($ids)
                || $contacts->contains(fn ($contact) => ! preg_match('/^\+?[1-9][0-9]{1,14}$/D', $contact->wa_id))) {
                throw ValidationException::withMessages(['contact_ids' => 'Semua kontak harus memiliki nomor WhatsApp yang valid dan terdaftar di akun aktif.']);
            }

            $optOuts = $this->optOuts($account->user_id, $account->phone_number_id, $contacts->pluck('wa_id')->all());
            $recipients = $contacts->unique(fn ($contact) => ltrim($contact->wa_id, '+'))->map(function ($contact) use ($optOuts) {
                $waId = ltrim($contact->wa_id, '+');
                $optedOut = isset($optOuts[$waId]);

                return [
                    'contact_id' => $contact->id,
                    'wa_id' => $waId,
                    'status' => $optedOut ? 'skipped' : 'queued',
                    'reason' => $optedOut ? 'opted_out' : null,
                    'callback_id' => (string) Str::uuid(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            });
            $count = $recipients->where('status', 'queued')->count();
            $remaining = $snapshot['limit'] === null ? null : max(0, $snapshot['limit'] - $this->quota->usage($portfolio));
            if ($remaining !== null && $count > $remaining) {
                throw new WhatsAppBroadcastException(
                    'BROADCAST_LIMIT_EXCEEDED',
                    "Broadcast melebihi limit WhatsApp. Kamu memilih {$count} penerima, sementara estimasi sisa kuota adalah {$remaining}. Kurangi jumlah penerima untuk melanjutkan.",
                    ['recipient_count' => $count, 'remaining_quota' => $remaining],
                );
            }

            $broadcast = WhatsAppBroadcast::create($payload + [
                'whatsapp_account_id' => $account->id,
                'user_id' => $account->user_id,
                'phone_number_id' => $account->phone_number_id,
                'portfolio_id' => $portfolio,
                'idempotency_key' => $key,
                'request_hash' => $hash,
                'template_id' => $template['id'],
                'template_name' => $template['name'],
                'language' => $template['language'],
                'template_hash' => $this->hash($template),
            ]);

            foreach ($recipients->chunk(100) as $chunk) {
                $broadcast->recipients()->insert($chunk->map(fn ($row) => $row + ['whatsapp_broadcast_id' => $broadcast->id])->values()->all());
            }

            return $broadcast;
        });
    }

    public function contacts(int $userId, string $phoneNumberId): Builder
    {
        return WhatsAppContact::withoutGlobalScopes()->where('user_id', $userId)->where('phone_number_id', $phoneNumberId);
    }

    public function optedOut(int $userId, string $phoneNumberId, string $waId): bool
    {
        return $this->contacts($userId, $phoneNumberId)->whereIn('wa_id', [$waId, '+'.$waId])
            ->whereNotNull('drips_unsubscribed_at')->exists();
    }

    public function optOuts(int $userId, string $phoneNumberId, array $numbers): array
    {
        $ids = array_unique(array_map(fn ($number) => ltrim($number, '+'), $numbers));
        $optOuts = [];
        foreach (array_chunk($ids, 250) as $chunk) {
            $variants = array_merge($chunk, array_map(fn ($id) => '+'.$id, $chunk));
            $matches = $this->contacts($userId, $phoneNumberId)->whereIn('wa_id', $variants)
                ->whereNotNull('drips_unsubscribed_at')->pluck('wa_id');
            foreach ($matches as $match) {
                $optOuts[ltrim($match, '+')] = true;
            }
        }

        return $optOuts;
    }

    public function hash(array $value): string
    {
        return hash('sha256', json_encode($value, JSON_THROW_ON_ERROR));
    }

    private function findRequest(WhatsAppAccount $account, string $key, string $hash): ?WhatsAppBroadcast
    {
        $existing = WhatsAppBroadcast::where('whatsapp_account_id', $account->id)->where('idempotency_key', $key)->first();
        if ($existing && $existing->request_hash !== $hash) {
            throw new WhatsAppBroadcastException('BROADCAST_IDEMPOTENCY_CONFLICT', 'Kunci pengiriman sudah dipakai untuk broadcast lain.');
        }

        return $existing;
    }
}
