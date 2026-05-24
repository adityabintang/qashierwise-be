<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreAiAgentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'bot_name' => 'required|string|max:255',
            'system_prompt' => 'required|string',
            'business_info' => 'nullable|array',
            'business_info.operating_hours' => 'nullable|string',
            'business_info.address' => 'nullable|string',
            'business_info.description' => 'nullable|string',
            'business_info.phone' => 'nullable|string',
            'default_store_id' => 'nullable|exists:stores,id',
            'catalog_id' => 'nullable|string|max:255',
            'catalog_enabled' => 'nullable|boolean',
            'order_enabled' => 'nullable|boolean',
            'qris_enabled' => 'nullable|boolean',
            'reservation_enabled' => 'nullable|boolean',
            'delivery_enabled' => 'nullable|boolean',
            'default_ongkir' => 'nullable|numeric|min:0',
            'is_active' => 'nullable|boolean',
            'settings' => 'nullable|array',
            'settings.followup_enabled' => 'nullable|boolean',
            'settings.followup_interval_minutes' => 'nullable|integer|in:5,10,15,20,30,45,60',
            'settings.followup_max_count' => 'nullable|integer|min:1|max:5',
            'settings.followup_auto_cancel' => 'nullable|boolean',
            'settings.drip_enabled' => 'nullable|boolean',
            'settings.quiet_hours_start' => ['nullable', 'string', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
            'settings.quiet_hours_end' => ['nullable', 'string', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
            'settings.max_drips_per_24h' => 'nullable|integer|min:0|max:10',
        ];
    }

    /**
     * Get custom error messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'bot_name.required' => 'Nama bot wajib diisi',
            'bot_name.max' => 'Nama bot maksimal 255 karakter',
            'system_prompt.required' => 'System prompt wajib diisi',
            'default_store_id.exists' => 'Toko tidak ditemukan',
            'settings.followup_interval_minutes.in' => 'Interval follow-up harus salah satu dari: 5, 10, 15, 20, 30, 45, atau 60 menit',
            'settings.followup_max_count.min' => 'Jumlah maksimum follow-up minimal 1',
            'settings.followup_max_count.max' => 'Jumlah maksimum follow-up maksimal 5',
            'settings.quiet_hours_start.regex' => 'Format jam tenang harus HH:MM (24-jam)',
            'settings.quiet_hours_end.regex' => 'Format jam tenang harus HH:MM (24-jam)',
        ];
    }
}
