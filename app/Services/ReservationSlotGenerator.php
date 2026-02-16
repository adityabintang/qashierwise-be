<?php

namespace App\Services;

use App\Models\Table;
use Carbon\Carbon;

class ReservationSlotGenerator
{
    /**
     * @param array{
     *   start_date: string,
     *   end_date: string,
     *   opening_time: string,
     *   closing_time: string,
     *   slot_duration: int,
     *   store_id?: int,
     *   capacity_per_slot?: int,
     *   exclude_dates?: array<int, string>
     * } $payload
     * @return array<int, array{datetime: string, capacity: int}>
     */
    public function generate(array $payload): array
    {
        // Set timezone to WIB (Asia/Jakarta) for slot generation
        $timezone = 'Asia/Jakarta';

        $startDate = Carbon::createFromFormat('Y-m-d', $payload['start_date'], $timezone)->startOfDay();
        $endDate = Carbon::createFromFormat('Y-m-d', $payload['end_date'], $timezone)->startOfDay();
        $openingTime = $payload['opening_time'];
        $closingTime = $payload['closing_time'];
        $slotDuration = (int) $payload['slot_duration'];
        $storeId = $payload['store_id'] ?? null;
        $excludeDates = collect($payload['exclude_dates'] ?? [])
            ->filter()
            ->map(fn (string $date) => Carbon::createFromFormat('Y-m-d', $date, $timezone)->toDateString())
            ->flip();

        $capacityPerSlot = $this->resolveCapacityPerSlot($payload, $storeId);

        $slots = [];
        $currentDate = $startDate->copy();

        while ($currentDate->lte($endDate)) {
            $dateKey = $currentDate->toDateString();
            if ($excludeDates->has($dateKey)) {
                $currentDate->addDay();

                continue;
            }

            $slotStart = Carbon::createFromFormat('Y-m-d H:i', $dateKey.' '.$openingTime, $timezone);
            $slotEnd = Carbon::createFromFormat('Y-m-d H:i', $dateKey.' '.$closingTime, $timezone);

            while ($slotStart->lt($slotEnd)) {
                // Store as array with datetime and capacity
                $slots[] = [
                    'datetime' => $slotStart->format('Y-m-d\TH:i'),
                    'capacity' => $capacityPerSlot,
                ];
                $slotStart->addMinutes($slotDuration);
            }

            $currentDate->addDay();
        }

        return $slots;
    }

    /**
     * Calculate total capacity from available tables for a store.
     */
    private function calculateCapacityFromTables(?int $storeId): int
    {
        if (! $storeId) {
            return 12; // Default capacity if no store specified
        }

        return Table::where('store_id', $storeId)
            ->where('status', '!=', Table::STATUS_UNAVAILABLE)
            ->sum('capacity');
    }

    /**
     * Resolve capacity per slot from payload or table capacity.
     *
     * @param  array{capacity_per_slot?: int}  $payload
     */
    private function resolveCapacityPerSlot(array $payload, ?int $storeId): int
    {
        $payloadCapacity = $payload['capacity_per_slot'] ?? null;

        if (is_numeric($payloadCapacity) && (int) $payloadCapacity > 0) {
            return (int) $payloadCapacity;
        }

        return $this->calculateCapacityFromTables($storeId);
    }
}
