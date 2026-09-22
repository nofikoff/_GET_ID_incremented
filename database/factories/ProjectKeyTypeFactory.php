<?php

namespace Database\Factories;

use App\Models\KeyType;
use App\Models\Project;
use App\Models\ProjectKeyType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectKeyType>
 */
class ProjectKeyTypeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'key_type_id' => KeyType::factory(),
            'seed_sequence' => 0,
            'last_sequence' => 0,
            'is_enabled' => true,
        ];
    }

    public function seeded(int $lastTakenOutside): static
    {
        return $this->state(['seed_sequence' => $lastTakenOutside]);
    }

    public function disabled(): static
    {
        return $this->state(['is_enabled' => false]);
    }
}
