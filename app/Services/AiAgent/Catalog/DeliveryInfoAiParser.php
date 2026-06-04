<?php

namespace App\Services\AiAgent\Catalog;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * AI-powered free-text → structured delivery info extractor.
 *
 * Why this exists alongside the regex DeliveryInfoParser:
 *   - Regex parser breaks on natural variations ("nama saya Budi sih bro,
 *     hp 0812", multi-word streets without commas, ambiguous order).
 *   - AI with response_format=json_schema GUARANTEES valid JSON output that
 *     matches the schema — eliminating parse errors, missing fields, and
 *     prompt-format drift across model versions.
 *   - Provider: Alibaba DashScope OpenAI-compatible endpoint (BYTEPLUS_ARK_*
 *     env vars are reused). Empirically verified 2026-05-28 that DeepSeek
 *     v3.2 supports the json_schema response_format.
 *
 * Failure mode: returns null on any API error / parse error. Caller falls
 * back to the regex parser so the flow never hard-stalls on a single
 * extraction failure.
 */
class DeliveryInfoAiParser
{
    /**
     * Extract { name, phone, address, note } from free-text customer input.
     * Key 'note' singular matches DeliveryInfoParser regex output for parity.
     *
     * @return array{name: string, phone: string, address: string, note: string}|null
     */
    public function parse(string $text): ?array
    {
        $text = trim($text);
        if ($text === '') {
            return null;
        }

        $config = config('services.byteplus_ark');
        $key    = $config['api_key']  ?? null;
        $url    = $config['base_url'] ?? null;
        $model  = $config['model']    ?? null;

        if (! $key || ! $url || ! $model) {
            Log::warning('DeliveryInfoAiParser: BytePlus ARK config tidak lengkap');
            return null;
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $key,
                'Content-Type'  => 'application/json',
            ])->timeout(20)->post(rtrim($url, '/') . '/chat/completions', [
                'model'       => $model,
                'temperature' => 0.1,
                'max_tokens'  => 500,
                'messages' => [
                    [
                        'role'    => 'system',
                        'content' => $this->systemPrompt(),
                    ],
                    [
                        'role'    => 'user',
                        'content' => $text,
                    ],
                ],
                'response_format' => [
                    'type'        => 'json_schema',
                    'json_schema' => [
                        'name'   => 'delivery_info',
                        'strict' => true,
                        'schema' => $this->schema(),
                    ],
                ],
            ]);

            if (! $response->successful()) {
                Log::warning('DeliveryInfoAiParser: API call failed', [
                    'status' => $response->status(),
                    'body'   => substr($response->body(), 0, 200),
                ]);
                return null;
            }

            $content = $response->json('choices.0.message.content');
            if (! is_string($content) || $content === '') {
                Log::warning('DeliveryInfoAiParser: empty model response');
                return null;
            }

            $parsed = json_decode($content, true, 16, JSON_THROW_ON_ERROR);

            // Empty-string-aware normalization — model fills required fields
            // with "" for missing data per the schema; caller may treat empty
            // string as "not provided".
            return [
                'name'    => trim((string) ($parsed['name']    ?? '')),
                'phone'   => trim((string) ($parsed['phone']   ?? '')),
                'address' => trim((string) ($parsed['address'] ?? '')),
                'note'   => trim((string) ($parsed['note']   ?? '')),
            ];
        } catch (\Throwable $e) {
            Log::warning('DeliveryInfoAiParser: exception', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /** Indonesia-aware extraction instructions (kept tight to avoid drift). */
    private function systemPrompt(): string
    {
        return <<<'PROMPT'
Anda mengekstrak informasi delivery dari pesan customer Indonesia ke dalam JSON.

ATURAN:
- "name": nama lengkap penerima paket. Jika tidak ada → string kosong "".
- "phone": nomor HP/WA penerima (digit saja, hapus spasi/dash/+). Jika tidak ada → "".
- "address": alamat fisik lengkap (jalan, nomor, RT/RW, kelurahan, kota, landmark). JANGAN masukkan nama atau no HP di sini.
- "notes": catatan tambahan untuk kurir/penjual (misal "jangan kasih sambal", "titip ke satpam"). Jika tidak ada → "".

Kembalikan HANYA JSON sesuai schema, tanpa penjelasan apapun.
PROMPT;
    }

    /** JSON schema for the structured output. */
    private function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'name'    => ['type' => 'string', 'description' => 'Nama lengkap penerima paket'],
                'phone'   => ['type' => 'string', 'description' => 'Nomor HP/WA penerima (digit saja)'],
                'address' => ['type' => 'string', 'description' => 'Alamat fisik lengkap, TANPA nama atau no HP'],
                'note'   => ['type' => 'string', 'description' => 'Catatan tambahan untuk kurir, "" jika tidak ada'],
            ],
            'required' => ['name', 'phone', 'address', 'note'],
            'additionalProperties' => false,
        ];
    }
}
