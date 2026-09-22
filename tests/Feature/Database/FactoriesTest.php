<?php

use App\Domain\KeyType\IdentifierFormat;
use App\Models\Identifier;
use App\Models\KeyType;
use App\Models\Project;
use App\Models\ProjectKeyType;

test('a project key is derived from the repository url it is given', function () {
    $project = Project::factory()->create(['repo_url' => 'git@gitlab.cas.ai:team/backend.git']);

    expect($project->key)->toBe('gitlab.cas.ai/team/backend')
        ->and($project->is_active)->toBeTrue()
        ->and($project->creator)->not->toBeNull()
        ->and(Project::factory()->inactive()->create()->is_active)->toBeFalse();
});

test('a key type comes with a valid format template', function () {
    $type = KeyType::factory()->create();

    expect(IdentifierFormat::parse($type->format_template)->template)->toBe($type->format_template)
        ->and(KeyType::factory()->inactive()->create()->is_active)->toBeFalse();
});

test('a counter row links a project to a key type', function () {
    $counter = ProjectKeyType::factory()->seeded(42)->create();

    expect($counter->project->keyTypes()->sole()->is($counter->keyType))->toBeTrue()
        ->and($counter->nextSequence())->toBe(43)
        ->and($counter->is_enabled)->toBeTrue()
        ->and(ProjectKeyType::factory()->disabled()->create()->is_enabled)->toBeFalse();
});

test('an identifier slug is derived from its name', function () {
    $identifier = Identifier::factory()->create(['name' => 'Add OAuth Auth']);

    expect($identifier->name_slug)->toBe('add-oauth-auth')
        ->and($identifier->sequence_number)->toBeGreaterThanOrEqual(1)
        ->and($identifier->creator)->not->toBeNull();
});
