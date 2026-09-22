<?php

namespace Database\Factories;

use App\Domain\Project\ProjectKey;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'repo_url' => sprintf('git@gitlab.cas.ai:%s/%s.git', fake()->unique()->slug(2), fake()->slug(1)),
            // A closure, so a repo_url override in a test still yields the matching key.
            'key' => fn (array $attributes): string => ProjectKey::fromOrigin($attributes['repo_url'])->value,
            'name' => fake()->words(2, true),
            'description' => null,
            'is_active' => true,
            'created_by' => User::factory(),
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
