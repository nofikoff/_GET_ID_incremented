<?php

use App\Domain\Project\ProjectKey;
use App\Domain\Sequence\Exceptions\UnparsableOrigin;

test('every form of one origin yields one project key', function (string $origin) {
    expect(ProjectKey::fromOrigin($origin)->value)->toBe('gitlab.cas.ai/team/backend');
})->with([
    'scp' => 'git@gitlab.cas.ai:team/backend.git',
    'scp without user' => 'gitlab.cas.ai:team/backend',
    'https' => 'https://gitlab.cas.ai/team/backend',
    'https with .git' => 'https://gitlab.cas.ai/team/backend.git',
    'http' => 'http://gitlab.cas.ai/team/backend',
    'https with credentials and port' => 'https://oauth2:secret@gitlab.cas.ai:8443/team/backend.git',
    'ssh url with port' => 'ssh://git@gitlab.cas.ai:2222/team/backend.git',
    'git+ssh' => 'git+ssh://git@gitlab.cas.ai/team/backend.git',
    'git protocol' => 'git://gitlab.cas.ai/team/backend.git',
    'trailing slash' => 'https://gitlab.cas.ai/team/backend/',
    'trailing slash after .git' => 'https://gitlab.cas.ai/team/backend.git/',
    'doubled slashes' => 'https://gitlab.cas.ai//team//backend',
    'mixed case' => 'HTTPS://GitLab.CAS.ai/Team/Backend.GIT',
    'surrounding whitespace' => "  git@gitlab.cas.ai:team/backend.git\n",
    'already normalized' => 'gitlab.cas.ai/team/backend',
    'normalized, other case' => 'GitLab.cas.ai/Team/Backend',
]);

test('nested groups and single-segment repositories keep their path', function (string $origin, string $key) {
    expect(ProjectKey::fromOrigin($origin)->value)->toBe($key);
})->with([
    'nested groups' => ['git@gitlab.cas.ai:team/platform/backend.git', 'gitlab.cas.ai/team/platform/backend'],
    'bare repository' => ['git@git.example.com:backend.git', 'git.example.com/backend'],
    'dots, dashes and underscores' => ['https://github.com/org/my_repo.v2-beta', 'github.com/org/my_repo.v2-beta'],
    'ip address host' => ['ssh://git@10.0.0.5:22/team/backend.git', '10.0.0.5/team/backend'],
]);

test('normalizing a key again returns the same key', function (string $origin) {
    $key = ProjectKey::fromOrigin($origin)->value;

    expect(ProjectKey::fromOrigin($key)->value)->toBe($key)
        ->and((string) ProjectKey::fromOrigin($key))->toBe($key);
})->with([
    'git@gitlab.cas.ai:team/backend.git',
    'https://oauth2:secret@gitlab.cas.ai:8443/Team/Platform/Backend.git/',
    'ssh://git@10.0.0.5:22/team/backend.git',
]);

test('a string with no host and repository path is rejected', function (string $origin) {
    expect(fn () => ProjectKey::fromOrigin($origin))
        ->toThrow(UnparsableOrigin::class);

    try {
        ProjectKey::fromOrigin($origin);
    } catch (UnparsableOrigin $e) {
        expect($e->errorCode())->toBe('origin_unparsable')
            ->and($e->getMessage())->toContain(trim($origin));
    }
})->with([
    'empty' => '',
    'blank' => '   ',
    'prose' => 'not a url',
    'host only' => 'gitlab.cas.ai',
    'url without path' => 'https://gitlab.cas.ai',
    'url with bare slash' => 'https://gitlab.cas.ai/',
    'scp without path' => 'git@gitlab.cas.ai:',
    'only .git as path' => 'https://gitlab.cas.ai/team/.git',
    'local absolute path' => '/srv/git/backend.git',
    'relative path' => '../backend',
    'file url' => 'file:///srv/git/backend.git',
    'unsupported scheme' => 'ftp://gitlab.cas.ai/team/backend.git',
    'query string' => 'https://gitlab.cas.ai/team/backend?ref=main',
    'dot-dot segment' => 'https://gitlab.cas.ai/team/../backend',
    'windows path' => 'C:\\repos\\backend',
    'space in host' => 'https://gitlab cas.ai/team/backend',
    'ipv6 literal' => 'ssh://git@[::1]/team/backend.git',
]);

test('the rejection names what could not be parsed', function (string $origin, string $reason) {
    expect(fn () => ProjectKey::fromOrigin($origin))->toThrow(UnparsableOrigin::class, $reason);
})->with([
    'unsupported scheme' => ['ftp://gitlab.cas.ai/team/backend.git', 'схема «ftp» не поддерживается'],
    'missing path' => ['https://gitlab.cas.ai', 'нет пути к репозиторию'],
    'missing host' => ['/srv/git/backend.git', 'нет хоста'],
    'bad segment' => ['https://gitlab.cas.ai/team/backend?ref=main', 'недопустимый сегмент пути «backend?ref=main»'],
]);
