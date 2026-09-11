<?php

namespace SmMehdiSharifi\LaravelMsgpack\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as IlluminateResponse;
use MessagePack\Type\Map;
use SmMehdiSharifi\LaravelMsgpack\MsgpackManager;
use stdClass;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ResponseTransformer
{
    private const REPRESENTATION_HEADERS = [
        'content-type',
        'content-length',
        'transfer-encoding',
        'content-encoding',
        'content-range',
        'content-location',
        'etag',
        'content-md5',
        'digest',
    ];

    public function __construct(
        private readonly MsgpackManager $manager,
        private readonly ContentNegotiator $negotiator,
    ) {}

    public function toMessagePack(SymfonyResponse $response, Request $request): SymfonyResponse
    {
        if ($this->isBodyless($response)) {
            $this->clearBody($response);

            return $response;
        }

        if ($request->isMethod('HEAD')) {
            return $this->toMessagePackHead($response, $request);
        }

        if ($this->negotiator->isMessagePackResponseContentType($response->headers->get('Content-Type'))) {
            return $response;
        }

        if ($response instanceof StreamedResponse || $response->headers->has('Content-Encoding')) {
            return $this->unsupportedResponse($request);
        }

        [$isJson, $payload] = $this->jsonPayload($response);

        if (! $isJson) {
            return $this->unsupportedResponse($request);
        }

        $headers = $this->headersForMessagePack($response);
        $messagePackResponse = new IlluminateResponse(
            $this->manager->encode($payload),
            $response->getStatusCode(),
            $headers,
        );

        $messagePackResponse->headers->set(
            'Content-Type',
            $this->negotiator->responseContentType($request),
        );

        return $messagePackResponse;
    }

    private function toMessagePackHead(SymfonyResponse $response, Request $request): SymfonyResponse
    {
        if ($this->negotiator->isMessagePackResponseContentType($response->headers->get('Content-Type'))) {
            $this->clearBody($response);

            return $response;
        }

        if ($response instanceof StreamedResponse || $response->headers->has('Content-Encoding')) {
            $response = $this->unsupportedResponse($request);
            $this->clearBody($response);

            return $response;
        }

        $contentType = $response->headers->get('Content-Type', '');
        $mediaType = strtolower(trim(explode(';', $contentType, 2)[0]));

        if (! $response instanceof JsonResponse
            && $mediaType !== 'application/json'
            && ! str_ends_with($mediaType, '+json')
        ) {
            $response = $this->unsupportedResponse($request);
            $this->clearBody($response);

            return $response;
        }

        $headResponse = new IlluminateResponse(
            null,
            $response->getStatusCode(),
            $this->headersForMessagePack($response),
        );
        $headResponse->headers->set('Content-Type', $this->negotiator->responseContentType($request));
        $this->clearBody($headResponse);

        return $headResponse;
    }

    public function errorResponse(Request $request, string $message, int $status): SymfonyResponse
    {
        if ($this->negotiator->negotiate($request) === ContentNegotiator::FORMAT_MESSAGE_PACK) {
            $response = new IlluminateResponse(
                $this->manager->encode(['message' => $message]),
                $status,
                ['Content-Type' => $this->negotiator->responseContentType($request)],
            );
        } else {
            $response = new JsonResponse(['message' => $message], $status);
        }

        $this->ensureVaryAccept($response);

        return $response;
    }

    public function notAcceptable(Request $request): SymfonyResponse
    {
        $response = new JsonResponse([
            'message' => 'The requested response format is not acceptable.',
        ], 406);

        $this->ensureVaryAccept($response);

        return $response;
    }

    public function ensureVaryAccept(SymfonyResponse $response): void
    {
        $values = [];

        foreach ($response->headers->all('Vary') as $headerValue) {
            if ($headerValue === null) {
                continue;
            }

            foreach (explode(',', $headerValue) as $value) {
                $value = trim($value);

                if ($value !== '' && ! in_array(strtolower($value), array_map('strtolower', $values), true)) {
                    $values[] = $value;
                }
            }
        }

        if (in_array('*', array_map('trim', $values), true)) {
            return;
        }

        if (! in_array('accept', array_map('strtolower', $values), true)) {
            $values[] = 'Accept';
        }

        $response->headers->set('Vary', implode(', ', $values));
    }

    /**
     * @return array{0: bool, 1: mixed}
     */
    private function jsonPayload(SymfonyResponse $response): array
    {
        $contentType = $response->headers->get('Content-Type', '');
        $mediaType = strtolower(trim(explode(';', $contentType, 2)[0]));

        if (! $response instanceof JsonResponse
            && $mediaType !== 'application/json'
            && ! str_ends_with($mediaType, '+json')
        ) {
            return [false, null];
        }

        $content = $response->getContent();

        if ($content === false || $content === '') {
            return [false, null];
        }

        try {
            $decoded = json_decode(
                $content,
                false,
                512,
                JSON_BIGINT_AS_STRING | JSON_THROW_ON_ERROR,
            );

            return [true, $this->normalizeJsonValue($decoded)];
        } catch (\JsonException) {
            return [false, null];
        }
    }

    private function normalizeJsonValue(mixed $value): mixed
    {
        if ($value instanceof stdClass) {
            $map = [];

            foreach (get_object_vars($value) as $key => $child) {
                $map[$key] = $this->normalizeJsonValue($child);
            }

            return new Map($map);
        }

        if (is_array($value)) {
            foreach ($value as $key => $child) {
                $value[$key] = $this->normalizeJsonValue($child);
            }
        }

        return $value;
    }

    private function isBodyless(SymfonyResponse $response): bool
    {
        return $response->getStatusCode() < 200
            || in_array($response->getStatusCode(), [204, 205, 304], true);
    }

    private function clearBody(SymfonyResponse $response): void
    {
        $response->setContent(null);
        $response->headers->remove('Content-Length');

        if ($response->getStatusCode() < 200
            || in_array($response->getStatusCode(), [204, 205, 304], true)
        ) {
            $response->headers->remove('Content-Type');
        }
    }

    private function unsupportedResponse(Request $request): SymfonyResponse
    {
        return $this->errorResponse(
            $request,
            'The response cannot be represented as MessagePack.',
            406,
        );
    }

    /**
     * @return array<string, array<int, string|null>>
     */
    private function headersForMessagePack(SymfonyResponse $response): array
    {
        $headers = [];

        foreach ($response->headers->all() as $name => $values) {
            if (in_array(strtolower($name), self::REPRESENTATION_HEADERS, true)) {
                continue;
            }

            $headers[$name] = $values;
        }

        return $headers;
    }
}
