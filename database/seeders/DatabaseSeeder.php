<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seeds no users: the first one comes from a Google sign-in listed in ADMIN_EMAILS.
     */
    public function run(): void
    {
        $this->call(KeyTypeSeeder::class);
    }
}
