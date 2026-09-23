<?php

use App\Models\ApiLog;
use App\Models\User;
use Illuminate\Support\Str;

// FR-014, FR-015, Edge Cases: the journal screen lists, filters and pages the request journal; every filter is part
// of the page's own address, so a copied link reproduces the same result.

beforeEach(function () {
    $this->actingAs(User::factory()->admin()->create());
    // The employee <select> always lists every employee (FR-015), so filter assertions read the results table alone.
    $this->rows = fn ($response): string => Str::between($response->getContent(), '<section id="logs">', '</section>');
});

test('entries are listed newest first with every FR-014 field, and parameters unfold on demand', function () {
    $ada = User::factory()->create(['email' => 'ada@cas.ai']);
    $this->travelTo('2026-09-20 09:00');
    ApiLog::factory()->for($ada)->create([
        'token_name' => 'laptop',
        'method' => 'POST',
        'endpoint' => '/api/v1/sequence/next',
        'payload' => ['type' => 'ADR'],
        'status_code' => 200,
        'duration_ms' => 12,
    ]);
    $this->travelTo('2026-09-21 09:00');
    ApiLog::factory()->for($ada)->create([
        'token_name' => 'phone',
        'method' => 'GET',
        'endpoint' => '/mcp',
        'payload' => ['method' => 'tools/call'],
        'status_code' => 200,
        'duration_ms' => 34,
    ]);

    $response = $this->get(route('admin.logs.index'))->assertOk();

    $response->assertSeeInOrder(['2026-09-21 09:00', 'ada@cas.ai', 'phone', 'GET', '/mcp', '34']);
    $response->assertSeeInOrder(['2026-09-20 09:00', 'ada@cas.ai', 'laptop', 'POST', '/api/v1/sequence/next', '12']);
    // {{ }} escapes the payload: it is untrusted request input, not markup this view controls.
    $response->assertSee('&quot;type&quot;: &quot;ADR&quot;', false);
});

test('filtering by one employee shows only their entries', function () {
    $ada = User::factory()->create(['email' => 'ada@cas.ai']);
    $grace = User::factory()->create(['email' => 'grace@cas.ai']);
    ApiLog::factory()->for($ada)->create();
    ApiLog::factory()->for($grace)->create();

    $response = $this->get(route('admin.logs.index', ['user_id' => $ada->id]))->assertOk();

    expect(($this->rows)($response))->toContain('ada@cas.ai')->not->toContain('grace@cas.ai');
});

test('filtering by day includes both boundaries in the service timezone', function () {
    $this->travelTo('2026-09-20 00:00:00');
    ApiLog::factory()->create(['endpoint' => '/api/v1/start-of-day']);
    $this->travelTo('2026-09-20 23:59:59');
    ApiLog::factory()->create(['endpoint' => '/api/v1/end-of-day']);
    $this->travelTo('2026-09-21 00:00:01');
    ApiLog::factory()->create(['endpoint' => '/api/v1/next-day']);

    $this->get(route('admin.logs.index', ['from' => '2026-09-20', 'to' => '2026-09-20']))
        ->assertOk()
        ->assertSee('/api/v1/start-of-day')
        ->assertSee('/api/v1/end-of-day')
        ->assertDontSee('/api/v1/next-day');
});

test('filtering by surface matches the endpoint prefix', function () {
    ApiLog::factory()->create(['endpoint' => '/api/v1/sequence/next']);
    ApiLog::factory()->create(['endpoint' => '/mcp']);

    $this->get(route('admin.logs.index', ['surface' => 'mcp']))
        ->assertOk()
        ->assertSee('/mcp')
        ->assertDontSee('/api/v1/sequence/next');

    $this->get(route('admin.logs.index', ['surface' => 'rest']))
        ->assertOk()
        ->assertSee('/api/v1/sequence/next')
        ->assertDontSee('/mcp');
});

test('filters combine', function () {
    $ada = User::factory()->create(['email' => 'ada@cas.ai']);
    $this->travelTo('2026-09-20 09:00');
    ApiLog::factory()->for($ada)->create(['endpoint' => '/api/v1/sequence/next']);
    ApiLog::factory()->for($ada)->create(['endpoint' => '/mcp']);
    $grace = User::factory()->create(['email' => 'grace@cas.ai']);
    ApiLog::factory()->for($grace)->create(['endpoint' => '/api/v1/sequence/next']);

    $response = $this->get(route('admin.logs.index', ['user_id' => $ada->id, 'surface' => 'rest', 'from' => '2026-09-20', 'to' => '2026-09-20']))
        ->assertOk();

    expect(($this->rows)($response))
        ->toContain('/api/v1/sequence/next')
        ->toContain('ada@cas.ai')
        ->not->toContain('grace@cas.ai');
});

test('pagination links carry the filter', function () {
    $ada = User::factory()->create(['email' => 'ada@cas.ai']);
    ApiLog::factory()->for($ada)->count(60)->create();

    $this->get(route('admin.logs.index', ['user_id' => $ada->id]))
        ->assertOk()
        ->assertSee('user_id='.$ada->id, false);
});

test('a deactivated employee still appears in the filter list', function () {
    User::factory()->create(['email' => 'ada@cas.ai', 'deactivated_at' => now()]);

    $this->get(route('admin.logs.index'))->assertOk()->assertSee('ada@cas.ai');
});

test('a date range with to earlier than from is refused under to', function () {
    $this->from(route('admin.logs.index'))
        ->get(route('admin.logs.index', ['from' => '2026-09-21', 'to' => '2026-09-20']))
        ->assertRedirect(route('admin.logs.index'))
        ->assertSessionHasErrors(['to' => 'Дата «по» не может быть раньше даты «с».']);
});

test('an unknown employee id is refused under user_id', function () {
    $this->from(route('admin.logs.index'))
        ->get(route('admin.logs.index', ['user_id' => 999999]))
        ->assertRedirect(route('admin.logs.index'))
        ->assertSessionHasErrors('user_id');
});

test('an empty result says so explicitly', function () {
    $this->get(route('admin.logs.index', ['surface' => 'mcp']))
        ->assertOk()
        ->assertSee('Записей нет');
});
