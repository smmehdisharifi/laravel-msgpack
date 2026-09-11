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
                return $this->createPendingRequest()->acceptMsgpack();
            });
        }

        if (! HttpResponse::hasMacro('msgpack')) {
            HttpResponse::macro('msgpack', function (?string $key = null, mixed $default = null) use ($manager, $negotiator, $isJsonContentType) {
                $body = $this->body();
                $contentType = $this->header('Content-Type');

                if ($body === '') {
                    $payload = null;
                } elseif ($negotiator->isMessagePackResponseContentType($contentType)) {
                    $payload = $manager->decode($body);
                } elseif ($isJsonContentType($contentType)) {
                    $payload = $this->json();
                } else {
                    throw new \UnexpectedValueException(
                        'The response content type cannot be decoded as MessagePack or JSON.',
                    );
                }

                return $key === null ? $payload : data_get($payload, $key, $default);
            });
        }
    }
}
