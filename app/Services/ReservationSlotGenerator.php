<?php

namespace App\Services;

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
     *   exclude_dates?: array<int, string>
     * } $payload
     * @return array<int, string>
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
        $excludeDates = collect($payload['exclude_dates'] ?? [])
            ->filter()
            ->map(fn (string $date) => Carbon::createFromFormat('Y-m-d', $date, $timezone)->toDateString())
            ->flip();

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
                // Format as ISO 8601 datetime-local format (without timezone indicator for browser compatibility)
                $slots[] = $slotStart->format('Y-m-d\TH:i');
                $slotStart->addMinutes($slotDuration);
            }

            $currentDate->addDay();
        }

        return $slots;
    }
}
