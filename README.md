# Laravel Msgpack

<p align="center">
  <strong>MessagePack content negotiation for Laravel APIs.</strong><br>
  Keep JSON as the default. Let capable clients opt into compact binary responses without changing controllers.
</p>

<p align="center">
  <a href="https://packagist.org/packages/smmehdisharifi/laravel-msgpack"><img src="https://img.shields.io/packagist/v/smmehdisharifi/laravel-msgpack.svg?style=flat-square" alt="Latest Version on Packagist"></a>
  <a href="https://packagist.org/packages/smmehdisharifi/laravel-msgpack"><img src="https://img.shields.io/packagist/dt/smmehdisharifi/laravel-msgpack.svg?style=flat-square" alt="Total Downloads"></a>
  <a href="https://github.com/smmehdisharifi/laravel-msgpack/actions/workflows/run-tests.yml"><img src="https://github.com/smmehdisharifi/laravel-msgpack/actions/workflows/run-tests.yml/badge.svg" alt="Tests"></a>
  <a href="https://packagist.org/packages/smmehdisharifi/laravel-msgpack"><img src="https://img.shields.io/packagist/php-v/smmehdisharifi/laravel-msgpack.svg?style=flat-square" alt="PHP Version"></a>
  <a href="https://github.com/smmehdisharifi/laravel-msgpack/stargazers"><img src="https://img.shields.io/github/stars/smmehdisharifi/laravel-msgpack?style=flat-square" alt="GitHub Stars"></a>
  <a href="https://github.com/smmehdisharifi/laravel-msgpack/blob/master/LICENSE"><img src="https://img.shields.io/github/license/smmehdisharifi/laravel-msgpack.svg?style=flat-square" alt="License"></a>
</p>

Laravel Msgpack gives Laravel APIs an opt-in binary representation while preserving JSON for existing clients. Add the middleware to a route or route group, and let each client choose its representation with the `Accept` header.

## Why this package?

MessagePack is a compact, schema-free binary format that can reduce wire size for suitable payloads. It is useful for high-volume APIs, mobile clients, internal services, and bandwidth-constrained applications.

This package is designed for incremental adoption:

- Existing clients continue to receive JSON.
- MessagePack clients opt in with `Accept: application/msgpack`.
- Responses include `Vary: Accept` for correct HTTP caching.
- Requests support both `application/msgpack` and the legacy `application/x-msgpack` media type.
- Errors follow the same negotiation, including unmatched routes and route exceptions.
- The benchmark helps you measure your own payloads instead of relying on universal performance claims.

## Features

- `Accept`-based response negotiation with JSON fallback
- Wildcard and quality-factor handling with `406 Not Acceptable` when every supported format is rejected
- Request body decoding for MessagePack content types with parameters
- `response()->msgpack()` with status and custom header support
- `request()->msgpack()` access to the original decoded payload
- Safe `400` responses for invalid MessagePack payloads
- Configurable request payload limit with `413` responses
- Configurable nesting-depth and value-count limits for decoded payloads
- Safe refusal of streamed, encoded, and non-JSON responses that cannot be represented as MessagePack
- Built-in Artisan benchmark for raw and gzip-compressed JSON/MessagePack payloads
- Laravel service provider and middleware auto-discovery

## Compatibility

- PHP 8.1 or newer
- Laravel 9.x through 12.x
- `rybakit/msgpack` 0.7 and 0.10
- Optional `ext-zlib` support for gzip benchmark metrics

## Installation

```bash
composer require smmehdisharifi/laravel-msgpack
```

## Quick Start

Add the middleware to an API route or route group:

```php
use Illuminate\Support\Facades\Route;

Route::middleware('msgpack')->get('/api/profile', function () {
    return [
        'name' => 'Laravel',
        'format' => 'negotiated',
    ];
});
```

No controller changes are required. Clients continue to receive JSON by default:

```bash
curl -i http://localhost/api/profile
```

A MessagePack-aware client opts into a binary response:

```bash
curl -i -H 'Accept: application/msgpack' http://localhost/api/profile
```

The negotiated response includes:

```http
Content-Type: application/msgpack
Vary: Accept
```

If both formats are accepted, the higher `q` value wins and media-type wildcards are evaluated by specificity. The package returns `406 Not Acceptable` when the client explicitly rejects every supported format.

The configured `content_type` is accepted as an explicit MessagePack response format. Additional entries in `accept_content_types`, such as the legacy `application/x-msgpack` type, are preserved when selected. The response macro always uses the configured `content_type`.

## Request Decoding

MessagePack request bodies are decoded automatically:

```php
use Illuminate\Http\Request;

Route::middleware('msgpack')->post('/api/profile', function (Request $request) {
    return [
        'received' => $request->msgpack(),
        'name' => $request->input('name'),
    ];
});
```

For map payloads, decoded values are also merged into Laravel's normal request input. The original value remains available through `request()->msgpack()`, including scalar and list payloads.

Invalid payloads do not reach the route handler. The middleware returns `400`, or `413` when the configured payload limit is exceeded. A body containing more than one MessagePack value is rejected; the package accepts one complete value per request.

## Explicit Responses

Use the response macro when a route should always return MessagePack:

```php
return response()->msgpack([
    'message' => 'Created',
], 201, [
    'X-Request-Id' => $requestId,
]);
```

The macro follows the familiar Laravel response shape:

```php
response()->msgpack($value, $status = 200, $headers = []);
```

## Encode and Decode

The facade is available for direct serialization:

```php
use Msgpack;

$data = ['name' => 'Laravel', 'type' => 'framework'];

$packed = Msgpack::encode($data);
$unpacked = Msgpack::decode($packed);
```

## Benchmark

Run the built-in benchmark from a Laravel application's console:

```bash
php artisan msgpack:benchmark
```

The command uses a deterministic API-shaped fixture and measures payload size,
size reduction, encode time, and decode time. It also reports gzip size and
compression timings when `ext-zlib` is available. JSON uses the default
`json_encode` options used by Laravel's JSON response factory. Each operation
is warmed up once before timing, and reported timings are per iteration.

Useful options:

```bash
php artisan msgpack:benchmark --iterations=5000
php artisan msgpack:benchmark --iterations=5000 --json
```

The `--json` option is useful for CI or for recording results over time. An
indicative table result looks like this; timings depend on the PHP version and host:

```text
MessagePack benchmark
Fixture: deterministic_api_payload
Iterations: 1000

Payload size       JSON       MessagePack   Reduction
                   591 B      435 B         26.40 %
Encode time        0.001 ms   0.008 ms
Decode time        0.003 ms   0.007 ms

Gzip compression (level 6)
Compressed size    377 B      371 B          1.59 %
Compress time      0.009 ms   0.010 ms
Decompress time    0.004 ms   0.003 ms
```

MessagePack is not automatically faster or smaller for every payload. Use the
benchmark with data representative of your application before choosing it as a
transport format.

## Configuration

Publish the configuration file:

```bash
php artisan vendor:publish --tag=msgpack-config
```

Default configuration:

```php
return [
    'content_type' => 'application/msgpack',

    'request_content_types' => [
        'application/msgpack',
        'application/x-msgpack',
    ],

    'accept_content_types' => [
        'application/msgpack',
        'application/x-msgpack',
    ],

    'max_request_size' => 10 * 1024 * 1024,

    'max_depth' => 64,

    'max_nodes' => 100000,
];
```

Set `max_request_size`, `max_depth`, or `max_nodes` to `0` or `null` to disable that package-level guard. The request-size check reads at most one byte beyond the configured limit and also rejects a larger declared `Content-Length` before decoding. Depth and value-count limits are checked while scanning the MessagePack value before it is materialized. Web-server and Laravel limits may still apply.

Responses selected as MessagePack must be JSON representations or already-encoded MessagePack responses. HTML, streamed responses, and responses with `Content-Encoding` return `406` instead of sending a body in an unexpected format. Representation-specific headers such as `ETag`, `Digest`, and `Content-MD5` are removed when the JSON body is re-encoded.

## JavaScript Client

The package works with standard MessagePack clients such as `@msgpack/msgpack`:

```javascript
import { decode, encode } from '@msgpack/msgpack';

const response = await fetch('/api/profile', {
  headers: { Accept: 'application/msgpack' },
});

const payload = decode(new Uint8Array(await response.arrayBuffer()));

await fetch('/api/profile', {
  method: 'POST',
  headers: {
    Accept: 'application/msgpack',
    'Content-Type': 'application/msgpack',
  },
  body: encode({ name: 'Laravel' }),
});
```

## Middleware Registration

The package registers the `msgpack` middleware alias automatically. If your application needs manual registration, add:

```php
protected $middlewareAliases = [
    'msgpack' => \SmMehdiSharifi\LaravelMsgpack\Middleware\MsgpackMiddleware::class,
];
```

## Testing

```bash
composer install
./vendor/bin/phpunit tests
```

The test suite covers round-trip serialization, response macros, content negotiation, JSON fallback, request decoding, malformed payloads, request limits, status codes, and custom headers.

## Support the Project

If this package helps your API, a GitHub star helps other Laravel developers discover it. Bug reports, feature ideas, documentation improvements, and real-world benchmark results are welcome.

## Contributing

Issues and pull requests are welcome. Please read [CONTRIBUTING.md](CONTRIBUTING.md) before starting work.

New behavior should include integration tests and an update to the HTTP contract documented above. Use the repository's issue forms and pull request template so reports and contributions remain consistent.

Security vulnerabilities must be reported privately according to [SECURITY.md](SECURITY.md).

## License

This package is open-sourced software licensed under the [MIT license](LICENSE).

## Links

- [Packagist](https://packagist.org/packages/smmehdisharifi/laravel-msgpack)
- [GitHub](https://github.com/smmehdisharifi/laravel-msgpack)
- [Discussions](https://github.com/smmehdisharifi/laravel-msgpack/discussions)
- [Contributing](CONTRIBUTING.md)
- [Security policy](SECURITY.md)
- [Code of Conduct](CODE_OF_CONDUCT.md)
