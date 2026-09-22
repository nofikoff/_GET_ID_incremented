<?php

use App\Domain\KeyType\DocumentName;
use App\Domain\Sequence\Exceptions\EmptyDocumentName;

test('spellings of one theme share a slug', function (string $name) {
    expect(DocumentName::fromString($name)->slug)->toBe('add-oauth-auth');
})->with([
    'already a slug' => 'add-oauth-auth',
    'title case with spaces' => 'Add OAuth Auth',
    'underscores' => 'add_oauth_auth',
    'dots' => 'add.oauth.auth',
    'slashes' => 'add/oauth/auth',
    'punctuation' => 'Add: OAuth, auth!',
    'repeated separators' => 'add -- oauth __ auth',
    'separators at the edges' => '  -add oauth auth.- ',
    'upper case' => 'ADD OAUTH AUTH',
    'tabs and newlines' => "add\toauth\nauth",
]);

test('letters of any script are kept without transliteration', function (string $name, string $slug) {
    expect(DocumentName::fromString($name)->slug)->toBe($slug);
})->with([
    'cyrillic' => ['Миграция на v2', 'миграция-на-v2'],
    'cyrillic yo is not folded to ye' => ['Ёлка', 'ёлка'],
    'german sharp s stays' => ['Straße', 'straße'],
    'devanagari keeps its combining signs' => ['हिन्दी दस्तावेज़', 'हिन्दी-दस्तावेज़'],
    'digits of other scripts' => ['Раздел ٣', 'раздел-٣'],
]);

test('composed and decomposed forms of a letter share a slug', function () {
    $composed = "Caf\u{00E9}";
    $decomposed = "Cafe\u{0301}";

    expect(DocumentName::fromString($decomposed)->slug)
        ->toBe(DocumentName::fromString($composed)->slug)
        ->toBe("caf\u{00E9}");
});

test('the original spelling is kept alongside the slug', function () {
    $name = DocumentName::fromString('Add OAuth Auth');

    expect($name->original)->toBe('Add OAuth Auth')
        ->and($name->slug)->toBe('add-oauth-auth');
});

test('a name with nothing left after normalization is rejected', function (string $name) {
    expect(fn () => DocumentName::fromString($name))->toThrow(EmptyDocumentName::class);

    try {
        DocumentName::fromString($name);
    } catch (EmptyDocumentName $e) {
        expect($e->errorCode())->toBe('name_empty_after_normalization');
    }
})->with([
    'empty' => '',
    'spaces' => '    ',
    'separators only' => ' -_./ ',
    'punctuation only' => '?!...',
    'emoji only' => "\u{1F680}",
]);
