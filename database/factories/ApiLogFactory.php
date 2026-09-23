<?php

namespace Database\Factories;

use App\Models\ApiLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApiLog>
 */
class ApiLogFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'token_name' => 'laptop',
            'method' => 'POST',
            'endpoint' => '/api/v1/sequence/next',
            'payload' => ['project_key' => 'gitlab.cas.ai/team/backend', 'type' => 'ADR'],
            'status_code' => 200,
            'duration_ms' => fake()->numberBetween(1, 50),
        ];
    }
}
