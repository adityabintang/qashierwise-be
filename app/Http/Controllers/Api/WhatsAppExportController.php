<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppContact;
use App\Models\WhatsAppMessage;
use App\Services\MediaStorageService;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class WhatsAppExportController extends Controller
{
    private const COLUMNS = ['Nama kontak', 'Nomor WhatsApp', 'Waktu pesan', 'Arah', 'Jenis', 'Status', 'Isi', 'Caption', 'Nama file', 'Lampiran'];

    public function contacts(Request $request, SubscriptionService $subscriptions)
    {
        $request->validate(['format' => 'sometimes|in:csv,xlsx', 'search' => 'nullable|string|max:200']);
        $this->authorizeExport($request, $subscriptions);
        $account = $this->activeAccount($request);
        $query = WhatsAppContact::query()->where('user_id', $request->user()->id)->where('phone_number_id', $account->phone_number_id)
            ->with('tags')->when($request->filled('search'), fn ($q) => $q->where(fn ($s) => $s->where('name', 'like', '%'.$request->search.'%')->orWhere('wa_id', 'like', '%'.$request->search.'%')));

        return $this->exportResponse($query->orderBy('id'), ['Nama', 'Nomor WhatsApp', 'Tag', 'Tanggal ditambahkan', 'Waktu pesan terakhir'], function ($contact) {
            return [$contact->name, $contact->phone_number, $contact->tags->pluck('name')->implode(', '), $contact->created_at?->toISOString(), $contact->last_message_at?->toISOString()];
        }, 'kontak', $request->input('format', 'csv'));
    }

    public function messages(Request $request, SubscriptionService $subscriptions)
    {
        $request->validate(['format' => 'sometimes|in:csv,xlsx', 'contact_id' => 'nullable|integer', 'from' => 'nullable|date_format:Y-m-d', 'to' => 'nullable|date_format:Y-m-d', 'timezone' => 'nullable|timezone']);
        abort_if($request->filled('from') xor $request->filled('to'), 422, 'Tanggal mulai dan akhir harus diisi bersama.');
        abort_if($request->filled('from') && $request->from > $request->to, 422, 'Tanggal mulai harus sebelum atau sama dengan tanggal akhir.');
        $this->authorizeExport($request, $subscriptions);
        $account = $this->activeAccount($request);
        if ($request->filled('contact_id')) {
            abort_unless(WhatsAppContact::query()->where('id', $request->integer('contact_id'))->where('user_id', $request->user()->id)->where('phone_number_id', $account->phone_number_id)->exists(), 404);
        }
        $query = WhatsAppMessage::query()->where('user_id', $request->user()->id)->where('phone_number_id', $account->phone_number_id)
            ->with(['contact:id,name,wa_id', 'media' => fn ($q) => $q->where('whatsapp_account_id', $account->id)])
            ->when($request->filled('contact_id'), fn ($q) => $q->where('contact_id', $request->integer('contact_id')));
        if ($request->filled('from')) {
            $tz = $request->input('timezone', 'UTC');
            $query->whereRaw('COALESCE(sent_at, created_at) BETWEEN ? AND ?', [\Illuminate\Support\Carbon::parse($request->from, $tz)->startOfDay()->utc(), \Illuminate\Support\Carbon::parse($request->to, $tz)->endOfDay()->utc()]);
        }

        return $this->exportResponse($query->orderByRaw('COALESCE(sent_at, created_at) ASC')->orderBy('id'), self::COLUMNS, function ($message) {
            $content = is_array($message->content) ? $message->content : (json_decode($message->content ?? '', true) ?: []);
            $media = $message->media->first();
            $hasAttachment = in_array($message->type, ['image', 'video', 'audio', 'document', 'sticker'], true);
            return [$message->contact?->name, $message->contact?->phone_number, $message->sent_at?->toISOString() ?? $message->created_at?->toISOString(), $message->direction, $message->type, $message->status, $message->type === 'text' ? $message->content : ($content['text'] ?? $content['body'] ?? ''), $content['caption'] ?? '', $content['filename'] ?? $media?->filename ?? '', $hasAttachment ? url('/whatsapp/attachments/'.$message->id) : ''];
        }, 'pesan', $request->input('format', 'csv'));
    }

    private function authorizeExport(Request $request, SubscriptionService $subscriptions): void
    {
        abort_if(! $request->user()->isSuperAdmin() && $request->user()->isAuthor(), 403, 'Hanya pemilik akun WhatsApp yang dapat mengekspor data.');
        abort_unless(config('whatsapp_exports.free_enabled', true) || $subscriptions->canAccessFeature($request->user(), 'pro'), 403, 'Fitur ekspor tersedia untuk paket Pro.');
    }

    private function activeAccount(Request $request): WhatsAppAccount
    {
        abort_if(! $request->user()->isSuperAdmin() && $request->user()->isAuthor(), 403, 'Hanya pemilik akun WhatsApp yang dapat membuka lampiran.');
        return WhatsAppAccount::query()->where('user_id', $request->user()->id)->where('is_active', true)->firstOrFail();
    }

    private function exportResponse($query, array $headers, callable $mapper, string $name, string $format)
    {
        $maxRows = (int) config('whatsapp_exports.max_rows', 25000);
        $maxBytes = (int) config('whatsapp_exports.max_bytes', 26214400);
        $rows = 0;
        $bytes = 0;
        $path = tempnam(sys_get_temp_dir(), 'qwe-');
        $out = $format === 'csv' ? fopen($path, 'w') : null;
        $excel = null;
        $sheet = null;
        try {
            if ($format === 'csv') {
                fwrite($out, "\xEF\xBB\xBF");
                $this->writeCsvRow($out, $headers);
                $bytes += 3;
            } else {
                $excel = \avadim\FastExcelWriter\Excel::create(['Ekspor']);
                $sheet = $excel->sheet();
                $sheet->writeHeader($headers);
                $sheet->setColFormats(array_fill(0, count($headers), '@'));
                $bytes += array_sum(array_map('strlen', $headers));
            }
            $query->chunk(500, function ($items) use ($out, &$excel, &$sheet, $format, $headers, $mapper, $maxRows, $maxBytes, &$rows, &$bytes, $path) {
                foreach ($items as $item) {
                    if (++$rows > $maxRows) abort(413, 'Ekspor terlalu besar. Persempit tanggal atau pencarian.');
                    $values = array_map(fn ($value) => $this->safeCell($value), $mapper($item));
                    $bytes += array_sum(array_map('strlen', $values));
                    if ($bytes > $maxBytes) abort(413, 'Ekspor terlalu besar. Persempit tanggal atau pencarian.');
                    if ($format === 'csv') $this->writeCsvRow($out, $values);
                    else $sheet->writeRow($values);
                }
            });
            if ($format === 'csv') fclose($out);
            else $excel->save($path);
        } catch (\Throwable $exception) {
            if (is_resource($out)) fclose($out);
            @unlink($path);
            throw $exception;
        }
        if (filesize($path) > $maxBytes) {
            @unlink($path);
            abort(413, 'Ekspor terlalu besar. Persempit tanggal atau pencarian.');
        }
        $mime = $format === 'csv' ? 'text/csv; charset=UTF-8' : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
        return response()->download($path, $name.'-'.now()->format('Ymd-His').'.'.$format, ['Content-Type' => $mime, 'Cache-Control' => 'private, no-store, no-cache, must-revalidate'])->deleteFileAfterSend(true);
    }

    private function writeCsvRow($stream, array $row): void
    {
        fputcsv($stream, $row);
    }

    private function safeCell($value): string
    {
        $value = (string) ($value ?? '');
        return preg_match('/^[\s]*[=+@\-]/u', $value) ? "'".$value : $value;
    }

    public function attachment(Request $request, int $messageId, MediaStorageService $storage)
    {
        $account = $this->activeAccount($request);
        $message = WhatsAppMessage::query()->where('id', $messageId)->where('user_id', $request->user()->id)->where('phone_number_id', $account->phone_number_id)->firstOrFail();
        $media = $message->media()->where('whatsapp_account_id', $account->id)->first();
        abort_if(! in_array($message->type, ['image', 'video', 'audio', 'document', 'sticker'], true), 404);
        if (! $media || ! $media->local_path) {
            return response()->view('whatsapp.attachment-unavailable', [], 404);
        }
        $disk = $storage->getDiskName();
        if (! Storage::disk($disk)->exists($media->local_path)) {
            return response()->view('whatsapp.attachment-unavailable', [], 404);
        }
        return Storage::disk($disk)->download($media->local_path, $media->filename ?: 'lampiran', ['Cache-Control' => 'private, no-store']);
    }
}
