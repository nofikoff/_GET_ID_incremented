<?php

use App\Models\User;

// FR-020: any signed-in employee can read how to connect Claude Code to the MCP server.

test('a guest is sent to sign in instead of the help page', function () {
    $this->get(route('help'))->assertRedirect(route('login'));
});

test('a member sees the connect command with the app\'s MCP URL and --scope user', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('help'))
        ->assertOk()
        ->assertSee('claude mcp add --scope user --transport http get-id '.url('/mcp'), false)
        ->assertSee('claude mcp list', false)
        ->assertSee(route('tokens.index'))
        ->assertSee('resolve_project')
        ->assertSee('next_id')
        ->assertSee('list_identifiers');
});

test('the page gives a CLAUDE.md block that routes ADR and spec numbering through get-id', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('help'))
        ->assertOk()
        ->assertSee('## Document numbers (ADR, specs)', false)
        ->assertSee('never derived from listing `docs/adr/` or `specs/`', false)
        ->assertSee('create-new-feature.sh --number &lt;sequence_number&gt; --short-name &lt;slug&gt;', false)
        ->assertSee('register the project at '.route('admin.projects.index'), false)
        ->assertSee('released only by a get-id admin', false)
        ->assertSee('Only the last issued number of the type in the project can be deleted', false);
});

// Spec 004, FR-004: the document name is the repository's convention, so the block states it instead of a service field.
test('the CLAUDE.md block has the repository build the name from the bare number, zero-padded to three digits', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('help'))
        ->assertOk()
        ->assertSee('The service issues only the number (`sequence_number`), never a document name', false)
        ->assertSee('Zero-pad it to three digits', false)
        ->assertSee('`docs/adr/adr-043-&lt;slug&gt;.md`, `specs/043-&lt;slug&gt;/`', false)
        ->assertSee('if the created directory does not start with the issued three-digit number, stop', false)
        ->assertDontSee('formatted_id', false);
});

test('the help link is offered to every signed-in employee, not only administrators', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('tokens.index'))->assertSee(route('help'));
});
