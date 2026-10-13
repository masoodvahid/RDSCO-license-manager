<?php

namespace Database\Factories;

use App\Enums\CustomerStatus;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'company_name' => fake()->company(),
            'mobile' => '0912'.fake()->numerify('#######'),
            'email' => fake()->unique()->safeEmail(),
            'status' => CustomerStatus::Active,
        ];
    }

    public function suspended(): static
    {
        return $this->state(['status' => CustomerStatus::Suspended]);
    }
}
