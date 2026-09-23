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

test('the help link is offered to every signed-in employee, not only administrators', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('tokens.index'))->assertSee(route('help'));
});
