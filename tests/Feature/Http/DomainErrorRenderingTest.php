<?php

use App\Domain\KeyType\DocumentName;
use App\Domain\Project\ProjectKey;
use App\Domain\Sequence\Exceptions\InactiveKeyType;
use App\Domain\Sequence\Exceptions\InactiveProject;
use App\Domain\Sequence\Exceptions\TypeNotEnabled;
use App\Domain\Sequence\Exceptions\UnknownProject;
use App\Models\Project;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;

// Plain get/post, not getJson/postJson: clients such as curl send no Accept header, and the shape must not depend on it.

test('a domain rejection renders as a 422 DomainError carrying its code and context', function (Closure $reject, array $error) {
    Sanctum::actingAs(User::factory()->create());
    Route::middleware('api')->post('api/v1/_probe', fn () => throw $reject());

    $response = $this->post('api/v1/_probe')->assertStatus(422)->assertHeader('Content-Type', 'application/json');

    expect($response->json('error'))->toBe($error)
        ->and($response->json('message'))->toBeString()->not->toBeEmpty();
})->with([
    'unknown project' => [
        fn () => UnknownProject::forKey(ProjectKey::fromOrigin('git@gitlab.cas.ai:team/sandbox.git')),
        ['code' => 'project_not_registered', 'project_key' => 'gitlab.cas.ai/team/sandbox'],
    ],
    'inactive project' => [
        fn () => InactiveProject::forKey(ProjectKey::fromOrigin('gitlab.cas.ai/team/old')),
        ['code' => 'project_inactive', 'project_key' => 'gitlab.cas.ai/team/old'],
    ],
    'type not enabled' => [
        fn () => TypeNotEnabled::inProject(ProjectKey::fromOrigin('gitlab.cas.ai/team/backend'), 'ADR'),
        ['code' => 'type_not_enabled', 'project_key' => 'gitlab.cas.ai/team/backend', 'type' => 'ADR'],
    ],
    'inactive key type' => [
        fn () => InactiveKeyType::inProject(ProjectKey::fromOrigin('gitlab.cas.ai/team/backend'), 'RFC'),
        ['code' => 'type_inactive', 'project_key' => 'gitlab.cas.ai/team/backend', 'type' => 'RFC'],
    ],
    'unparsable origin' => [
        fn () => ProjectKey::fromOrigin('not a url'),
        ['code' => 'origin_unparsable'],
    ],
    'empty document name' => [
        fn () => DocumentName::fromString(' -- '),
        ['code' => 'name_empty_after_normalization'],
    ],
]);

test('the unknown project message names the key and the next step', function () {
    Sanctum::actingAs(User::factory()->create());
    Route::middleware('api')->post('api/v1/_probe', fn () => throw UnknownProject::forKey(ProjectKey::fromOrigin('gitlab.cas.ai/team/sandbox')));

    expect($this->post('api/v1/_probe')->json('message'))
        ->toContain('gitlab.cas.ai/team/sandbox')
        ->toContain('администратор');
});

test('a request without a token gets an unauthenticated DomainError', function (string $uri) {
    Route::middleware('api')->post($uri, fn () => ['ok' => true]);

    $this->post($uri)
        ->assertStatus(401)
        ->assertJsonPath('error.code', 'unauthenticated')
        ->assertJsonStructure(['message', 'error' => ['code']]);
})->with(['rest' => 'api/v1/_probe', 'mcp' => 'mcp']);

test('a denied action gets a forbidden DomainError', function (Closure $deny) {
    Sanctum::actingAs(User::factory()->create());
    Route::middleware('api')->post('api/v1/_probe', $deny);

    $this->post('api/v1/_probe')->assertStatus(403)->assertJsonPath('error.code', 'forbidden');
})->with([
    'policy denial' => fn () => throw new AuthorizationException,
    'abort' => fn () => abort(403),
]);

test('a missing route or record gets a not_found DomainError that does not name the model', function (string $method, string $uri) {
    Sanctum::actingAs(User::factory()->create());
    Route::middleware('api')->get('api/v1/_probe/{id}', fn (string $id) => Project::query()->findOrFail($id));

    $response = $this->call($method, $uri)->assertStatus(404)->assertJsonPath('error.code', 'not_found');

    expect($response->json('message'))->not->toContain('App\\Models');
})->with([
    'unknown route' => ['GET', 'api/v1/nothing-here'],
    'unknown record' => ['GET', 'api/v1/_probe/999'],
]);

test('validation failures on the API keep the ValidationError shape without an Accept header', function () {
    Sanctum::actingAs(User::factory()->create());
    Route::middleware('api')->post('api/v1/_probe', fn (Request $request) => $request->validate(['name' => 'required']));

    $this->post('api/v1/_probe')
        ->assertStatus(422)
        ->assertJsonStructure(['message', 'errors' => ['name']]);
});

test('web pages keep the framework rendering', function () {
    $this->get('/definitely-not-a-page')
        ->assertNotFound()
        ->assertHeader('Content-Type', 'text/html; charset=utf-8');
});
