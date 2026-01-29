<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateReservationFlowConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $data = [];

        // Convert time format H:i:s to H:i if needed
        if ($this->has('opening_time') && $this->opening_time) {
            $data['opening_time'] = substr($this->opening_time, 0, 5);
        }

        if ($this->has('closing_time') && $this->closing_time) {
            $data['closing_time'] = substr($this->closing_time, 0, 5);
        }

        // Ensure blocked_times is an array
        if ($this->has('blocked_times')) {
            $blockedTimes = $this->blocked_times;
            if (is_string($blockedTimes)) {
                $blockedTimes = $blockedTimes ? explode(',', $blockedTimes) : [];
            }
            if (! is_array($blockedTimes)) {
                $blockedTimes = [];
            }
            // Trim each time to H:i format
            $data['blocked_times'] = array_map(fn ($t) => substr(trim($t), 0, 5), $blockedTimes);
        }

        if (! empty($data)) {
            $this->merge($data);
        }
    }

    public function rules(): array
    {
        return [
            // Flow Identity
            'flow_name' => 'sometimes|string|max:255',

            // Time Configuration
            'opening_time' => 'sometimes|nullable|date_format:H:i',
            'closing_time' => 'sometimes|nullable|date_format:H:i|after:opening_time',
            'time_interval' => 'sometimes|integer|in:30,60,90,120',
            'blocked_times' => 'sometimes|nullable|array',
            'blocked_times.*' => 'date_format:H:i',
            'operating_days' => 'sometimes|array',
            'operating_days.*' => 'integer|between:1,7',

            // Booking Configuration
            'max_advance_days' => 'sometimes|integer|min:1|max:365',
            'min_advance_hours' => 'sometimes|integer|min:0|max:72',
            'max_guests' => 'sometimes|integer|min:1|max:100',
            'min_guests' => 'sometimes|integer|min:1|max:100',

            // Table Configuration
            'enable_table_selection' => 'sometimes|boolean',
            'available_table_ids' => 'sometimes|array',
            'available_table_ids.*' => 'integer|exists:tables,id',

            // Menu Configuration
            'enable_menu_selection' => 'sometimes|boolean',
            'available_product_ids' => 'sometimes|array',
            'available_product_ids.*' => 'integer|exists:products,id',
            'require_menu_selection' => 'sometimes|boolean',

            // Payment Configuration
            'enable_payment' => 'sometimes|boolean',
            'table_fee' => 'sometimes|numeric|min:0',
            'dp_percentage' => 'sometimes|numeric|min:10|max:100',
            'allow_full_payment' => 'sometimes|boolean',
            'allow_dp_payment' => 'sometimes|boolean',
            'enable_qris' => 'sometimes|boolean',
            'enable_cash' => 'sometimes|boolean',

            // Customer Data Configuration
            'require_email' => 'sometimes|boolean',
            'require_event_type' => 'sometimes|boolean',
            'enabled_event_types' => 'sometimes|array',
            'enabled_event_types.*' => 'string|in:regular,birthday,meeting,anniversary,family,other',

            // Messages
            'header_text' => 'sometimes|string|max:60',
            'body_text' => 'sometimes|nullable|string|max:1024',
            'footer_text' => 'sometimes|string|max:60',
            'cta_text' => 'sometimes|string|max:20',
        ];
    }

    public function messages(): array
    {
        return [
            'closing_time.after' => 'Jam tutup harus setelah jam buka',
            'time_interval.in' => 'Interval waktu harus 30, 60, 90, atau 120 menit',
            'operating_days.*.between' => 'Hari operasional harus antara 1 (Senin) dan 7 (Minggu)',
            'dp_percentage.min' => 'Persentase DP minimal 10%',
            'dp_percentage.max' => 'Persentase DP maksimal 100%',
            'header_text.max' => 'Header text maksimal 60 karakter',
            'cta_text.max' => 'CTA text maksimal 20 karakter',
        ];
    }
}
