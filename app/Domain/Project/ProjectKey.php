<?php

namespace App\Domain\Project;

use App\Domain\Sequence\Exceptions\UnparsableOrigin;
use Stringable;

/**
 * The project key a repository origin normalizes to: `host/path`, lower case, with scheme, user,
 * port and `.git` removed (FR-008). A key is itself a valid input and maps to itself (FR-008a),
 * so clients may send either the origin or a key they stored earlier.
 */
final readonly class ProjectKey implements Stringable
{
    private const SCHEMES = ['ssh', 'git', 'http', 'https', 'git+ssh', 'ssh+git'];

    private const HOST = '/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?(?:\.[a-z0-9](?:[a-z0-9-]*[a-z0-9])?)*$/';

    private const SEGMENT = '/^[\p{L}\p{N}._~-]+$/u';

    private function __construct(public string $value) {}

    /**
     * @throws UnparsableOrigin
     */
    public static function fromOrigin(string $origin): self
    {
        $input = trim($origin);

        if ($input === '') {
            throw UnparsableOrigin::because($origin, 'пустая строка');
        }

        [$host, $path] = self::split($origin, $input);

        $host = strtolower($host);
        if ($host === '') {
            throw UnparsableOrigin::because($origin, 'нет хоста');
        }
        if (preg_match(self::HOST, $host) !== 1) {
            throw UnparsableOrigin::because($origin, "недопустимый хост «{$host}»");
        }

        return new self($host.'/'.implode('/', self::segments($origin, $path)));
    }

    public function __toString(): string
    {
        return $this->value;
    }

    /**
     * @return array{string, string} host with user and port removed, and the raw path
     */
    private static function split(string $origin, string $input): array
    {
        if (preg_match('~^([a-z][a-z0-9+.-]*)://(.*)$~i', $input, $url) === 1) {
            $scheme = strtolower($url[1]);
            if (! in_array($scheme, self::SCHEMES, true)) {
                throw UnparsableOrigin::because($origin, "схема «{$scheme}» не поддерживается");
            }

            [$authority, $path] = array_pad(explode('/', $url[2], 2), 2, '');
            $at = strrpos($authority, '@');
            $hostAndPort = $at === false ? $authority : substr($authority, $at + 1);

            return [preg_replace('/:\d*$/', '', $hostAndPort) ?? $hostAndPort, $path];
        }

        // git's own rule: a colon before the first slash makes it scp syntax, `[user@]host:path`, with no port.
        if (preg_match('~^(?:[^@/]+@)?([^/:]+):(.*)$~', $input, $scp) === 1) {
            return [$scp[1], $scp[2]];
        }

        return array_pad(explode('/', $input, 2), 2, '');
    }

    /**
     * @return non-empty-list<string>
     */
    private static function segments(string $origin, string $path): array
    {
        $segments = array_values(array_filter(
            explode('/', mb_strtolower($path, 'UTF-8')),
            static fn (string $segment): bool => $segment !== '',
        ));

        if ($segments !== []) {
            $last = array_key_last($segments);
            $segments[$last] = preg_replace('/\.git$/', '', $segments[$last]) ?? $segments[$last];
        }

        if ($segments === [] || end($segments) === '') {
            throw UnparsableOrigin::because($origin, 'нет пути к репозиторию');
        }

        foreach ($segments as $segment) {
            if ($segment === '.' || $segment === '..' || preg_match(self::SEGMENT, $segment) !== 1) {
                throw UnparsableOrigin::because($origin, "недопустимый сегмент пути «{$segment}»");
            }
        }

        return $segments;
    }
}
