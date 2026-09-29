<?php

namespace Database\Factories;

use App\Models\Business;
use Illuminate\Database\Eloquent\Factories\Factory;

class WorkSiteFactory extends Factory
{
    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'name' => fake()->randomElement(['Main shop', 'Second shop', 'Warehouse', 'Head office']),
            'address' => fake()->streetAddress().', Southampton '.fake()->postcode(),
        ];
    }
}
