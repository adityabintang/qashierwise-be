<?php

namespace App\Jobs;

use App\Events\NewWhatsAppMessage;
use App\Models\WhatsAppCoexistenceImport;
use App\Models\WhatsAppContact;
use App\Models\WhatsAppMessage;
use App\Services\WhatsAppCoexistenceImportService;
use App\Services\WhatsAppMessageHistory;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class StoreWhatsAppHistoryAttachment implements ShouldQueue
{
    use Queueable;

    public const DISK = 'whatsapp_history';

    public int $tries = 4;

    public array $backoff = [10, 60, 180];

    public function __construct(public int $attachmentId) {}

    public function middleware(): array
    {
        return [(new WithoutOverlapping($this->attachmentId))->releaseAfter(25)->expireAfter(75)];
    }

    public function handle(WhatsAppCoexistenceImportService $service): void
    {
        try {
            $this->store($service);
        } catch (\Throwable) {
            // HTTP and SQL exception messages can contain customer data or temporary media URLs.
            throw new \RuntimeException('Could not store WhatsApp history attachment '.$this->attachmentId);
        }
    }

    private function store(WhatsAppCoexistenceImportService $service): void
    {
        $pending = DB::table('whatsapp_coexistence_attachments')->where('id', $this->attachmentId);
        $attachment = $pending->first();
        if (! $attachment || $attachment->processed) {
            return;
        }

        $import = WhatsAppCoexistenceImport::find($attachment->import_id);
        $account = $import ? $service->accountFor($import) : null;
        if (! $account) {
            $pending->update(['processed' => true, 'payload' => null]);

            return;
        }

        $message = WhatsAppMessage::withoutGlobalScopes()
            ->where('message_id', $attachment->meta_message_id)
            ->where('user_id', $account->user_id)
            ->where('phone_number_id', $account->phone_number_id)
            ->first();
        if (! $message) {
            return;
        }

        if (! $message->is_history_import) {
            $pending->update(['processed' => true, 'payload' => null]);

            return;
        }

        ['type' => $type, 'media' => $media] = json_decode(Crypt::decryptString($attachment->payload), true, flags: JSON_THROW_ON_ERROR);
        $downloadable = $message->sent_at && $message->sent_at >= $import->onboarded_at->subDays(14)
            && $message->sent_at <= $import->onboarded_at;
        $file = $downloadable
            ? $this->download($account->access_token, $account->phone_number_id, $type, (string) $media['id'])
            : null;

        DB::transaction(function () use ($service, $import, $pending, $message, $media, $type, $file) {
            $account = $service->accountFor($import, lock: true);
            $locked = (clone $pending)->lockForUpdate()->first();
            if (! $account || ! $locked || $locked->processed) {
                return;
            }

            $message = WhatsAppMessage::withoutGlobalScopes()->lockForUpdate()->find($message->id);
            if ($message?->is_history_import) {
                $details = json_decode($message->content, true) ?: [];
                $data = [
                    'caption' => $media['caption'] ?? $details['caption'] ?? '',
                    'filename' => $media['filename'] ?? $details['filename'] ?? null,
                ];

                if ($file) {
                    $path = self::path($account->id, $message->id);
                    if (! Storage::disk(self::DISK)->put($path, $file['body'])) {
                        throw new \RuntimeException('Private storage write failed');
                    }
                }

                $message->update([
                    'type' => $type === 'sticker' ? 'image' : $type,
                    'content' => json_encode($data, JSON_THROW_ON_ERROR),
                    'metadata' => [
                        'history_attachment' => $file !== null,
                        'attachment_unavailable' => $file === null,
                        'mime_type' => $file['mime_type'] ?? null,
                    ],
                ]);
                $contact = WhatsAppContact::withoutGlobalScopes()->find($message->contact_id);
                if ($contact) {
                    WhatsAppMessageHistory::updateContactPreview($contact, $message);
                    DB::afterCommit(fn () => rescue(fn () => NewWhatsAppMessage::dispatch($message, $contact), report: false));
                }
            }
            $pending->update(['processed' => true, 'payload' => null]);
        }, 3);
    }

    public static function path(int $accountId, int $messageId): string
    {
        return "whatsapp-history/{$accountId}/{$messageId}";
    }

    private function download(string $token, string $phoneNumberId, string $type, string $mediaId): ?array
    {
        if (! ctype_digit($mediaId)) {
            return null;
        }

        $limit = (int) config('whatsapp.media_limits.'.$type, 500 * 1024);
        $version = config('whatsapp.api_version');
        $meta = Http::withToken($token)->connectTimeout(5)->timeout(10)->withoutRedirecting()
            ->get("https://graph.facebook.com/{$version}/{$mediaId}", ['phone_number_id' => $phoneNumberId]);
        $url = $meta->json('url');

        if ($meta->serverError() || $meta->status() === 429) {
            throw new \RuntimeException('Meta media metadata temporarily unavailable');
        }

        if (! $meta->successful() || ! is_string($url) || (int) $meta->json('file_size') > $limit) {
            return null;
        }

        $parts = parse_url($url);
        if (($parts['scheme'] ?? '') !== 'https' || ($parts['host'] ?? '') !== 'lookaside.fbsbx.com'
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['port'])) {
            return null;
        }

        $response = Http::withToken($token)->connectTimeout(5)->timeout(15)->withoutRedirecting()
            ->withOptions(['stream' => true, 'read_timeout' => 10])->get($url);
        $stream = $response->toPsrResponse()->getBody();
        try {
            if ($response->serverError() || $response->status() === 429) {
                throw new \RuntimeException('Meta media temporarily unavailable');
            }
            if (! $response->successful() || (int) $response->header('Content-Length') > $limit) {
                return null;
            }

            $data = '';
            while (! $stream->eof()) {
                $chunk = $stream->read(min(65536, $limit + 1 - strlen($data)));
                if ($chunk === '' && ! $stream->eof()) {
                    throw new \RuntimeException('Meta media stream interrupted');
                }
                $data .= $chunk;
                if (strlen($data) > $limit) {
                    return null;
                }
            }

            return $data === '' ? null : [
                'body' => $data,
                'mime_type' => explode(';', $response->header('Content-Type') ?: 'application/octet-stream', 2)[0],
            ];
        } finally {
            $stream->close();
        }
    }
}
