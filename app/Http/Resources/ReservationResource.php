<?php

namespace App\Http\Resources;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReservationResource extends JsonResource
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
            'order_id' => $this->order_id,
            'customer_name' => $this->customer_name,
            'customer_phone' => $this->phone,
            'customer_email' => $this->email,
            'reservation_date' => $this->reservation_date,
            'reservation_date_formatted' => $this->reservation_date?->format('d M Y'),
            'reservation_time' => $this->reservation_time,
            'guest_count' => $this->guest_count,
            'notes' => $this->notes,
            'table' => [
                'id' => $this->table?->id,
                'name' => $this->table?->number,
                'capacity' => $this->table?->capacity,
            ],
            'selected_products' => $this->resolveSelectedProducts(),
            'payment_type' => $this->payment_type,
            'payment_type_label' => match ($this->payment_type) {
                'dp' => 'Down Payment',
                'full' => 'Full Payment',
                default => $this->payment_type,
            },
            'payment_method' => $this->payment_method,
            'total_amount' => $this->total_amount,
            'paid_amount' => $this->paid_amount,
            'remaining_amount' => $this->remaining_amount,
            'status' => $this->status,
            'status_label' => match ($this->status) {
                'pending_payment' => 'Pending Payment',
                'confirmed' => $this->payment_type === 'dp' ? 'DP Confirmed' : 'Confirmed',
                'completed' => 'Completed',
                'cancelled' => 'Cancelled',
                default => $this->status,
            },
            'status_class' => match ($this->status) {
                'pending_payment' => 'warning',
                'confirmed' => 'primary',
                'completed' => 'success',
                'cancelled' => 'danger',
                default => 'secondary',
            },
            'is_fully_paid' => $this->isFullyPaid(),
            'can_be_cancelled' => $this->canBeCancelled(),
            'calendar_event_id' => $this->calendar_event_id,
            'notified_at' => $this->notified_at,
            'cancelled_reason' => $this->cancelled_reason,
            'created_at' => $this->created_at,
            'created_at_formatted' => $this->created_at?->format('d M Y H:i'),
            'updated_at' => $this->updated_at,
            'qris_transaction' => $this->whenLoaded('qrisTransaction', function () {
                return [
                    'id' => $this->qrisTransaction->id,
                    'status' => $this->qrisTransaction->status,
                    'qr_string' => $this->qrisTransaction->qr_string,
                ];
            }),
            'store' => $this->whenLoaded('store', function () {
                return [
                    'id' => $this->store->id,
                    'name' => $this->store->name,
                ];
            }),
        ];
    }

    /**
     * Normalize selected products into a consistent shape for the UI.
     *
     * @return array<int, array<string, mixed>>
     */
    private function resolveSelectedProducts(): array
    {
        $selectedProducts = $this->selected_products;

        if (! is_array($selectedProducts) || $selectedProducts === []) {
            return [];
        }

        $first = $selectedProducts[0] ?? null;

        if (is_array($first) && array_key_exists('name', $first)) {
            return collect($selectedProducts)->map(function ($item) {
                return [
                    'id' => $item['id'] ?? null,
                    'name' => $item['name'] ?? 'Menu',
                    'price' => $item['price'] ?? 0,
                    'quantity' => isset($item['quantity']) ? (int) $item['quantity'] : 1,
                ];
            })->values()->toArray();
        }

        $productIds = collect($selectedProducts)
            ->map(function ($item) {
                if (is_array($item)) {
                    return $item['id'] ?? null;
                }

                return $item;
            })
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int) $id)
            ->values();

        if ($productIds->isEmpty()) {
            return [];
        }

        $products = Product::whereIn('id', $productIds)->get()->keyBy('id');

        return $productIds->map(function (int $productId) use ($products, $selectedProducts) {
            $product = $products->get($productId);
            $quantity = 1;

            $selectedEntry = collect($selectedProducts)->first(function ($item) use ($productId) {
                return is_array($item) && isset($item['id']) && (int) $item['id'] === $productId;
            });

            if (is_array($selectedEntry) && isset($selectedEntry['quantity'])) {
                $quantity = (int) $selectedEntry['quantity'];
            }

            return [
                'id' => $productId,
                'name' => $product?->name ?? 'Menu',
                'price' => $product?->price ?? 0,
                'quantity' => $quantity,
            ];
        })->values()->toArray();
    }
}
