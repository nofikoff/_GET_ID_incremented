<?php

use App\Models\User;
use Illuminate\Routing\Route as RouteDefinition;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;

// The model layer and the refusing rollback are pinned elsewhere: every Eloquent write path in
// tests/Feature/Models/IdentifierImmutabilityTest.php, down() over issued numbers in
// tests/Feature/Database/RegistrySchemaTest.php. This file covers the API surface and the empty rollback.

test('issuing and repeating through the API never rewrites an issued row', function () {
    Sanctum::actingAs(User::factory()->create());
    enabledPair();
    $next = fn (string $name) => $this->postJson(
        'api/v1/sequence/next',
        ['project_key' => 'gitlab.cas.ai/team/backend', 'type' => 'ADR', 'name' => $name],
    )->assertOk();

    $this->travelTo('2026-09-20 14:30:00');
    $next('Add OAuth Auth');
    $next('init-project');
    $issued = DB::table('identifiers')->orderBy('id')->get()->all();

    $this->travelTo('2026-09-21 09:00:00');
    $next('add_oauth_auth');
    $next('INIT PROJECT');
    $next('add-docker');

    expect(DB::table('identifiers')->orderBy('id')->limit(2)->get()->all())->toEqual($issued);
});

test('the sequence endpoints only read and issue', function () {
    $methods = collect(Route::getRoutes()->getRoutes())
        ->filter(fn (RouteDefinition $route) => str_starts_with($route->uri(), 'api/v1/sequence/'))
        ->flatMap(fn (RouteDefinition $route) => $route->methods())
        ->unique()->sort()->values()->all();

    expect($methods)->toBe(['GET', 'HEAD', 'POST']);
});

// DDL commits implicitly in MySQL, ending this test's RefreshDatabase transaction, so nothing is written
// before it and the table is restored in `finally` for the tests that follow.
test('the registry migration drops an empty registry', function () {
    $migration = require database_path('migrations/2026_09_23_100003_create_identifiers_table.php');

    try {
        $migration->down();

        expect(Schema::hasTable('identifiers'))->toBeFalse();
    } finally {
        if (! Schema::hasTable('identifiers')) {
            $migration->up();
        }
    }

    expect(Schema::hasTable('identifiers'))->toBeTrue();
});
