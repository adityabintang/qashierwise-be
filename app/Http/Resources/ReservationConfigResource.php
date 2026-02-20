<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReservationConfigResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'store_id' => $this->store_id,
            'is_active' => $this->is_active,
            'available_slots' => $this->available_slots,
            'available_dates_formatted' => $this->getAvailableDatesFormatted(),
            'capacity_per_slot' => $this->capacity_per_slot,
            'guest_options' => $this->guest_options,
            'reservation_fee' => $this->reservation_fee,
            'dp_percentage' => $this->dp_percentage,
            'allow_full_payment' => $this->allow_full_payment,
            'allow_dp_payment' => $this->allow_dp_payment,
            'available_tables' => $this->available_tables,
            'available_products' => $this->available_products,
            'enable_menu_selection' => $this->enable_menu_selection,
            'require_menu_selection' => $this->require_menu_selection,
            // Reminder fields
            'reminder_enabled' => $this->reminder_enabled,
            'reminder_template' => $this->reminder_template,
            'reminder_template_language' => $this->reminder_template_language,
            'reminder_param_mapping' => $this->reminder_param_mapping,
            'reminder_timing' => $this->reminder_timing,
            'available_mapping_fields' => \App\Models\ReservationConfig::getAvailableMappingFields(),
            'is_reminder_configured' => $this->isReminderConfigured(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'store' => $this->whenLoaded('store', function () {
                return [
                    'id' => $this->store->id,
                    'name' => $this->store->name,
                    'address' => $this->store->address,
                ];
            }),
        ];
    }
}
