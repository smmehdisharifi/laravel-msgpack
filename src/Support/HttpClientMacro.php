<?php

namespace SmMehdiSharifi\LaravelMsgpack\Support;

use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response as HttpResponse;
use SmMehdiSharifi\LaravelMsgpack\MsgpackManager;

/**
 * Registers MessagePack helpers on Laravel's HTTP client.
 */
final class HttpClientMacro
{
    public static function register(MsgpackManager $manager, ContentNegotiator $negotiator): void
    {
        $isJsonContentType = static function (?string $contentType): bool {
            if ($contentType === null) {
                return false;
            }

            $mediaType = strtolower(trim(explode(';', $contentType, 2)[0]));

            return $mediaType === 'application/json' || str_ends_with($mediaType, '+json');
        };

        /**
         * @return array{max_depth: int, max_nodes: int}
         */
        $httpClientDecodeLimits = static function (): array {
            return [
                'max_depth' => (int) config(
                    'msgpack.http_client.max_depth',
                    config('msgpack.max_depth', 0),
                ),
                'max_nodes' => (int) config(
                    'msgpack.http_client.max_nodes',
                    config('msgpack.max_nodes', 0),
                ),
            ];
        };

        if (! PendingRequest::hasMacro('acceptMsgpack')) {
            PendingRequest::macro('acceptMsgpack', function () {
                return $this->accept(config('msgpack.content_type', 'application/msgpack'));
            });
        }

        if (! PendingRequest::hasMacro('withMsgpackBody')) {
            PendingRequest::macro('withMsgpackBody', function (mixed $payload) use ($manager) {
                return $this->withBody(
                    $manager->encode($payload),
                    config('msgpack.content_type', 'application/msgpack'),
                );
            });
        }

        if (! HttpFactory::hasMacro('msgpack')) {
            HttpFactory::macro('msgpack', function () {
                return $this->acceptMsgpack();
            });
        }

        if (! HttpResponse::hasMacro('msgpack')) {
            HttpResponse::macro(
                'msgpack',
                function (?string $key = null, mixed $default = null) use ($manager, $negotiator, $isJsonContentType, $httpClientDecodeLimits) {
                    $body = $this->body();
                    $contentType = $this->header('Content-Type');

                    if ($body === '') {
                        if (! in_array($this->status(), [204, 205, 304], true)) {
                            throw new \UnexpectedValueException('The response body is empty.');
                        }

                        $payload = null;
                    } elseif ($negotiator->isMessagePackResponseContentType($contentType)) {
                        $limits = $httpClientDecodeLimits();

                        $payload = $manager->decode(
                            $body,
                            $limits['max_depth'],
                            $limits['max_nodes'],
                        );
                    } elseif ($isJsonContentType($contentType)) {
                        $payload = $this->json();
                    } else {
                        throw new \UnexpectedValueException(
                            'The response content type cannot be decoded as MessagePack or JSON.',
                        );
                    }

                    return $key === null ? $payload : data_get($payload, $key, $default);
                },
            );
        }
    }
}
