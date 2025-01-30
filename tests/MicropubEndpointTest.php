<?php

use Pest\Expectation;

/**
 * Test Dataset Provider Functions
 */
function getValidConfig(): array {
    return [
        'mysite' => 'https://example.com/',
        'tokenEndpoint' => 'https://tokens.indieauth.com/token'
    ];
}

function getMockHeaders(bool $withAuth = true): array {
    return $withAuth ? ['Authorization' => 'Bearer valid-token'] : [];
}

function getMockPostData(bool $withH = true, bool $withContent = true): array {
    $data = [];
    if ($withH) $data['h'] = 'entry';
    if ($withContent) $data['content'] = 'Test content';
    return $data;
}

/**
 * Test Cases
 */
beforeEach(function () {
    // Mock getallheaders() if it doesn't exist in the test environment
    if (!function_exists('getallheaders')) {
        function getallheaders() {
            return [];
        }
    }
});

test('constructor properly initializes the endpoint', function () {
    $config = getValidConfig();
    $headers = getMockHeaders();
    $postData = getMockPostData();

    $endpoint = new MicropubEndpoint(
        $config['mysite'],
        $config['tokenEndpoint'],
        $headers,
        $postData
    );

    expect($endpoint)->toBeInstanceOf(MicropubEndpoint::class);
});

test('endpoint fails without authorization header', function () {
    $config = getValidConfig();
    $headers = getMockHeaders(false);
    $postData = getMockPostData();

    $endpoint = new MicropubEndpoint(
        $config['mysite'],
        $config['tokenEndpoint'],
        $headers,
        $postData
    );

    expect(fn() => $endpoint->process())
        ->toThrow(RuntimeException::class, 'Missing "Authorization" header.')
        ->throws()
        ->getCode()->toBe(401);
});

test('endpoint fails without h parameter', function () {
    $config = getValidConfig();
    $headers = getMockHeaders();
    $postData = getMockPostData(false);

    $endpoint = new MicropubEndpoint(
        $config['mysite'],
        $config['tokenEndpoint'],
        $headers,
        $postData
    );

    expect(fn() => $endpoint->process())
        ->toThrow(RuntimeException::class, 'Missing "h" value.')
        ->throws()
        ->getCode()->toBe(400);
});

test('endpoint fails without content', function () {
    $config = getValidConfig();
    $headers = getMockHeaders();
    $postData = getMockPostData(true, false);

    $endpoint = new MicropubEndpoint(
        $config['mysite'],
        $config['tokenEndpoint'],
        $headers,
        $postData
    );

    expect(fn() => $endpoint->process())
        ->toThrow(RuntimeException::class, 'Missing "content" value.')
        ->throws()
        ->getCode()->toBe(400);
});

test('endpoint normalizes URLs properly', function () {
    $config = getValidConfig();
    $headers = getMockHeaders();
    $postData = getMockPostData();

    // Test with trailing slash
    $endpoint1 = new MicropubEndpoint(
        'https://example.com/',
        $config['tokenEndpoint'],
        $headers,
        $postData
    );

    // Test without trailing slash
    $endpoint2 = new MicropubEndpoint(
        'https://example.com',
        $config['tokenEndpoint'],
        $headers,
        $postData
    );

    // Both should result in the same behavior
    expect(fn() => $endpoint1->process())->toThrow(RuntimeException::class);
    expect(fn() => $endpoint2->process())->toThrow(RuntimeException::class);
});

test('token validation is performed', function () {
    $config = getValidConfig();
    $headers = getMockHeaders();
    $postData = getMockPostData();

    // Mock curl_init and related functions
    if (!function_exists('curl_init')) {
        function curl_init() { return true; }
        function curl_setopt_array($ch, $options) { return true; }
        function curl_exec($ch) { 
            return http_build_query([
                'me' => 'https://example.com/',
                'scope' => 'post'
            ]); 
        }
        function curl_getinfo($ch, $opt = null) { return 200; }
        function curl_close($ch) { return true; }
    }

    $endpoint = new MicropubEndpoint(
        $config['mysite'],
        $config['tokenEndpoint'],
        $headers,
        $postData
    );

    // Should not throw an exception for valid token data
    expect(fn() => $endpoint->process())->not->toThrow(RuntimeException::class);
});

test('mismatching me value is rejected', function () {
    $config = getValidConfig();
    $headers = getMockHeaders();
    $postData = getMockPostData();

    // Mock curl functions to return different 'me' value
    if (!function_exists('curl_init')) {
        function curl_init() { return true; }
        function curl_setopt_array($ch, $options) { return true; }
        function curl_exec($ch) { 
            return http_build_query([
                'me' => 'https://different-site.com/',
                'scope' => 'post'
            ]); 
        }
        function curl_getinfo($ch, $opt = null) { return 200; }
        function curl_close($ch) { return true; }
    }

    $endpoint = new MicropubEndpoint(
        $config['mysite'],
        $config['tokenEndpoint'],
        $headers,
        $postData
    );

    expect(fn() => $endpoint->process())
        ->toThrow(RuntimeException::class, 'Mismatching "me" value in authentication token.')
        ->throws()
        ->getCode()->toBe(403);
});

test('missing post scope is rejected', function () {
    $config = getValidConfig();
    $headers = getMockHeaders();
    $postData = getMockPostData();

    // Mock curl functions to return scope without 'post'
    if (!function_exists('curl_init')) {
        function curl_init() { return true; }
        function curl_setopt_array($ch, $options) { return true; }
        function curl_exec($ch) { 
            return http_build_query([
                'me' => 'https://example.com/',
                'scope' => 'read'
            ]); 
        }
        function curl_getinfo($ch, $opt = null) { return 200; }
        function curl_close($ch) { return true; }
    }

    $endpoint = new MicropubEndpoint(
        $config['mysite'],
        $config['tokenEndpoint'],
        $headers,
        $postData
    );

    expect(fn() => $endpoint->process())
        ->toThrow(RuntimeException::class, 'Missing "post" value in "scope".')
        ->throws()
        ->getCode()->toBe(403);
});

// Helper test to ensure URL normalization works correctly
test('URL normalization works correctly', function () {
    $urls = [
        'https://example.com' => 'https://example.com/',
        'https://example.com/' => 'https://example.com/',
        'https://example.com/path' => 'https://example.com/path/',
        'https://example.com/path/' => 'https://example.com/path/'
    ];

    $reflection = new ReflectionClass(MicropubEndpoint::class);
    $method = $reflection->getMethod('normalizeUrl');
    $method->setAccessible(true);

    $endpoint = new MicropubEndpoint(
        'https://example.com/',
        'https://tokens.indieauth.com/token',
        [],
        []
    );

    foreach ($urls as $input => $expected) {
        expect($method->invoke($endpoint, $input))->toBe($expected);
    }
});