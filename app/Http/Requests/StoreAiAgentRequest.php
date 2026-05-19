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
            'order_enabled' => 'nullable|boolean',
            'qris_enabled' => 'nullable|boolean',
            'reservation_enabled' => 'nullable|boolean',
            'delivery_enabled' => 'nullable|boolean',
            'default_ongkir' => 'nullable|numeric|min:0',
            'is_active' => 'nullable|boolean',
            'settings' => 'nullable|array',
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
        ];
    }
}
