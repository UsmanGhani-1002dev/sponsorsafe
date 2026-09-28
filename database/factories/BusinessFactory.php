<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class BusinessFactory extends Factory
{
    public function definition(): array
    {
        return ['name' => fake()->company().' Ltd', 'licence_number' => strtoupper(fake()->bothify('??#######')), 'status' => 'active'];
    }

    public function suspended(): static
    {
        return $this->state(['status' => 'suspended', 'suspended_at' => now()]);
    }
}
