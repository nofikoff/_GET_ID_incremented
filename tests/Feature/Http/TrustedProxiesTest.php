<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::get('_proxy-probe', fn (Request $request) => [
        'secure' => $request->isSecure(),
        'url' => url('/tokens'),
        'host' => $request->getHost(),
        'ip' => $request->ip(),
    ]);
});

// Behind Cloudflare the app must build https:// links, or the Google redirect URI stops matching.
test('the forwarded scheme is trusted', function () {
    $response = $this->withHeaders(['X-Forwarded-Proto' => 'https'])->get('_proxy-probe');

    expect($response->json('secure'))->toBeTrue()
        ->and($response->json('url'))->toStartWith('https://');
});

test('forwarded host and client address are ignored', function () {
    $response = $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
        ->withHeaders([
            'X-Forwarded-Proto' => 'https',
            'X-Forwarded-Host' => 'evil.example',
            'X-Forwarded-For' => '198.51.100.7',
        ])
        ->get('_proxy-probe');

    expect($response->json('host'))->toBe('localhost')
        ->and($response->json('url'))->not->toContain('evil.example')
        ->and($response->json('ip'))->toBe('203.0.113.10');
});

test('without the header the scheme stays plain http', function () {
    expect($this->get('_proxy-probe')->json('secure'))->toBeFalse();
});
