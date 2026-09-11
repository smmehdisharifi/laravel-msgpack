<?php

namespace SmMehdiSharifi\LaravelMsgpack\Support;

use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\Resources\Json\JsonResource;
use SmMehdiSharifi\LaravelMsgpack\Facades\Msgpack;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class ResponseMacro
{
    private const BODYLESS_STATUSES = [204, 205, 304];

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

    public static function register(ResponseFactory $factory): void
    {
        $bodylessStatuses = self::BODYLESS_STATUSES;
        $representationHeaders = self::REPRESENTATION_HEADERS;
        $isBodyless = static fn (int $status): bool => in_array($status, $bodylessStatuses, true);
        $mergeResourceHeaders = static function (array $headers, SymfonyResponse $resourceResponse) use ($representationHeaders): array {
            $headerNames = array_fill_keys(array_map('strtolower', array_keys($headers)), true);

            foreach ($resourceResponse->headers->all() as $name => $values) {
                $normalizedName = strtolower($name);

                if (in_array($normalizedName, $representationHeaders, true)
                    || isset($headerNames[$normalizedName])) {
                    continue;
                }

                $headers[$name] = $values;
                $headerNames[$normalizedName] = true;
            }

            return $headers;
        };

        $factory->macro('msgpack', function (mixed $value, int $status = 200, array $headers = []) use ($isBodyless, $mergeResourceHeaders) {
            $hasExplicitStatus = func_num_args() >= 2;

            if ($value instanceof JsonResource && ! ($hasExplicitStatus && $isBodyless($status))) {
                $resourceResponse = $value->toResponse(request());

                if (! $hasExplicitStatus) {
                    $status = $resourceResponse->getStatusCode();
                }

                $headers = $mergeResourceHeaders($headers, $resourceResponse);

                if (! $isBodyless($status)) {
                    $value = JsonPayloadDecoder::decodeResponse($resourceResponse);
                }
            }

            $headers['Content-Type'] = config('msgpack.content_type', 'application/msgpack');

            return response(
                $isBodyless($status) ? null : Msgpack::encode($value),
                $status,
                $headers,
            );
        });
    }
}
