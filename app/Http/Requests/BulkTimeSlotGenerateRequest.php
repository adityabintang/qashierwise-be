<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkTimeSlotGenerateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $effectiveUserId = $this->user()?->getEffectiveUserId();

        return [
            'store_id' => [
                'required',
                Rule::exists('stores', 'id')->where('user_id', $effectiveUserId),
            ],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'opening_time' => ['required', 'date_format:H:i'],
            'closing_time' => ['required', 'date_format:H:i', 'after:opening_time'],
            'slot_duration' => ['required', 'integer', 'in:30,60,120'],
            'capacity_per_slot' => ['nullable', 'integer', 'min:1', 'max:100'],
            'exclude_dates' => ['nullable', 'array'],
            'exclude_dates.*' => ['date_format:Y-m-d'],
        ];
    }

    /**
     * Get custom error messages for validator.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        $startDate = $this->input('start_date', 'tanggal mulai');
        $endDate = $this->input('end_date', 'tanggal akhir');
        $openingTime = $this->input('opening_time', 'jam mulai');
        $closingTime = $this->input('closing_time', 'jam selesai');

        return [
            'store_id.required' => 'Toko wajib dipilih.',
            'store_id.exists' => 'Toko tidak valid.',
            'start_date.required' => 'Tanggal mulai wajib diisi.',
            'start_date.date_format' => 'Format tanggal mulai tidak valid. Gunakan format YYYY-MM-DD.',
            'end_date.required' => 'Tanggal akhir wajib diisi.',
            'end_date.date_format' => 'Format tanggal akhir tidak valid. Gunakan format YYYY-MM-DD.',
            'end_date.after_or_equal' => "Tanggal akhir ({$endDate}) harus sama atau setelah tanggal mulai ({$startDate}).",
            'opening_time.required' => 'Jam mulai wajib diisi.',
            'opening_time.date_format' => 'Format jam mulai tidak valid. Gunakan format HH:MM.',
            'closing_time.required' => 'Jam selesai wajib diisi.',
            'closing_time.date_format' => 'Format jam selesai tidak valid. Gunakan format HH:MM.',
            'closing_time.after' => "Jam selesai ({$closingTime}) harus setelah jam mulai ({$openingTime}).",
            'slot_duration.required' => 'Durasi slot wajib dipilih.',
            'slot_duration.in' => 'Durasi slot harus 30, 60, atau 120 menit.',
            'capacity_per_slot.required' => 'Kapasitas per slot wajib diisi.',
            'capacity_per_slot.integer' => 'Kapasitas per slot harus berupa angka.',
            'capacity_per_slot.min' => 'Kapasitas per slot minimal 1.',
            'capacity_per_slot.max' => 'Kapasitas per slot maksimal 100.',
            'exclude_dates.*.date_format' => 'Format tanggal pengecualian tidak valid. Gunakan format YYYY-MM-DD.',
        ];
    }
}
