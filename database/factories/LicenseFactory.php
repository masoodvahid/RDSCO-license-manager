<?php

namespace Database\Factories;

use App\Enums\LicenseStatus;
use App\Models\Customer;
use App\Models\License;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<License>
 */
class LicenseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'product_id' => Product::factory(),
            'max_users' => 10,
            'starts_at' => now()->subMonth()->toDateString(),
            'expires_at' => now()->addYear()->toDateString(),
            'status' => LicenseStatus::Active,
        ];
    }

    public function expired(): static
    {
        return $this->state(['expires_at' => now()->subDay()->toDateString()]);
    }

    public function suspended(): static
    {
        return $this->state(['status' => LicenseStatus::Suspended]);
    }
}
