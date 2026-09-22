<?php

namespace Database\Factories;

use App\Models\KeyType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KeyType>
 */
class KeyTypeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->lexify('???????'),
            'name' => fake()->words(3, true),
            'format_template' => fn (array $attributes): string => strtoupper($attributes['code']).'-{number:04d}',
            'description' => null,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
