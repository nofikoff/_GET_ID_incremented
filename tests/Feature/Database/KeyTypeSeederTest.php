<?php

use App\Models\KeyType;
use Database\Seeders\KeyTypeSeeder;

test('the seeder provides the ADR and spec key types', function () {
    $this->seed(KeyTypeSeeder::class);

    expect(KeyType::query()->orderBy('code')->pluck('name', 'code')->all())
        ->toBe(['ADR' => 'Architecture Decision Record', 'spec' => 'Specification']);
});

test('seeding again adds nothing and keeps what an administrator changed', function () {
    $this->seed(KeyTypeSeeder::class);
    KeyType::query()->where('code', 'ADR')->sole()->update(['name' => 'Decision', 'is_active' => false]);

    $this->seed(KeyTypeSeeder::class);

    $adr = KeyType::query()->where('code', 'ADR')->sole();
    expect(KeyType::query()->count())->toBe(2)
        ->and($adr->name)->toBe('Decision')
        ->and($adr->is_active)->toBeFalse();
});

test('the database seeder runs it', function () {
    $this->seed();

    expect(KeyType::query()->pluck('code')->sort()->values()->all())->toBe(['ADR', 'spec']);
});
