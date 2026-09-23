<?php

use App\Domain\Sequence\SequenceIssuer;
use App\Mcp\Servers\GetIdServer;
use App\Mcp\Tools\ListIdentifiersTool;
use App\Models\User;

beforeEach(function () {
    $this->pair = enabledPair(keyType: ['code' => 'spec']);
    $this->list = fn (array $arguments = []) => GetIdServer::actingAs(User::factory()->create())
        ->tool(ListIdentifiersTool::class, ['project_key' => 'gitlab.cas.ai/team/backend', 'type' => 'spec', ...$arguments]);
});

test('the tool is list_identifiers and takes the key and the type', function () {
    $tool = (new ListIdentifiersTool)->toArray();

    expect($tool['name'])->toBe('list_identifiers')
        ->and($tool['description'])->toContain('от новых к старым')
        ->and($tool['inputSchema']['required'])->toBe(['project_key', 'type']);
});

// Spec 004, FR-001: the exact shape, so a formatted name coming back fails here.
test('issued numbers come newest first, each with its first wording and date and no formatted name', function () {
    $issuer = app(SequenceIssuer::class);
    $this->travelTo('2026-09-18 10:00:00');
    $issuer->issue('gitlab.cas.ai/team/backend', 'spec', 'Init Project');
    $this->travelTo('2026-09-19 14:30:00');
    $issuer->issue('gitlab.cas.ai/team/backend', 'spec', 'add-docker-support');
    $issuer->issue('gitlab.cas.ai/team/backend', 'spec', 'init_project');

    ($this->list)()->assertOk()->assertStructuredContent([
        'project_key' => 'gitlab.cas.ai/team/backend',
        'type' => 'spec',
        'items' => [
            ['sequence_number' => 2, 'name' => 'add-docker-support', 'created_at' => '2026-09-19T14:30:00Z'],
            ['sequence_number' => 1, 'name' => 'Init Project', 'created_at' => '2026-09-18T10:00:00Z'],
        ],
    ]);
});

test('a pair with nothing issued yet lists nothing', function () {
    ($this->list)()->assertOk()->assertStructuredContent([
        'project_key' => 'gitlab.cas.ai/team/backend',
        'type' => 'spec',
        'items' => [],
    ]);
});

test('an unregistered project is a tool error led by its REST code', function () {
    ($this->list)(['project_key' => 'gitlab.cas.ai/team/sandbox'])->assertHasErrors(['project_not_registered: ', 'gitlab.cas.ai/team/sandbox']);
});
