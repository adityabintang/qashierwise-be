<?php

namespace App\Services\AiAgent\Catalog;

/**
 * Pure-function parser for free-text delivery info.
 *
 * Two strategies the caller can mix:
 *   parseFree(text)        — regex heuristics on a one-shot message like
 *                            "Budi, 0812xxxx, Jl. Mawar 12, jangan pedas".
 *   extractLabeled(text)   — explicit "label = value" pairs like
 *                            "ubah nama = Budi, telepon = 0812xxxx" — useful
 *                            for partial edits where only some fields change.
 *
 * Both return arrays with keys: name, phone, address, note (all optional).
 * `compose()` rebuilds a comma-separated raw string from a parsed array so
 * the customer can edit it again.
 */
class DeliveryInfoParser
{
    /** Aliases the customer might use → canonical field name. */
    private const FIELD_ALIASES = [
        'nama' => 'name', 'name' => 'name',
        'telepon' => 'phone', 'telpon' => 'phone', 'telp' => 'phone',
        'phone' => 'phone', 'hp' => 'phone', 'no' => 'phone',
        'nomor' => 'phone', 'wa' => 'phone',
        'alamat' => 'address', 'address' => 'address',
        'catatan' => 'note', 'note' => 'note', 'keterangan' => 'note',
    ];

    /**
     * Pull every "label = value" pair out of the message. Used for partial
     * edits — empty result means the caller should fall back to parseFree().
     */
    public function extractLabeled(string $text): array
    {
        $aliasGroup = implode('|', array_keys(self::FIELD_ALIASES));

        // Lazy value — runs until the next labeled pair or end-of-string.
        $re = '/(?:ubah|ganti|edit|update|set|isi)?\s*\b('.$aliasGroup.')\b\s*[:=]\s*'
            .'(.+?)\s*'
            .'(?=(?:[\s,;\n•*_\-]+(?:ubah|ganti|edit|update|set|isi)?\s*\b(?:'.$aliasGroup.')\b\s*[:=])|$)/iu';

        if (! preg_match_all($re, $text, $matches, PREG_SET_ORDER)) {
            return [];
        }

        $result = [];
        foreach ($matches as $m) {
            $alias = strtolower(trim($m[1]));
            $field = self::FIELD_ALIASES[$alias] ?? null;
            if ($field === null) {
                continue;
            }

            $value = trim($m[2], " \t.,-_*•\n");
            if ($value === '') {
                continue;
            }

            if ($field === 'phone') {
                if (preg_match('/(?:\+?62|0)[\s\-]?[2-9]\d(?:[\s\-]?\d){6,11}/', $value, $pm)) {
                    $value = $this->normalizePhone($pm[0]);
                } else {
                    continue;
                }
            } elseif ($field === 'name') {
                $value = $this->titleCase($value);
            }

            $result[$field] = $value;
        }

        return $result;
    }

    /**
     * Rebuild a human-readable comma-separated string from a parsed array.
     * Used when the customer asks to edit — we show their existing input back.
     */
    public function compose(array $parsed): string
    {
        $parts = [];
        foreach (['name', 'phone', 'address', 'note'] as $k) {
            if (! empty($parsed[$k])) {
                $parts[] = $parsed[$k];
            }
        }

        return implode(', ', $parts);
    }

    /**
     * Best-effort extraction from a comma-separated free-text message. Each
     * segment is classified into name/phone/address/note by regex. Geographic
     * continuations (city/province) get merged with the preceding address
     * segment so "Jakarta" doesn't end up in the wrong bucket.
     */
    public function parseFree(string $text): array
    {
        $text = preg_replace('/\s+/', ' ', trim($text));

        $rawSegments = array_filter(array_map('trim',
            preg_split('/\s*[,;]\s*/u', $text) ?: []
        ), fn ($s) => $s !== '');

        // Second-pass split: when a segment mixes a geographic term AND a note
        // keyword separated by ". ", split there so each part is classified
        // separately (e.g. "Padang. Jangan pedas").
        $segments = [];
        foreach ($rawSegments as $seg) {
            if (str_contains($seg, '. ')
                && preg_match($this->geoRegex(), $seg)
                && preg_match($this->noteRegex(), $seg)) {
                foreach (preg_split('/\.\s+/u', $seg) as $sub) {
                    $sub = trim($sub, ' .');
                    if ($sub !== '') {
                        $segments[] = $sub;
                    }
                }
            } else {
                $segments[] = $seg;
            }
        }

        $phone = null;
        $addressParts = [];
        $noteParts = [];
        $nameCandidates = [];

        $phoneRe = '/(?:\+?62|0)[\s\-]?[2-9]\d(?:[\s\-]?\d){6,11}/';
        $addressRe = '/\b(jl\.?|jalan|gang|gg\.?|komplek|kompleks|kel\.|kelurahan|kec\.|kecamatan|rt\b|rw\b|no\.?\s*\d|nomor\s+\d|alamat|blok|kampung|desa|dusun|perumahan)\b/i';
        $geoRe = $this->geoRegex();
        $noteRe = $this->noteRegex();
        $labelRe = '/^(nomor|no\.?|telp\.?|telepon|hp|wa|nama(?:nya)?(?:\s+saya)?|saya|alamat)$/iu';

        foreach ($segments as $seg) {
            if ($phone === null && preg_match($phoneRe, $seg, $m)) {
                $phone = $this->normalizePhone($m[0]);
                $remainder = trim(preg_replace($phoneRe, '', $seg, 1, $count) ?? '');
                $remainder = trim(preg_replace('/^\s*(nomor|no\.?|telp\.?|telepon|hp|wa|telp)\s*[:.-]?\s*/iu', '', $remainder), " \t,.-");
                if ($remainder === '' || preg_match($labelRe, $remainder)) {
                    continue;
                }
                $seg = $remainder;
            }

            if (preg_match($addressRe, $seg)) {
                $addressParts[] = $seg;

                continue;
            }
            if (! empty($addressParts) && preg_match($geoRe, $seg) && ! preg_match($noteRe, $seg)) {
                $addressParts[] = $seg;

                continue;
            }
            if (preg_match($noteRe, $seg)) {
                $noteParts[] = $seg;

                continue;
            }

            $nameCandidates[] = $seg;
        }

        $name = null;
        if (! empty($nameCandidates)) {
            $first = array_shift($nameCandidates);
            $first = preg_replace('/^\s*(nama(?:nya)?(?:\s+saya)?|saya)\s+/iu', '', $first);
            $first = trim($first);
            // Reject "names" that are too long (likely a sentence misclassified)
            // or contain verbs/greetings.
            if ($first !== '' && mb_strlen($first) <= 60
                && ! preg_match('/\b(halo|hai|hi|kasih|info|dulu|deh|pesan|order|tolong)\b/iu', $first)) {
                $name = $this->titleCase($first);
            } else {
                array_unshift($nameCandidates, $first);
            }
            $noteParts = array_merge($nameCandidates, $noteParts);
        }

        return [
            'name' => $name !== null && $name !== '' ? $name : null,
            'phone' => $phone,
            'address' => ! empty($addressParts) ? trim(implode(', ', $addressParts), ' ,') : null,
            'note' => ! empty($noteParts) ? trim(implode('. ', $noteParts), ' .') : null,
        ];
    }

    /**
     * Indonesian phone normalization to local "08..." form for readability.
     * Both "+62..." and "62..." get rewritten.
     */
    public function normalizePhone(string $raw): string
    {
        $digits = preg_replace('/[^\d+]/', '', $raw);
        if (str_starts_with($digits, '+62')) {
            return '0'.substr($digits, 3);
        }
        if (str_starts_with($digits, '62')) {
            return '0'.substr($digits, 2);
        }

        return $digits;
    }

    public function titleCase(string $s): string
    {
        return mb_convert_case(mb_strtolower($s), MB_CASE_TITLE, 'UTF-8');
    }

    protected function geoRegex(): string
    {
        return '/\b(jakarta|bandung|surabaya|medan|semarang|makassar|palembang|tangerang|depok|bekasi|bogor|padang|pekanbaru|denpasar|yogya(?:karta)?|malang|solo|sumatera|sumatra|jawa|kalimantan|sulawesi|bali|aceh|riau|lampung|banten|papua)\b/i';
    }

    protected function noteRegex(): string
    {
        return '/\b(jangan|tanpa|tolong|minta|catatan|note|gak\s+pakai|tidak\s+pakai|extra|less|more|tambah)\b/i';
    }
}
