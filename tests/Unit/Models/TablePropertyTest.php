<?php

namespace Tests\Unit\Models;

use App\Models\Table;
use Eris\Generators;
use Eris\TestTrait;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Property-based tests for Table model
 * 
 * Feature: point-of-sale
 */
class TablePropertyTest extends TestCase
{
    use TestTrait;

    /**
     * Feature: point-of-sale, Property 13: Table Serialization Round-Trip
     * Validates: Requirements 6.5, 6.6
     * 
     * For any valid Table object, serializing to JSON and then deserializing back
     * SHALL produce an equivalent Table object with identical field values.
     */
    #[Test]
    public function table_serialization_round_trip_preserves_data(): void
    {
        $statuses = [
            Table::STATUS_AVAILABLE,
            Table::STATUS_OCCUPIED,
            Table::STATUS_RESERVED,
            Table::STATUS_UNAVAILABLE,
        ];

        $this
            ->limitTo(100)
            ->forAll(
                Generators::pos(),
                Generators::string(),
                Generators::choose(1, 20),
                Generators::elements($statuses)
            )
            ->then(function (int $storeId, string $number, int $capacity, string $status) {
                // Create a Table instance with generated data
                $table = new Table([
                    'store_id' => $storeId,
                    'number' => $number,
                    'capacity' => $capacity,
                    'status' => $status,
                ]);

                // Serialize to JSON
                $json = $table->toJson();

                // Deserialize from JSON
                $decoded = json_decode($json, true);

                // Create new Table from decoded data
                $restoredTable = new Table($decoded);

                // Property: All fields should be preserved after round-trip
                $this->assertEquals($table->store_id, $restoredTable->store_id, 'store_id should be preserved');
                $this->assertEquals($table->number, $restoredTable->number, 'number should be preserved');
                $this->assertEquals($table->capacity, $restoredTable->capacity, 'capacity should be preserved');
                $this->assertEquals($table->status, $restoredTable->status, 'status should be preserved');
            });
    }
}
