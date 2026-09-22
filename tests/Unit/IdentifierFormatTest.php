<?php

use App\Domain\KeyType\DocumentName;
use App\Domain\KeyType\IdentifierFormat;
use App\Domain\KeyType\InvalidFormatTemplate;

test('a template renders the number and the name slug', function (string $template, int $number, string $expected) {
    $name = DocumentName::fromString('Add OAuth Auth');

    expect(IdentifierFormat::parse($template)->format($number, $name))->toBe($expected);
})->with([
    'bare number' => ['{number}', 12, '12'],
    'zero padded' => ['ADR-{number:04d}', 12, 'ADR-0012'],
    'spec style' => ['{number:03d}-{name}', 7, '007-add-oauth-auth'],
    'name before number' => ['{name}-{number:02d}', 3, 'add-oauth-auth-03'],
    'number twice' => ['{number}/{number:03d}', 5, '5/005'],
    'wide padding' => ['X{number:010d}', 42, 'X0000000042'],
    'literal text with percent signs is printed as is' => ['%s-%d-{number}', 1, '%s-%d-1'],
]);

test('a number wider than the template width is printed in full', function () {
    expect(IdentifierFormat::parse('ADR-{number:04d}')->format(123456, DocumentName::fromString('x')))
        ->toBe('ADR-123456');
});

test('the name placeholder takes the slug, never the original spelling', function () {
    expect(IdentifierFormat::parse('{number:03d}-{name}')->format(1, DocumentName::fromString('Миграция на v2')))
        ->toBe('001-миграция-на-v2');
});

test('the template is kept as written', function () {
    expect(IdentifierFormat::parse('ADR-{number:04d}')->template)->toBe('ADR-{number:04d}');
});

test('a template is rejected when saved, not when first used', function (string $template, string $reason) {
    expect(fn () => IdentifierFormat::parse($template))->toThrow(InvalidFormatTemplate::class, $reason);
})->with([
    'no number' => ['{name}', 'не содержит номера'],
    'empty' => ['', 'не содержит номера'],
    'literal only' => ['ADR', 'не содержит номера'],
    'unknown placeholder' => ['ADR-{number}-{date}', '{date}'],
    'misspelled number' => ['ADR-{num:04d}', '{num:04d}'],
    'space padding is not a width' => ['ADR-{number:4d}', '{number:4d}'],
    'zero width' => ['ADR-{number:00d}', '{number:00d}'],
    'placeholders are case sensitive' => ['{NUMBER}', '{NUMBER}'],
    'unclosed brace' => ['ADR-{number', 'непарную фигурную скобку'],
    'stray closing brace' => ['ADR-{number}}', 'непарную фигурную скобку'],
]);

test('a number below one is refused', function () {
    IdentifierFormat::parse('{number}')->format(0, DocumentName::fromString('x'));
})->throws(InvalidArgumentException::class);
