<?php

namespace App\DTOs;

/**
 * Data Transfer Object for subscription plan details.
 *
 * Contains all information about a subscription plan including
 * pricing, features, and Polar.sh product mapping.
 */
class PlanDetails
{
    /**
     * Create a new PlanDetails instance.
     *
     * @param  string  $id  Internal plan identifier (e.g., 'free_trial', 'standard', 'pro')
     * @param  string  $name  Display name of the plan
     * @param  int  $priceMonthly  Monthly price in cents
     * @param  string  $polarProductId  Polar.sh product ID for this plan
     * @param  array  $features  List of features included in this plan
     * @param  string  $tier  Access tier level ('basic', 'standard', 'pro')
     */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly int $priceMonthly,
        public readonly string $polarProductId,
        public readonly array $features,
        public readonly string $tier,
    ) {}

    /**
     * Convert the DTO to an array representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'price_monthly' => $this->priceMonthly,
            'polar_product_id' => $this->polarProductId,
            'features' => $this->features,
            'tier' => $this->tier,
        ];
    }

    /**
     * Create a PlanDetails instance from an array.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            name: $data['name'],
            priceMonthly: $data['price_monthly'],
            polarProductId: $data['polar_product_id'],
            features: $data['features'],
            tier: $data['tier'],
        );
    }
}
