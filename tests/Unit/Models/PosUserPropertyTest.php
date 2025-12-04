<?php

namespace Tests\Unit\Models;

use App\Models\PosUser;
use Eris\Generators;
use Eris\TestTrait;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Property-based tests for PosUser model
 * 
 * Feature: point-of-sale
 */
class PosUserPropertyTest extends TestCase
{
    use TestTrait;

    /**
     * Feature: point-of-sale, Property 14: Staff User Serialization Round-Trip
     * Validates: Requirements 7.5, 7.6
     * 
     * For any valid PosUser object, serializing to JSON and then deserializing back
     * SHALL produce an equivalent PosUser object with role information.
     */
    #[Test]
    public function pos_user_serialization_round_trip_preserves_data(): void
    {
        $this
            ->limitTo(100)
            ->forAll(
                Generators::pos(),
                Generators::pos(),
                Generators::pos(),
                Generators::bool()
            )
            ->then(function (int $userId, int $storeId, int $roleId, bool $isActive) {
                // Create a PosUser instance with generated data
                $posUser = new PosUser([
                    'user_id' => $userId,
                    'store_id' => $storeId,
                    'role_id' => $roleId,
                    'is_active' => $isActive,
                ]);

                // Serialize to JSON
                $json = $posUser->toJson();

                // Deserialize from JSON
                $decoded = json_decode($json, true);

                // Create new PosUser from decoded data
                $restoredPosUser = new PosUser($decoded);

                // Property: All fields should be preserved after round-trip
                $this->assertEquals($posUser->user_id, $restoredPosUser->user_id, 'user_id should be preserved');
                $this->assertEquals($posUser->store_id, $restoredPosUser->store_id, 'store_id should be preserved');
                $this->assertEquals($posUser->role_id, $restoredPosUser->role_id, 'role_id should be preserved');
                $this->assertEquals($posUser->is_active, $restoredPosUser->is_active, 'is_active should be preserved');
            });
    }
}
