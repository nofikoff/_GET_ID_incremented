<?php

use App\Domain\Project\ProjectKey;
use App\Domain\Sequence\Exceptions\UnknownProject;
use App\Mcp\Servers\GetIdServer;
use App\Mcp\Tools\NextIdTool;
use App\Models\Identifier;
use App\Models\User;

beforeEach(function () {
    $this->travelTo('2026-09-20 14:30:00');
    $this->user = User::factory()->create();
    $this->pair = enabledPair();

    $this->nextId = fn (array $arguments = []) => GetIdServer::actingAs($this->user)->tool(NextIdTool::class, [
        'project_key' => 'gitlab.cas.ai/team/backend', 'type' => 'ADR', 'name' => 'add-oauth-auth', ...$arguments,
    ]);
});

test('the description says the call is safe to repeat and where the key comes from', function () {
    $tool = (new NextIdTool)->toArray();

    expect($tool['name'])->toBe('next_id')
        ->and($tool['description'])->toContain('Идемпотентно')->toContain('`project_key` берите из ответа `resolve_project`')
        ->and($tool['inputSchema']['required'])->toBe(['project_key', 'type', 'name'])
        ->and($tool['inputSchema']['properties']['project_key']['description'])->toContain('Не угадывайте');
});

test('a first call issues number 1 under the type template, authored by the caller', function () {
    ($this->nextId)()->assertOk()->assertStructuredContent([
        'project_key' => 'gitlab.cas.ai/team/backend',
        'type' => 'ADR',
        'name' => 'add-oauth-auth',
        'sequence_number' => 1,
        'formatted_id' => 'ADR-0001',
        'is_new' => true,
        'created_at' => '2026-09-20T14:30:00Z',
    ]);

    expect(Identifier::query()->sole()->created_by)->toBe($this->user->id);
});

// Principle I: an assistant retrying after a dropped connection gets the same number.
test('a repeat in other wording returns the first number and wording', function () {
    ($this->nextId)()->assertOk();

    ($this->nextId)(['name' => 'Add OAuth Auth', 'project_key' => 'git@gitlab.cas.ai:team/backend.git'])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('sequence_number', 1)
            ->where('name', 'add-oauth-auth')
            ->where('is_new', false)
            ->etc());

    expect(Identifier::query()->count())->toBe(1);
});

// FR-024: the code comes first so a client can branch on it, the rest tells the model what to do.
test('a refusal is led by the REST error code, then names the key and the next step', function () {
    $message = UnknownProject::forKey(ProjectKey::fromOrigin('gitlab.cas.ai/team/sandbox'))->getMessage();

    ($this->nextId)(['project_key' => 'git@gitlab.cas.ai:team/sandbox.git'])
        ->assertHasErrors(["project_not_registered: {$message}"])
        ->assertSee(['gitlab.cas.ai/team/sandbox', 'администратора']);

    expect(Identifier::query()->count())->toBe(0);
});

test('every argument is required', function (string $missing) {
    GetIdServer::actingAs($this->user)
        ->tool(NextIdTool::class, collect(['project_key' => 'gitlab.cas.ai/team/backend', 'type' => 'ADR', 'name' => 'x'])->except($missing)->all())
        ->assertHasErrors([$missing === 'project_key' ? 'project key' : $missing]);

    expect(Identifier::query()->count())->toBe(0);
})->with(['project_key', 'type', 'name']);
