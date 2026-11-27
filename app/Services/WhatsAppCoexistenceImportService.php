<?php

namespace App\Services;

use App\Jobs\ProcessWhatsAppCoexistenceBatch;
use App\Jobs\StoreWhatsAppHistoryAttachment;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppCoexistenceImport;
use App\Models\WhatsAppContact;
use App\Models\WhatsAppMessage;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppCoexistenceImportService
{
    public const SIGNUP_EVENT = 'FINISH_WHATSAPP_BUSINESS_APP_ONBOARDING';

    public const MEDIA_TYPES = ['image', 'document', 'audio', 'video', 'sticker'];

    public function requestSync(int $importId): void
    {
        foreach (['contacts' => 'smb_app_state_sync', 'history' => 'history'] as $stream => $type) {
            $import = WhatsAppCoexistenceImport::find($importId);
            if (! $import?->subscribed_at || $import->onboarded_at->addDay()->lte(now())) {
                return;
            }

            $account = $this->accountFor($import);
            if (! $account) {
                return;
            }

            $claimed = WhatsAppCoexistenceImport::whereKey($importId)
                ->whereNull($stream.'_requested_at')
                ->update([$stream.'_requested_at' => now()]);

            if (! $claimed) {
                continue;
            }

            try {
                $version = config('whatsapp.api_version');
                $response = Http::withToken($account->access_token)->connectTimeout(5)->timeout(15)
                    ->post("https://graph.facebook.com/{$version}/{$import->phone_number_id}/smb_app_data", [
                        'messaging_product' => 'whatsapp',
                        'sync_type' => $type,
                    ]);
            } catch (ConnectionException) {
                Log::warning('WhatsApp import request outcome unknown', ['import_id' => $importId, 'stream' => $stream]);

                continue;
            }

            if ($response->successful() && is_string($response->json('request_id'))) {
                $import->update([$stream.'_request_id' => $response->json('request_id')]);
            } elseif ($stream === 'history' && (int) $response->json('error.code') === 2593109) {
                WhatsAppCoexistenceImport::whereKey($importId)->where('history_status', 'waiting')
                    ->update(['history_status' => 'not_shared']);
            } else {
                Log::warning('WhatsApp import request not confirmed', [
                    'import_id' => $importId, 'stream' => $stream, 'http_status' => $response->status(),
                ]);
            }
        }
    }

    public function acceptBatch(WhatsAppAccount $account, string $wabaId, string $field, array $value): void
    {
        $import = WhatsAppCoexistenceImport::whereKey($account->coexistence_import_id)
            ->where('whatsapp_account_id', $account->id)
            ->where('waba_id', $wabaId)
            ->where('phone_number_id', $value['metadata']['phone_number_id'] ?? null)
            ->first();
        $requestedAt = $field === 'history' ? 'history_requested_at' : 'contacts_requested_at';

        if (! $import?->{$requestedAt}) {
            return;
        }

        $json = json_encode($value, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
        $key = ['import_id' => $import->id, 'digest' => hash('sha256', $field."\n".$json)];
        DB::table('whatsapp_coexistence_batches')->insertOrIgnore($key + [
            'field' => $field,
            'payload' => Crypt::encryptString($json),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $batch = DB::table('whatsapp_coexistence_batches')->where($key)->first();
        if (! $batch->processed_at || $batch->attachment_jobs) {
            ProcessWhatsAppCoexistenceBatch::dispatch($batch->id);
        }
    }

    public function processBatch(int $batchId): void
    {
        $attachmentJobs = DB::transaction(function () use ($batchId): array {
            $batch = DB::table('whatsapp_coexistence_batches')->where('id', $batchId)->lockForUpdate()->first();
            if (! $batch || $batch->processed_at) {
                return json_decode($batch->attachment_jobs ?? '[]', true, flags: JSON_THROW_ON_ERROR);
            }

            $import = WhatsAppCoexistenceImport::find($batch->import_id);
            $account = $import ? $this->accountFor($import, lock: true) : null;
            $attachmentJobs = [];
            if ($account) {
                $import->refresh();
                $value = json_decode(Crypt::decryptString($batch->payload), true, flags: JSON_THROW_ON_ERROR);
                if ($batch->field === 'smb_app_state_sync') {
                    if (isset($value['state_sync'])) {
                        $this->importContacts($import, $account, $value['state_sync']);
                    }
                } else {
                    $attachmentJobs = $this->importHistory($import, $account, $value);
                }
            }

            DB::table('whatsapp_coexistence_batches')->where('id', $batchId)->update([
                'processed_at' => now(),
                'payload' => null,
                'attachment_jobs' => $attachmentJobs ? json_encode($attachmentJobs, JSON_THROW_ON_ERROR) : null,
                'updated_at' => now(),
            ]);

            return $attachmentJobs;
        }, 3);

        foreach ($attachmentJobs as $attachmentId) {
            StoreWhatsAppHistoryAttachment::dispatch($attachmentId);
        }
        if ($attachmentJobs) {
            DB::table('whatsapp_coexistence_batches')->where('id', $batchId)->update(['attachment_jobs' => null]);
        }
    }

    public function accountFor(WhatsAppCoexistenceImport $import, bool $lock = false): ?WhatsAppAccount
    {
        return WhatsAppAccount::withoutGlobalScopes()->whereKey($import->whatsapp_account_id)
            ->where('is_active', true)
            ->where('coexistence_import_id', $import->id)
            ->where('waba_id', $import->waba_id)
            ->where('phone_number_id', $import->phone_number_id)
            ->when($lock, fn ($query) => $query->lockForUpdate())
            ->first();
    }

    private function importContacts(WhatsAppCoexistenceImport $import, WhatsAppAccount $account, array $entries): void
    {
        $initial = $entries === [];
        foreach ($entries as $entry) {
            $timestamp = filter_var(data_get($entry, 'metadata.timestamp'), FILTER_VALIDATE_INT);
            if (($entry['type'] ?? null) !== 'contact' || ! $timestamp || $timestamp > $import->onboarded_at->timestamp) {
                continue;
            }

            $initial = true;
            $data = $entry['contact'] ?? [];
            $phone = $this->phone($data['phone_number'] ?? null);
            if (! $phone) {
                if (! empty($data['user_id'])) {
                    $added = DB::table('whatsapp_coexistence_skipped_contacts')->insertOrIgnore([
                        'import_id' => $import->id,
                        'identity_hash' => hash('sha256', $data['user_id']),
                    ]);
                    if ($added) {
                        $import->increment('contacts_without_phone');
                    }
                }

                continue;
            }

            $hasName = ($entry['action'] ?? null) === 'add';
            $contact = $this->contact($account, $phone, $hasName);
            if (! $contact || $timestamp <= $contact->coexistence_contact_timestamp) {
                continue;
            }

            $name = trim($data['full_name'] ?? '') ?: trim($data['first_name'] ?? '');
            $canRename = ! $contact->name || $this->phone($contact->name) === $phone
                || ((int) $contact->coexistence_contact_import_id === $import->id
                    && $contact->name === $contact->coexistence_contact_name);

            $contact->coexistence_contact_timestamp = $timestamp;
            if ($hasName && $name !== '' && $canRename) {
                $contact->name = $contact->coexistence_contact_name = mb_substr($name, 0, 255);
                $contact->coexistence_contact_import_id = $import->id;
            }
            $contact->save();
        }

        if ($initial) {
            $import->update(['contacts_status' => 'imported']);
        }
    }

    private function importHistory(WhatsAppCoexistenceImport $import, WhatsAppAccount $account, array $value): array
    {
        $mediaMessageIds = [];
        foreach ($value['history'] ?? [] as $chunk) {
            foreach ($chunk['errors'] ?? [] as $error) {
                if ((int) ($error['code'] ?? 0) === 2593109 && $import->history_status === 'waiting') {
                    $import->update(['history_status' => 'not_shared']);
                }
            }

            $progress = data_get($chunk, 'metadata.progress');
            if ((isset($chunk['threads']) || is_numeric($progress)) && $import->history_status !== 'not_shared') {
                if (is_numeric($progress)) {
                    $import->history_progress = max($import->history_progress ?? 0, min(100, max(0, (int) $progress)));
                }
                $import->history_status = $import->history_progress === 100 ? 'imported' : 'importing';
                foreach ($chunk['threads'] ?? [] as $thread) {
                    $phone = $this->phone($thread['id'] ?? data_get($thread, 'context.wa_id'));
                    if (! $phone || empty($thread['messages'])) {
                        continue;
                    }

                    $contact = $this->contact($account, $phone);
                    $newest = null;
                    foreach ($thread['messages'] as $data) {
                        $message = $this->importMessage($import, $contact, $data, $value['metadata']['display_phone_number'] ?? $account->display_phone_number);
                        if ($message && (! $newest || [$message->sent_at, $message->id] > [$newest->sent_at, $newest->id])) {
                            $newest = $message;
                        }
                        if (! empty($data['id']) && in_array($data['type'] ?? '', [...self::MEDIA_TYPES, 'media_placeholder'], true)) {
                            $mediaMessageIds[] = $data['id'];
                        }
                    }
                    if ($newest) {
                        WhatsAppMessageHistory::updateContactPreview($contact, $newest);
                    }
                }
                $import->save();
            }
        }

        if ($import->history_status !== 'not_shared') {
            foreach ($value['messages'] ?? [] as $message) {
                $this->rememberAttachment($import, $message);
                if (! empty($message['id'])) {
                    $mediaMessageIds[] = $message['id'];
                }
            }
        }

        $attachments = [];
        foreach (array_chunk(array_unique($mediaMessageIds), 500) as $ids) {
            $attachments = array_merge($attachments, DB::table('whatsapp_coexistence_attachments')
                ->where('import_id', $import->id)
                ->whereIn('meta_message_id', $ids)
                ->where('processed', false)
                ->pluck('id')->all());
        }

        return $attachments;
    }

    private function importMessage(WhatsAppCoexistenceImport $import, WhatsAppContact $contact, array $data, ?string $displayPhone): ?WhatsAppMessage
    {
        $id = $data['id'] ?? null;
        $timestamp = filter_var($data['timestamp'] ?? null, FILTER_VALIDATE_INT);
        if (! is_string($id) || $id === '' || ! $timestamp
            || $timestamp < $import->onboarded_at->subDays(180)->timestamp
            || $timestamp > $import->onboarded_at->timestamp) {
            return null;
        }

        $businessPhone = $this->phone($displayPhone);
        $from = $this->phone($data['from'] ?? null);
        $direction = ($businessPhone && $businessPhone === $from) || $this->phone($data['to'] ?? null) === $this->phone($contact->wa_id)
            ? 'outgoing' : 'incoming';
        [$type, $content, $details] = $this->messageContent($data);
        $message = WhatsAppMessage::withoutGlobalScopes()->firstOrCreate(['message_id' => $id], [
            'user_id' => $contact->user_id,
            'phone_number_id' => $contact->phone_number_id,
            'contact_id' => $contact->id,
            'direction' => $direction,
            'type' => $type,
            'content' => $content,
            'metadata' => $details,
            'status' => 'read',
            'is_read' => true,
            'is_history_import' => true,
            'sent_at' => Carbon::createFromTimestamp($timestamp, config('app.timezone')),
        ]);
        $this->rememberAttachment($import, $data);

        return $message->wasRecentlyCreated ? $message : null;
    }

    private function messageContent(array $data): array
    {
        $type = $data['type'] ?? 'unknown';
        $body = $data[$type] ?? [];
        if ($type === 'text') {
            return [$type, $body['body'] ?? '', []];
        }

        if (in_array($type, self::MEDIA_TYPES, true)) {
            // SQLite's original message type constraint excludes stickers.
            $displayType = $type === 'sticker' ? 'image' : $type;

            return [$displayType, json_encode([
                'caption' => $body['caption'] ?? '',
                'filename' => $body['filename'] ?? null,
            ], JSON_THROW_ON_ERROR), ['attachment_unavailable' => true]];
        }

        if (in_array($type, ['location', 'contacts', 'button', 'interactive', 'template'], true)) {
            return [$type, json_encode($body, JSON_THROW_ON_ERROR), []];
        }

        return ['text', $type === 'media_placeholder' ? 'Lampiran tidak tersedia' : 'Pesan tidak didukung', [
            'original_type' => $type,
            'attachment_unavailable' => $type === 'media_placeholder',
        ]];
    }

    private function rememberAttachment(WhatsAppCoexistenceImport $import, array $message): void
    {
        $type = $message['type'] ?? '';
        $id = $message['id'] ?? null;
        $media = $message[$type] ?? [];
        if (! in_array($type, self::MEDIA_TYPES, true) || ! $id || empty($media['id'])) {
            return;
        }

        DB::table('whatsapp_coexistence_attachments')->insertOrIgnore([
            'import_id' => $import->id,
            'meta_message_id' => $id,
            'payload' => Crypt::encryptString(json_encode(['type' => $type, 'media' => $media], JSON_THROW_ON_ERROR)),
        ]);
    }

    private function contact(WhatsAppAccount $account, string $phone, bool $create = true): ?WhatsAppContact
    {
        $key = ['user_id' => $account->user_id, 'phone_number_id' => $account->phone_number_id];
        $contact = WhatsAppContact::withoutGlobalScopes()->where($key)->whereIn('wa_id', [$phone, '+'.$phone])
            ->orderByRaw('CASE WHEN wa_id = ? THEN 0 ELSE 1 END', [$phone])
            ->lockForUpdate()->first();

        return $contact ?? ($create
            ? WhatsAppContact::withoutGlobalScopes()->firstOrCreate($key + ['wa_id' => $phone], ['name' => $phone])
            : null);
    }

    private function phone(mixed $value): ?string
    {
        if (! is_string($value) && ! is_int($value)) {
            return null;
        }

        $phone = preg_replace('/[\s()+-]/', '', (string) $value);

        return preg_match('/^[1-9][0-9]{5,14}$/D', $phone) ? $phone : null;
    }
}
