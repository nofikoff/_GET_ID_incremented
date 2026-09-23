<?php

use App\Domain\Sequence\Exceptions\RegistryIsAppendOnly;
use App\Models\Identifier;
use App\Models\KeyType;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $user = User::factory()->create();
    $this->project = new Project(['key' => 'gitlab.cas.ai/team/backend', 'name' => 'Backend', 'repo_url' => 'git@gitlab.cas.ai:team/backend.git']);
    $this->project->creator()->associate($user)->save();
    $keyType = KeyType::create(['code' => 'ADR', 'name' => 'Architecture Decision Record', 'format_template' => 'ADR-{number:04d}']);

    $this->identifier = new Identifier(['name' => 'Add OAuth', 'name_slug' => 'add-oauth', 'sequence_number' => 7, 'formatted_id' => 'ADR-0007']);
    $this->identifier->project()->associate($this->project);
    $this->identifier->keyType()->associate($keyType);
    $this->identifier->save();
});

test('an issued identifier cannot be changed or removed', function (Closure $write) {
    expect(fn () => $write($this->identifier, $this->project))->toThrow(RegistryIsAppendOnly::class);

    expect((array) DB::table('identifiers')->first(['name_slug', 'sequence_number']))
        ->toEqual(['name_slug' => 'add-oauth', 'sequence_number' => 7]);
})->with([
    // A Closure-typed parameter receives dataset closures uninvoked, so each entry is the write itself.
    'update' => fn (Identifier $i) => $i->update(['sequence_number' => 8]),
    'save' => function (Identifier $i) {
        $i->name_slug = 'renamed';
        $i->save();
    },
    'increment' => fn (Identifier $i) => $i->increment('sequence_number'),
    'touch' => fn () => Identifier::query()->touch(),
    'delete' => fn (Identifier $i) => $i->delete(),
    'mass update' => fn () => Identifier::query()->update(['sequence_number' => 8]),
    'mass delete' => fn () => Identifier::query()->delete(),
    'upsert' => fn (Identifier $i) => Identifier::query()->upsert(
        [['project_id' => $i->project_id, 'key_type_id' => $i->key_type_id, 'name' => 'x', 'name_slug' => 'add-oauth', 'sequence_number' => 8]],
        ['project_id', 'key_type_id', 'name_slug'],
        ['sequence_number'],
    ),
    'relation delete' => fn (Identifier $i, Project $p) => $p->identifiers()->delete(),
]);

test('reading and inserting stay open', function () {
    expect(Identifier::query()->where('sequence_number', 7)->exists())->toBeTrue();

    $next = $this->identifier->replicate()->fill(['name_slug' => 'drop-oauth', 'sequence_number' => 8, 'formatted_id' => 'ADR-0008']);
    $next->save();

    expect(Identifier::query()->count())->toBe(2);
});
