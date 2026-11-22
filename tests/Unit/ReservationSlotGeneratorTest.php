<?php

namespace Tests\Unit;

use App\Services\ReservationSlotGenerator;
use PHPUnit\Framework\TestCase;

class ReservationSlotGeneratorTest extends TestCase
{
    public function test_generate_uses_payload_capacity_per_slot(): void
    {
        $generator = new ReservationSlotGenerator;

        $slots = $generator->generate([
            'start_date' => '2026-02-16',
            'end_date' => '2026-02-16',
            'opening_time' => '17:00',
            'closing_time' => '18:00',
            'slot_duration' => 30,
            'capacity_per_slot' => 24,
        ]);

        $this->assertCount(2, $slots);
        $this->assertSame('2026-02-16T17:00', $slots[0]['datetime']);
        $this->assertSame(24, $slots[0]['capacity']);
        $this->assertSame(24, $slots[1]['capacity']);
    }
}
