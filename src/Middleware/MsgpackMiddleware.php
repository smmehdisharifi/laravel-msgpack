<?php

namespace SmMehdiSharifi\LaravelMsgpack\Middleware;

use Closure;
use Illuminate\Http\Request;
use SmMehdiSharifi\LaravelMsgpack\Facades\Msgpack;
use SmMehdiSharifi\LaravelMsgpack\Support\ContentNegotiator;
use SmMehdiSharifi\LaravelMsgpack\Support\RequestMacro;
use SmMehdiSharifi\LaravelMsgpack\Support\ResponseTransformer;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class MsgpackMiddleware
{
    public const ENABLED_ATTRIBUTE = 'msgpack.middleware.enabled';

    public function __construct(
        private readonly ContentNegotiator $negotiator,
        private readonly ResponseTransformer $transformer,
    ) {}

    public function handle(Request $request, Closure $next)
    {
        $request->attributes->set(self::ENABLED_ATTRIBUTE, true);

        if ($this->negotiator->isMessagePackContentType($request->header('Content-Type'))) {
            $error = $this->decodeRequest($request);

            if ($error !== null) {
                return $error;
            }
        }

        $response = $next($request);

        if (! $response instanceof SymfonyResponse) {
            return $response;
        }

        $this->transformer->ensureVaryAccept($response);

        $format = $this->negotiator->negotiate($request);

        if ($format === null) {
            return $this->transformer->notAcceptable($request);
        }

        if ($format !== ContentNegotiator::FORMAT_MESSAGE_PACK) {
            return $response;
        }

        return $this->transformer->toMessagePack($response, $request);
    }

    private function decodeRequest(Request $request): ?SymfonyResponse
    {
        $maxRequestSize = (int) config('msgpack.max_request_size', 0);
        $content = $this->readContent($request, $maxRequestSize);

        if ($content === null) {
            return $this->errorResponse($request, 'The MessagePack request payload is too large.', 413);
        }

        try {
            $decoded = Msgpack::decode(
                $content,
                (int) config('msgpack.max_depth', 0),
                (int) config('msgpack.max_nodes', 0),
            );
        } catch (\Throwable $exception) {
            report($exception);

            $message = $exception instanceof \LengthException
                ? $exception->getMessage()
                : 'The request contains an invalid MessagePack payload.';

            return $this->errorResponse($request, $message, 400);
        }

        $request->attributes->set(RequestMacro::PAYLOAD_ATTRIBUTE, $decoded);

        if (is_array($decoded) && ! array_is_list($decoded)) {
            $request->merge($decoded);
        }

        return null;
    }

    private function readContent(Request $request, int $maxRequestSize): ?string
    {
        if ($maxRequestSize > 0 && $this->contentLengthExceedsLimit($request, $maxRequestSize)) {
            return null;
        }

        $content = $request->getContent(true);

        if (is_resource($content)) {
            $length = $maxRequestSize > 0 && $maxRequestSize < PHP_INT_MAX
                ? $maxRequestSize + 1
                : -1;
            $content = stream_get_contents($content, $length);
        } else {
            $content = $request->getContent();
        }

        if ($content === false) {
            return '';
        }

        if ($maxRequestSize > 0 && strlen($content) > $maxRequestSize) {
            return null;
        }

        return $content;
    }

    private function contentLengthExceedsLimit(Request $request, int $maxRequestSize): bool
    {
        $contentLength = trim((string) $request->header('Content-Length', ''));

        if ($contentLength === '' || ! ctype_digit($contentLength)) {
            return false;
        }

        $contentLength = ltrim($contentLength, '0') ?: '0';
        $limit = (string) $maxRequestSize;

        return strlen($contentLength) > strlen($limit)
            || (strlen($contentLength) === strlen($limit) && strcmp($contentLength, $limit) > 0);
    }

    private function errorResponse(Request $request, string $message, int $status): SymfonyResponse
    {
        if ($this->negotiator->negotiate($request) === null) {
            return $this->transformer->notAcceptable($request);
        }

        return $this->transformer->errorResponse($request, $message, $status);
    }
}
