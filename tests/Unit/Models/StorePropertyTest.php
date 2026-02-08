<?php

namespace Tests\Unit\Models;

use App\Models\Store;
use Eris\Generators;
use Eris\TestTrait;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Property-based tests for Store model
 *
 * Feature: point-of-sale
 */
class StorePropertyTest extends TestCase
{
    use TestTrait;

    /**
     * Feature: point-of-sale, Property 10: Store Serialization Round-Trip
     * Validates: Requirements 5.5, 5.6
     *
     * For any valid Store object, serializing to JSON and then deserializing back
     * SHALL produce an equivalent Store object with identical field values.
     */
    #[Test]
    public function store_serialization_round_trip_preserves_data(): void
    {
        $this
            ->limitTo(100)
            ->forAll(
                Generators::string(),
                Generators::suchThat(
                    fn ($s) => strlen($s) > 0 && strlen($s) <= 50,
                    Generators::string()
                ),
                Generators::string(),
                Generators::string(),
                Generators::bool()
            )
            ->then(function (string $name, string $code, string $address, string $phone, bool $isActive) {
                // Create a Store instance with generated data
                $store = new Store([
                    'name' => $name,
                    'code' => $code,
                    'address' => $address,
                    'phone' => $phone,
                    'is_active' => $isActive,
                ]);

                // Serialize to JSON
                $json = $store->toJson();

                // Deserialize from JSON
                $decoded = json_decode($json, true);

                // Create new Store from decoded data
                $restoredStore = new Store($decoded);

                // Property: All fields should be preserved after round-trip
                $this->assertEquals($store->name, $restoredStore->name, 'Name should be preserved');
                $this->assertEquals($store->code, $restoredStore->code, 'Code should be preserved');
                $this->assertEquals($store->address, $restoredStore->address, 'Address should be preserved');
                $this->assertEquals($store->phone, $restoredStore->phone, 'Phone should be preserved');
                $this->assertEquals($store->is_active, $restoredStore->is_active, 'is_active should be preserved');
            });
    }
}
