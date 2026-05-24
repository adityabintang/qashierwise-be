<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Typed accessor for ai_agents.settings JSON.
 *
 * Reads return an array with every field present and clamped to a safe range,
 * even if the raw JSON is partial or null. Writes accept an array and persist
 * the canonical shape — unknown keys are dropped so the column stays clean.
 *
 * Field contract (mirrors development/config-plan.md C5 + C8):
 *
 *   drip_enabled                bool   default true
 *   followup_enabled            bool   default true
 *   followup_interval_minutes   int    default 10, clamped to [5, 60]
 *   followup_max_count          int    default 3,  clamped to [1, 5]
 *   followup_auto_cancel        bool   default true
 *   quiet_hours_start           string|null  "HH:MM" or null
 *   quiet_hours_end             string|null  "HH:MM" or null
 *   max_drips_per_24h           int    default 3, clamped to [0, 10]
 */
class AiAgentSettings implements CastsAttributes
{
    public const ALLOWED_INTERVALS = [5, 10, 15, 20, 30, 45, 60];

    public const DEFAULTS = [
        'drip_enabled' => true,
        'followup_enabled' => true,
        'followup_interval_minutes' => 10,
        'followup_max_count' => 3,
        'followup_auto_cancel' => true,
        'quiet_hours_start' => null,
        'quiet_hours_end' => null,
        'max_drips_per_24h' => 3,
    ];

    public function get(Model $model, string $key, mixed $value, array $attributes): array
    {
        $raw = is_string($value) ? json_decode($value, true) : (array) $value;
        if (! is_array($raw)) {
            $raw = [];
        }

        return $this->normalize($raw);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        $input = is_array($value) ? $value : [];

        return [$key => json_encode($this->normalize($input))];
    }

    /**
     * Merge with defaults and clamp values to their valid range.
     * Unknown keys are dropped so the persisted JSON stays canonical.
     */
    protected function normalize(array $input): array
    {
        $out = self::DEFAULTS;

        foreach (self::DEFAULTS as $field => $default) {
            if (! array_key_exists($field, $input)) {
                continue;
            }
            $out[$field] = $this->coerce($field, $input[$field], $default);
        }

        return $out;
    }

    protected function coerce(string $field, mixed $raw, mixed $default): mixed
    {
        return match ($field) {
            'drip_enabled', 'followup_enabled', 'followup_auto_cancel' => $this->toBool($raw, (bool) $default),
            'followup_interval_minutes' => $this->clampInterval($raw, (int) $default),
            'followup_max_count' => $this->clampInt($raw, (int) $default, 1, 5),
            'max_drips_per_24h' => $this->clampInt($raw, (int) $default, 0, 10),
            'quiet_hours_start', 'quiet_hours_end' => $this->coerceTime($raw),
            default => $default,
        };
    }

    protected function toBool(mixed $raw, bool $default): bool
    {
        if (is_bool($raw)) {
            return $raw;
        }
        if (is_string($raw)) {
            return in_array(strtolower($raw), ['1', 'true', 'yes', 'on'], true);
        }
        if (is_int($raw)) {
            return $raw !== 0;
        }

        return $default;
    }

    protected function clampInt(mixed $raw, int $default, int $min, int $max): int
    {
        if (! is_numeric($raw)) {
            return $default;
        }
        $n = (int) $raw;

        return max($min, min($max, $n));
    }

    /**
     * Interval is constrained to the discrete options the dashboard exposes.
     * Anything outside the allowed list falls back to the default.
     */
    protected function clampInterval(mixed $raw, int $default): int
    {
        if (! is_numeric($raw)) {
            return $default;
        }
        $n = (int) $raw;

        return in_array($n, self::ALLOWED_INTERVALS, true) ? $n : $default;
    }

    protected function coerceTime(mixed $raw): ?string
    {
        if (! is_string($raw)) {
            return null;
        }
        $trim = trim($raw);
        if ($trim === '') {
            return null;
        }
        // Accept HH:MM (24-hour). Anything else is treated as missing.
        return preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $trim) === 1 ? $trim : null;
    }
}
