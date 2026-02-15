<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReservationFormRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Public form - no authorization needed
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'customer_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'regex:/^(\+62|62|0)[0-9]{9,12}$/'],
            'email' => ['required', 'email', 'max:255'],
            'reservation_date' => ['required', 'date', 'after:today'],
            'guest_count' => ['required', 'integer', 'min:1', 'max:50'],
            'table_id' => ['required', 'integer', 'exists:tables,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'selected_products' => ['nullable', 'array'],
            'selected_products.*.id' => ['required', 'integer', 'exists:products,id'],
            'selected_products.*.quantity' => ['nullable', 'integer', 'min:1', 'max:50'],
            'payment_type' => ['required', 'in:dp,full'],
            'store_id' => ['nullable', 'integer', 'exists:stores,id'],
        ];
    }

    /**
     * Get custom error messages for validator.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'customer_name.required' => 'Nama pelanggan harus diisi.',
            'phone.required' => 'Nomor WhatsApp harus diisi.',
            'phone.regex' => 'Format nomor WhatsApp tidak valid. Gunakan format Indonesia (08xx atau +628xx).',
            'email.required' => 'Email harus diisi.',
            'email.email' => 'Format email tidak valid.',
            'reservation_date.required' => 'Tanggal reservasi harus dipilih.',
            'reservation_date.after' => 'Tanggal reservasi harus setelah hari ini.',
            'guest_count.required' => 'Jumlah tamu harus diisi.',
            'guest_count.min' => 'Jumlah tamu minimal 1 orang.',
            'guest_count.max' => 'Jumlah tamu maksimal 50 orang.',
            'table_id.required' => 'Meja harus dipilih.',
            'table_id.exists' => 'Meja yang dipilih tidak valid.',
            'notes.max' => 'Catatan maksimal 1000 karakter.',
            'selected_products.*.quantity.min' => 'Jumlah menu minimal 1.',
            'selected_products.*.quantity.max' => 'Jumlah menu maksimal 50.',
            'payment_type.required' => 'Jenis pembayaran harus dipilih.',
            'payment_type.in' => 'Jenis pembayaran harus DP atau Lunas.',
        ];
    }
}
