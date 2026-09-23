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
        ->assertSee('create-new-feature.sh --number', false)
        ->assertSee('register the project at '.url('/admin'), false);
});

test('the help link is offered to every signed-in employee, not only administrators', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('tokens.index'))->assertSee(route('help'));
});
