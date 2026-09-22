<?php

use App\Domain\KeyType\IdentifierFormat;
use App\Models\KeyType;
use Database\Seeders\KeyTypeSeeder;

test('the seeder provides the ADR and spec key types', function () {
    $this->seed(KeyTypeSeeder::class);

    expect(KeyType::query()->orderBy('code')->pluck('format_template', 'code')->all())
        ->toBe(['ADR' => 'ADR-{number:04d}', 'spec' => '{number:03d}-{name}']);

    KeyType::all()->each(fn (KeyType $type) => IdentifierFormat::parse($type->format_template));
});

test('seeding again adds nothing and keeps what an administrator changed', function () {
    $this->seed(KeyTypeSeeder::class);
    KeyType::query()->where('code', 'ADR')->sole()->update(['format_template' => 'ADR{number:03d}', 'is_active' => false]);

    $this->seed(KeyTypeSeeder::class);

    $adr = KeyType::query()->where('code', 'ADR')->sole();
    expect(KeyType::query()->count())->toBe(2)
        ->and($adr->format_template)->toBe('ADR{number:03d}')
        ->and($adr->is_active)->toBeFalse();
});

test('the database seeder runs it', function () {
    $this->seed();

    expect(KeyType::query()->pluck('code')->sort()->values()->all())->toBe(['ADR', 'spec']);
});
