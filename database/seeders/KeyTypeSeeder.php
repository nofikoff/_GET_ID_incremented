<?php

namespace Database\Seeders;

use App\Models\KeyType;
use Illuminate\Database\Seeder;

class KeyTypeSeeder extends Seeder
{
    /**
     * Runs on every `make migrate`, so it only creates what is missing and never overwrites an
     * administrator's later edits.
     */
    public function run(): void
    {
        KeyType::query()->firstOrCreate(
            ['code' => 'ADR'],
            ['name' => 'Architecture Decision Record'],
        );

        KeyType::query()->firstOrCreate(
            ['code' => 'spec'],
            ['name' => 'Specification'],
        );
    }
}
