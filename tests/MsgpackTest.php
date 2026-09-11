<?php

namespace SmMehdiSharifi\LaravelMsgpack\Tests;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Orchestra\Testbench\TestCase;
use RuntimeException;
use SmMehdiSharifi\LaravelMsgpack\Facades\Msgpack;
use SmMehdiSharifi\LaravelMsgpack\MsgpackServiceProvider;

class MsgpackTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [MsgpackServiceProvider::class];
    }

    protected function getPackageAliases($app): array
    {
        return ['Msgpack' => Msgpack::class];
    }

    protected function defineRoutes($router): void
    {
        $router->middleware('msgpack')->get('/payload', function (Request $request) {
            return response()->json([
                'message' => 'hello',
                'payload' => $request->msgpack(),
            ]);
        });

        $router->middleware('msgpack')->post('/payload', function (Request $request) {
            return response()->json([
                'received' => $request->msgpack(),
                'name' => $request->input('name'),
            ], 201, ['X-Test' => 'preserved']);
        });

        $router->middleware('msgpack')->post('/list-payload', function (Request $request) {
            return response()->json([
                'received' => $request->msgpack(),
                'numeric_input' => $request->input('0', 'missing'),
            ]);
        });

        $router->middleware('msgpack')->get('/failure', function () {
            throw new RuntimeException('Something failed.');
        });

        $router->middleware('msgpack')->get('/validation-failure', function () {
            throw ValidationException::withMessages(['name' => 'The name is required.']);
        });

        $router->middleware('msgpack')->get('/html', function () {
            return response('<p>html</p>', 200, ['Content-Type' => 'text/html']);
        });

        $router->middleware('msgpack')->get('/stream', function () {
            return response()->stream(static function (): void {
                echo '{"stream":true}';
            }, 200, ['Content-Type' => 'application/json']);
        });

        $router->middleware('msgpack')->get('/compressed', function () {
            return response()->json(['compressed' => true], 200, ['Content-Encoding' => 'gzip']);
        });

        $router->middleware('msgpack')->get('/headers', function () {
            return response()->json(['ok' => true], 200, [
                'Cache-Control' => 'public, max-age=60',
                'ETag' => '"json-tag"',
                'Content-MD5' => 'json-digest',
                'Vary' => ['Origin', 'Accept-Encoding'],
            ]);
        });

        $router->middleware('msgpack')->get('/numeric-object', function () {
            return JsonResponse::fromJsonString('{"0":"x"}');
        });

        $router->middleware('msgpack')->get('/large-integer', function () {
            return JsonResponse::fromJsonString('{"number":9223372036854775808}');
        });

        $router->middleware('msgpack')->get('/empty-response', function () {
            return response()->json(['ignored' => true], 205);
        });

        $router->middleware('msgpack')->get('/not-modified', function () {
            return response()->json(['ignored' => true], 304);
        });

        $router->middleware('msgpack')->get('/head-response', function () {
            return response()->json(['ignored' => true]);
        });
    }

    public function test_encode_decode()
    {
        $data = ['name' => 'Mehdi', 'age' => 30];
        $encoded = Msgpack::encode($data);
        $decoded = Msgpack::decode($encoded);

        $this->assertEquals($data, $decoded);
    }

    public function test_encode_decode_various_data()
    {
        $samples = [
            'string' => 'Hello, world!',
            'int' => 12345,
            'float' => 123.45,
            'array' => ['foo' => 'bar', 'baz' => [1, 2, 3]],
            'bool' => true,
            'null' => null,
        ];

        foreach ($samples as $input) {
            $encoded = Msgpack::encode($input);
            $decoded = Msgpack::decode($encoded);
            $this->assertEquals($input, $decoded);
        }
    }

    public function test_response_macro()
    {
        $response = response()->msgpack(['hello' => 'world']);

        $this->assertEquals('application/msgpack', $response->headers->get('Content-Type'));

        $decoded = Msgpack::decode($response->getContent());

        $this->assertEquals(['hello' => 'world'], $decoded);
    }

    public function test_response_macro_accepts_status_and_headers(): void
    {
        $response = response()->msgpack(['created' => true], 201, ['X-Test' => 'ok']);

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame('ok', $response->headers->get('X-Test'));
    }

    public function test_response_macro_does_not_create_a_body_for_empty_statuses(): void
    {
        $response = response()->msgpack(['ignored' => true], 204);

        $this->assertSame('', $response->getContent());
    }

    public function test_middleware_does_not_create_a_body_for_empty_statuses(): void
    {
        $response = $this->withHeaders([
            'Accept' => 'application/msgpack',
        ])->get('/empty-response');

        $response->assertStatus(205);
        $this->assertSame('', $response->getContent());
    }

    public function test_middleware_does_not_create_a_body_for_not_modified_responses(): void
    {
        $response = $this->withHeaders([
            'Accept' => 'application/msgpack',
        ])->get('/not-modified');

        $response->assertStatus(304);
        $this->assertSame('', $response->getContent());
        $this->assertNull($response->headers->get('Content-Type'));
    }

    public function test_middleware_does_not_create_a_body_for_head_requests(): void
    {
        $response = $this->withHeaders([
            'Accept' => 'application/msgpack',
        ])->head('/head-response');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/msgpack');
        $this->assertSame('', $response->getContent());
    }

    public function test_middleware_returns_json_by_default(): void
    {
        $response = $this->get('/payload');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/json');
        $response->assertHeader('Vary', 'Accept');
        $response->assertJson([
            'message' => 'hello',
            'payload' => null,
        ]);
    }

    public function test_middleware_negotiates_message_pack_response(): void
    {
        $response = $this->withHeaders([
            'Accept' => 'application/msgpack',
        ])->get('/payload');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/msgpack');
        $response->assertHeader('Vary', 'Accept');
        $this->assertSame([
            'message' => 'hello',
            'payload' => null,
        ], Msgpack::decode($response->getContent()));
    }

    public function test_json_is_preferred_when_it_has_higher_quality(): void
    {
        $response = $this->withHeaders([
            'Accept' => 'application/msgpack;q=0.5, application/json;q=1',
        ])->get('/payload');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/json');
    }

    public function test_wildcard_quality_is_considered_for_json_fallback(): void
    {
        $response = $this->withHeaders([
            'Accept' => 'application/msgpack;q=0.1, */*;q=1',
        ])->get('/payload');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/json');
    }

    public function test_explicitly_unacceptable_formats_return_not_acceptable(): void
    {
        $response = $this->withHeaders([
            'Accept' => 'application/msgpack;q=0, application/json;q=0',
        ])->get('/payload');

        $response->assertStatus(406);
        $response->assertHeader('Content-Type', 'application/json');
        $response->assertJson([
            'message' => 'The requested response format is not acceptable.',
        ]);
    }

    public function test_specific_zero_quality_wildcard_rejects_json(): void
    {
        $response = $this->withHeaders([
            'Accept' => 'application/*;q=0, */*;q=1',
        ])->get('/payload');

        $response->assertStatus(406);
        $response->assertHeader('Content-Type', 'application/json');
    }

    public function test_legacy_accept_type_is_preserved_for_message_pack_response(): void
    {
        $response = $this->withHeaders([
            'Accept' => 'application/x-msgpack',
        ])->get('/payload');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/x-msgpack');
        $this->assertSame([
            'message' => 'hello',
            'payload' => null,
        ], Msgpack::decode($response->getContent()));
    }

    public function test_configured_response_content_type_is_negotiated(): void
    {
        config(['msgpack.content_type' => 'application/vnd.example+msgpack']);

        $response = $this->withHeaders([
            'Accept' => 'application/vnd.example+msgpack',
        ])->get('/payload');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/vnd.example+msgpack');
        $this->assertSame([
            'message' => 'hello',
            'payload' => null,
        ], Msgpack::decode($response->getContent()));
    }

    public function test_middleware_decodes_message_pack_request_with_parameters(): void
    {
        $payload = Msgpack::encode(['name' => 'Laravel']);

        $response = $this->call('POST', '/payload', [], [], [], [
            'CONTENT_TYPE' => 'application/msgpack; charset=binary',
            'HTTP_ACCEPT' => 'application/msgpack',
        ], $payload);

        $response->assertCreated();
        $response->assertHeader('Content-Type', 'application/msgpack');
        $response->assertHeader('X-Test', 'preserved');
        $this->assertSame([
            'received' => ['name' => 'Laravel'],
            'name' => 'Laravel',
        ], Msgpack::decode($response->getContent()));
    }

    public function test_list_message_pack_payload_is_not_merged_into_request_input(): void
    {
        $response = $this->call('POST', '/list-payload', [], [], [], [
            'CONTENT_TYPE' => 'application/msgpack',
        ], Msgpack::encode(['first']));

        $response->assertOk();
        $response->assertJson([
            'received' => ['first'],
            'numeric_input' => 'missing',
        ]);
    }

    public function test_legacy_message_pack_content_type_is_accepted(): void
    {
        $payload = Msgpack::encode(['name' => 'Laravel']);

        $response = $this->call('POST', '/payload', [], [], [], [
            'CONTENT_TYPE' => 'application/x-msgpack',
        ], $payload);

        $response->assertCreated();
        $response->assertHeader('Content-Type', 'application/json');
        $response->assertJson([
            'received' => ['name' => 'Laravel'],
            'name' => 'Laravel',
        ]);
    }

    public function test_invalid_message_pack_returns_bad_request(): void
    {
        $response = $this->call('POST', '/payload', [], [], [], [
            'CONTENT_TYPE' => 'application/msgpack',
            'HTTP_ACCEPT' => 'application/msgpack',
        ], "\xc1");

        $response->assertStatus(400);
        $response->assertHeader('Content-Type', 'application/msgpack');
        $response->assertHeader('Vary', 'Accept');
        $this->assertSame([
            'message' => 'The request contains an invalid MessagePack payload.',
        ], Msgpack::decode($response->getContent()));
    }

    public function test_empty_message_pack_returns_bad_request(): void
    {
        $response = $this->call('POST', '/payload', [], [], [], [
            'CONTENT_TYPE' => 'application/msgpack',
            'HTTP_ACCEPT' => 'application/msgpack',
        ], '');

        $response->assertStatus(400);
        $response->assertHeader('Content-Type', 'application/msgpack');
        $this->assertSame([
            'message' => 'The request contains an invalid MessagePack payload.',
        ], Msgpack::decode($response->getContent()));
    }

    public function test_trailing_message_pack_data_returns_bad_request(): void
    {
        $response = $this->call('POST', '/payload', [], [], [], [
            'CONTENT_TYPE' => 'application/msgpack',
            'HTTP_ACCEPT' => 'application/msgpack',
        ], Msgpack::encode(['name' => 'Laravel'])."\xc0");

        $response->assertStatus(400);
        $this->assertSame([
            'message' => 'The request contains an invalid MessagePack payload.',
        ], Msgpack::decode($response->getContent()));
    }

    public function test_message_pack_decode_rejects_trailing_data(): void
    {
        $this->expectException(\UnexpectedValueException::class);

        Msgpack::decode(Msgpack::encode(['valid' => true])."\xc0");
    }

    public function test_decode_limits_accept_supported_message_pack_values(): void
    {
        $payload = [
            'string' => 'value',
            'integer' => 42,
            'float' => 1.5,
            'boolean' => true,
            'null' => null,
            'list' => [1, 2, 3],
        ];

        $this->assertEquals(
            $payload,
            Msgpack::decode(Msgpack::encode($payload), 10, 100),
        );
    }

    public function test_nested_payloads_are_limited(): void
    {
        config(['msgpack.max_depth' => 1]);

        $response = $this->call('POST', '/payload', [], [], [], [
            'CONTENT_TYPE' => 'application/msgpack',
        ], Msgpack::encode(['outer' => ['inner' => true]]));

        $response->assertStatus(400);
        $response->assertJson([
            'message' => 'The MessagePack request payload exceeds the maximum nesting depth.',
        ]);
    }

    public function test_payload_node_count_is_limited(): void
    {
        config(['msgpack.max_nodes' => 2]);

        $response = $this->call('POST', '/payload', [], [], [], [
            'CONTENT_TYPE' => 'application/msgpack',
        ], Msgpack::encode([1, 2]));

        $response->assertStatus(400);
        $response->assertJson([
            'message' => 'The MessagePack request payload contains too many values.',
        ]);
    }

    public function test_map_keys_count_toward_payload_node_limit(): void
    {
        config(['msgpack.max_nodes' => 2]);

        $response = $this->call('POST', '/payload', [], [], [], [
            'CONTENT_TYPE' => 'application/msgpack',
        ], Msgpack::encode(['key' => 'value']));

        $response->assertStatus(400);
        $response->assertJson([
            'message' => 'The MessagePack request payload contains too many values.',
        ]);
    }

    public function test_content_length_is_rejected_before_decoding(): void
    {
        config(['msgpack.max_request_size' => 1]);

        $response = $this->call('POST', '/payload', [], [], [], [
            'CONTENT_TYPE' => 'application/msgpack',
            'CONTENT_LENGTH' => '100',
        ], Msgpack::encode(null));

        $response->assertStatus(413);
        $response->assertJson([
            'message' => 'The MessagePack request payload is too large.',
        ]);
    }

    public function test_message_pack_request_size_can_be_limited(): void
    {
        config(['msgpack.max_request_size' => 1]);

        $response = $this->call('POST', '/payload', [], [], [], [
            'CONTENT_TYPE' => 'application/msgpack',
        ], Msgpack::encode(['name' => 'Laravel']));

        $response->assertStatus(413);
        $response->assertJson([
            'message' => 'The MessagePack request payload is too large.',
        ]);
    }

    public function test_route_exceptions_are_rendered_as_message_pack(): void
    {
        config(['app.debug' => false]);

        $response = $this->withHeaders([
            'Accept' => 'application/msgpack',
        ])->get('/failure');

        $response->assertStatus(500);
        $response->assertHeader('Content-Type', 'application/msgpack');
        $response->assertHeader('Vary', 'Accept');
        $this->assertSame(['message' => 'Server Error'], Msgpack::decode($response->getContent()));
    }

    public function test_validation_exceptions_are_rendered_as_message_pack(): void
    {
        $response = $this->withHeaders([
            'Accept' => 'application/msgpack',
        ])->get('/validation-failure');

        $response->assertStatus(422);
        $response->assertHeader('Content-Type', 'application/msgpack');
        $payload = Msgpack::decode($response->getContent());

        $this->assertNotEmpty($payload['message']);
        $this->assertSame(['The name is required.'], $payload['errors']['name']);
    }

    public function test_unmatched_routes_are_rendered_as_message_pack(): void
    {
        config(['app.debug' => false]);

        $response = $this->withHeaders([
            'Accept' => 'application/msgpack',
        ])->get('/missing');

        $response->assertStatus(404);
        $response->assertHeader('Content-Type', 'application/msgpack');
        $response->assertHeader('Vary', 'Accept');
        $this->assertNotEmpty(Msgpack::decode($response->getContent())['message']);
    }

    public function test_non_json_responses_return_not_acceptable_for_message_pack_clients(): void
    {
        $response = $this->withHeaders([
            'Accept' => 'application/msgpack',
        ])->get('/html');

        $response->assertStatus(406);
        $response->assertHeader('Content-Type', 'application/msgpack');
        $this->assertSame([
            'message' => 'The response cannot be represented as MessagePack.',
        ], Msgpack::decode($response->getContent()));
    }

    public function test_streamed_responses_return_not_acceptable_for_message_pack_clients(): void
    {
        $response = $this->withHeaders([
            'Accept' => 'application/msgpack',
        ])->get('/stream');

        $response->assertStatus(406);
        $response->assertHeader('Content-Type', 'application/msgpack');
    }

    public function test_encoded_json_responses_return_not_acceptable_for_message_pack_clients(): void
    {
        $response = $this->withHeaders([
            'Accept' => 'application/msgpack',
        ])->get('/compressed');

        $response->assertStatus(406);
        $response->assertHeader('Content-Type', 'application/msgpack');
    }

    public function test_representation_headers_are_rebuilt_for_message_pack(): void
    {
        $response = $this->withHeaders([
            'Accept' => 'application/msgpack',
        ])->get('/headers');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/msgpack');
        $this->assertSame(['max-age=60, public'], $response->headers->all('Cache-Control'));
        $this->assertNull($response->headers->get('ETag'));
        $this->assertNull($response->headers->get('Content-MD5'));
        $vary = implode(', ', $response->headers->all('Vary'));
        $this->assertStringContainsString('Origin', $vary);
        $this->assertStringContainsString('Accept-Encoding', $vary);
        $this->assertStringContainsString('Accept', $vary);
    }

    public function test_json_objects_with_numeric_keys_remain_message_pack_maps(): void
    {
        $response = $this->withHeaders([
            'Accept' => 'application/msgpack',
        ])->get('/numeric-object');

        $response->assertOk();
        $this->assertSame('81', bin2hex(substr($response->getContent(), 0, 1)));
        $this->assertSame([0 => 'x'], Msgpack::decode($response->getContent()));
    }

    public function test_large_json_integers_are_not_converted_to_floats(): void
    {
        $response = $this->withHeaders([
            'Accept' => 'application/msgpack',
        ])->get('/large-integer');

        $response->assertOk();
        $this->assertSame([
            'number' => '9223372036854775808',
        ], Msgpack::decode($response->getContent()));
    }

    public function test_service_provider_registers_singleton()
    {
        $this->assertTrue($this->app->bound('msgpack'));

        $instance1 = $this->app->make('msgpack');
        $instance2 = $this->app->make('msgpack');

        $this->assertSame($instance1, $instance2);
    }
}
