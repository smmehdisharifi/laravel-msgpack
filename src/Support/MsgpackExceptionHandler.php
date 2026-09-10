<?php

namespace SmMehdiSharifi\LaravelMsgpack\Support;

use Illuminate\Contracts\Debug\ExceptionHandler as ExceptionHandlerContract;
use Illuminate\Http\Request;
use SmMehdiSharifi\LaravelMsgpack\Middleware\MsgpackMiddleware;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Throwable;

final class MsgpackExceptionHandler implements ExceptionHandlerContract
{
    public function __construct(
        private readonly ExceptionHandlerContract $handler,
        private readonly ContentNegotiator $negotiator,
        private readonly ResponseTransformer $transformer,
    ) {}

    public function report(Throwable $e): void
    {
        $this->handler->report($e);
    }

    public function shouldReport(Throwable $e): bool
    {
        return $this->handler->shouldReport($e);
    }

    public function render($request, Throwable $e): SymfonyResponse
    {
        if (! $request instanceof Request || ! $this->shouldHandle($request)) {
            return $this->handler->render($request, $e);
        }

        $format = $this->negotiator->negotiate($request);

        if ($format === null) {
            return $this->transformer->notAcceptable($request);
        }

        $renderRequest = clone $request;
        $renderRequest->headers->set('Accept', 'application/json');
        $response = $this->handler->render($renderRequest, $e);

        if (! $response instanceof SymfonyResponse) {
            return $response;
        }

        $this->transformer->ensureVaryAccept($response);

        return $format === ContentNegotiator::FORMAT_MESSAGE_PACK
            ? $this->transformer->toMessagePack($response, $request)
            : $response;
    }

    public function renderForConsole($output, Throwable $e): void
    {
        $this->handler->renderForConsole($output, $e);
    }

    private function shouldHandle(Request $request): bool
    {
        return (bool) $request->attributes->get(MsgpackMiddleware::ENABLED_ATTRIBUTE)
            || $this->negotiator->mentionsMessagePack($request);
    }
}
