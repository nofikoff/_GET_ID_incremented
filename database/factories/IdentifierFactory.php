<?php

namespace Database\Factories;

use App\Domain\KeyType\DocumentName;
use App\Domain\KeyType\IdentifierFormat;
use App\Models\Identifier;
use App\Models\KeyType;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Builds registry rows directly, past SequenceIssuer, so the pair's counter row is not advanced.
 *
 * @extends Factory<Identifier>
 */
class IdentifierFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'key_type_id' => KeyType::factory(),
            'name' => fake()->unique()->sentence(3),
            'name_slug' => fn (array $attributes): string => DocumentName::fromString($attributes['name'])->slug,
            'sequence_number' => fake()->unique()->numberBetween(1, 1_000_000),
            'formatted_id' => fn (array $attributes): string => IdentifierFormat::parse(KeyType::query()->findOrFail($attributes['key_type_id'])->format_template)
                ->format($attributes['sequence_number'], DocumentName::fromString($attributes['name'])),
            'created_by' => User::factory(),
        ];
    }
}
