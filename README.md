# Laravel Msgpack

[![Latest Version on Packagist](https://img.shields.io/packagist/v/smmehdisharifi/laravel-msgpack.svg?style=flat-square)](https://packagist.org/packages/smmehdisharifi/laravel-msgpack)
[![Total Downloads](https://img.shields.io/packagist/dt/smmehdisharifi/laravel-msgpack.svg?style=flat-square)](https://packagist.org/packages/smmehdisharifi/laravel-msgpack)
[![Tests](https://github.com/smmehdisharifi/laravel-msgpack/actions/workflows/run-tests.yml/badge.svg)](https://github.com/smmehdisharifi/laravel-msgpack/actions/workflows/run-tests.yml)

Optional MessagePack content negotiation for Laravel APIs.

Keep JSON as the default. Let capable clients opt into compact binary responses with an `Accept` header, without changing your controllers.

## Why MessagePack?

MessagePack is useful for high-volume APIs, mobile clients, internal services, and bandwidth-constrained applications. It can reduce payload size and parsing overhead while keeping a schema-free data model.

The package is deliberately opt-in:

- Existing clients continue to receive JSON.
- MessagePack clients send `Accept: application/msgpack`.
- Responses include `Vary: Accept` for correct HTTP caching.
- Incoming requests accept both `application/msgpack` and the legacy `application/x-msgpack` media type.
- Error responses follow the same negotiation, including unmatched routes and route exceptions.

## Features

- `Accept`-based response negotiation with JSON fallback
- Standard wildcard and quality-factor handling with `406 Not Acceptable` when both formats are rejected
- Request body decoding for MessagePack content types with parameters
- `response()->msgpack()` with status and custom header support
- `request()->msgpack()` access to the original decoded payload
- Safe `400` responses for invalid MessagePack payloads
- Configurable request payload limit with `413` responses
- Configurable nesting-depth and value-count limits for decoded request payloads
- Safe refusal of streamed, encoded, and non-JSON responses that cannot be represented as MessagePack
- Laravel service provider and middleware auto-discovery
- PHP 8.1+ and Laravel 9.x through 12.x

## Installation

```bash
composer require smmehdisharifi/laravel-msgpack
```

## Quick Start

Apply the middleware to an API route or route group:

```php
use Illuminate\Support\Facades\Route;

Route::middleware('msgpack')->get('/api/profile', function () {
    return [
        'name' => 'Laravel',
        'format' => 'negotiated',
    ];
});
```

A normal client receives JSON:

```http
GET /api/profile HTTP/1.1
Accept: application/json
```

A MessagePack-aware client receives a binary response:

```http
GET /api/profile HTTP/1.1
Accept: application/msgpack
```

The response uses:

```http
Content-Type: application/msgpack
Vary: Accept
```

JSON remains the fallback when MessagePack is not selected. If both formats are accepted, the higher `q` value wins; media-type wildcards are evaluated according to their specificity. A `406 Not Acceptable` response is returned when the client explicitly rejects every supported format.

The configured `content_type` is also accepted as an explicit MessagePack response format. Additional entries in `accept_content_types`, such as the legacy `application/x-msgpack` type, are preserved when selected. The response macro always uses the configured `content_type`.

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
