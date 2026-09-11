---
title: "JSON vs MessagePack in Laravel: A Practical API Benchmark"
published: false
description: "A practical comparison of JSON and MessagePack in Laravel, including content negotiation, real benchmark results, and a decision guide."
tags: laravel, php, api, performance
---

JSON is the right default for most Laravel APIs. It is readable, supported everywhere, easy to debug, and familiar to every client.

MessagePack is worth considering when payload size, bandwidth, or a controlled client ecosystem matters. It is a compact binary format that keeps a schema-free data model, but it is not automatically faster or smaller for every workload.

This article compares both formats in a Laravel API and shows how to adopt MessagePack without breaking existing JSON clients.

The examples use [Laravel Msgpack](https://github.com/smmehdisharifi/laravel-msgpack), an open-source package that adds opt-in MessagePack content negotiation, request decoding, safety limits, and a repeatable benchmark command.

## The short answer

Use JSON when:

- Your clients include browsers, third-party integrations, or unknown consumers.
- Human-readable responses and simple debugging are important.
- Your payloads are already small or compressed effectively.
- You do not control the client decoder and deployment environment.

Use MessagePack when:

- You control the clients and can ship a binary decoder.
- Network transfer size is important for mobile, edge, or internal services.
- Your payloads are large enough for serialization and decoding trade-offs to matter.
- You want to keep JSON compatibility while allowing capable clients to opt in.

For many Laravel applications, the best answer is not choosing one format globally. Keep JSON as the default and negotiate MessagePack per request.

## What are we comparing?

JSON is a text representation. It is self-describing, easy to inspect, and supported by the web platform.

MessagePack is a binary representation. It encodes the same basic data model using compact type markers and lengths instead of textual punctuation and names repeated in a text format.

The comparison should use the same data, the same PHP runtime, and the same operation count. Otherwise, the result is a benchmark of different workloads rather than a comparison of formats.

## Add MessagePack to Laravel

Install the package:

```bash
composer require smmehdisharifi/laravel-msgpack
```

Apply the middleware to a route or route group:

```php
use Illuminate\Support\Facades\Route;

Route::middleware('msgpack')->get('/api/profile', function () {
    return [
        'id' => 1042,
        'name' => 'Laravel MessagePack',
        'roles' => ['maintainer', 'developer'],
        'features' => [
            'negotiation' => true,
            'fallback' => 'json',
        ],
    ];
});
```

The route does not need separate JSON and MessagePack controllers. Existing clients continue to receive JSON:

```bash
curl -i http://localhost/api/profile
```

A capable client opts into MessagePack with the `Accept` header. Save the binary response instead of printing it as terminal text:

```bash
curl -sS -D - \
  -H 'Accept: application/msgpack' \
  -o profile.msgpack \
  http://localhost/api/profile
```

The negotiated response includes:

```http
Content-Type: application/msgpack
Vary: Accept
```

`Vary: Accept` is important because a cache must not serve a MessagePack response to a client that asked for JSON, or the other way around.

## How content negotiation works

The middleware keeps the migration opt-in:

1. A client that does not request MessagePack receives JSON.
2. A client that sends `Accept: application/msgpack` receives a binary response when the route supports the middleware.
3. If both formats are accepted, the higher quality factor wins.
4. Wildcards are evaluated by media-type specificity.
5. A request that rejects every supported representation receives `406 Not Acceptable`.

The same negotiation applies to relevant error responses, including unmatched routes and route exceptions. Incoming requests can use `application/msgpack` or the legacy `application/x-msgpack` media type.

## Run a repeatable benchmark

Laravel Msgpack includes an Artisan command so the comparison is easy to reproduce:

```bash
php artisan msgpack:benchmark --iterations=1000 --json
```

The command uses a deterministic API-shaped fixture with nested values and Unicode text. It measures:

- Raw JSON and MessagePack byte size.
- Encode time per iteration.
- Decode time per iteration.
- Optional gzip size and compression timings when `ext-zlib` is available.

The command warms each operation once before timing it. It reports per-iteration timings so runs with different iteration counts remain comparable.

## Results from this package

The following result was produced by the package benchmark on PHP 8.4 with `rybakit/msgpack` 0.7.2 and 1,000 iterations:

| Measurement | JSON | MessagePack | Difference |
| --- | ---: | ---: | ---: |
| Raw payload | 591 B | 435 B | 26.40% smaller |
| Encode time | 0.002 ms | 0.009 ms | JSON faster |
| Decode time | 0.003 ms | 0.008 ms | JSON faster |
| Gzip payload | 377 B | 371 B | 1.59% smaller |
| Gzip compress time | 0.009 ms | 0.010 ms | Nearly equal |
| Gzip decompress time | 0.004 ms | 0.003 ms | Nearly equal |

There are three useful conclusions here.

First, MessagePack is noticeably smaller before compression for this fixture. That can matter when transfer size is the bottleneck.

Second, the smallest representation is not automatically the fastest representation. For this small payload, JSON serialization is faster in the measured run.

Third, compression changes the size comparison. Once gzip is applied, the gap is much smaller because gzip already removes a lot of JSON's repetitive syntax.

These are indicative measurements, not universal promises. A real decision should use payloads, client runtimes, compression settings, and network conditions that match your application.

## Decode MessagePack requests

MessagePack request bodies are decoded automatically when the middleware is applied:

```php
use Illuminate\Http\Request;

Route::middleware('msgpack')->post('/api/profile', function (Request $request) {
    return [
        'received' => $request->msgpack(),
        'name' => $request->input('name'),
    ];
});
```

Map payloads are also merged into Laravel's normal request input. The original decoded value remains available through `request()->msgpack()`, including scalar and list payloads.

The package rejects malformed payloads before they reach the route handler. Configurable request size, nesting-depth, and value-count guards provide additional protection against oversized or unexpectedly deep input.

## When a route must always return MessagePack

Negotiation is useful when the same endpoint serves different clients. For a route that should always return MessagePack, use the response macro:

```php
return response()->msgpack([
    'message' => 'Created',
], 201, [
    'X-Request-Id' => $requestId,
]);
```

Direct serialization is also available through the facade:

```php
use Msgpack;

$packed = Msgpack::encode($data);
$unpacked = Msgpack::decode($packed);
```

## A practical decision guide

Do not change every endpoint to MessagePack because a synthetic benchmark produced a smaller payload. Instead, measure the part of the system that matters.

Choose JSON first when interoperability, browser support, observability, or simple debugging is the priority.

Test MessagePack when bandwidth, payload volume, or mobile transfer cost is important and the clients are controlled.

Keep both formats when you serve a mixed ecosystem. JSON remains the safe default, while clients that understand MessagePack can opt into it without a second controller implementation.

## Limitations to keep in mind

MessagePack is not encryption. Sensitive data still needs transport security and application-level authorization.

Binary responses are less convenient to inspect in logs and command-line terminals. Save them to a file or decode them with a client library.

Compression may reduce the practical size advantage. Measure compressed and uncompressed responses for the transport configuration you actually deploy.

The benchmark fixture is intentionally stable, not universal. A list-heavy payload, a text-heavy payload, a large nested object, or a response with repeated keys can produce different results.

## Try it in your project

Install [Laravel Msgpack](https://github.com/smmehdisharifi/laravel-msgpack), apply the `msgpack` middleware to one endpoint, and compare both representations with your real data.

The repository contains the full implementation, compatibility matrix, configuration reference, safety limits, and benchmark command:

https://github.com/smmehdisharifi/laravel-msgpack

If the package helps your API, [a GitHub star](https://github.com/smmehdisharifi/laravel-msgpack/stargazers) helps other Laravel developers discover it.
