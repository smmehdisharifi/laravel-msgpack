<?php

namespace SmMehdiSharifi\LaravelMsgpack\Support;

use MessagePack\Type\Map;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

final class JsonPayloadDecoder
{
    public static function decodeResponse(SymfonyResponse $response): mixed
    {
        $content = $response->getContent();

        if ($content === false || $content === '') {
            throw new \UnexpectedValueException('The JSON response body is empty.');
        }

        return self::decode($content);
    }

    public static function decode(string $content): mixed
    {
        return self::normalize(json_decode(
            $content,
            false,
            512,
            JSON_BIGINT_AS_STRING | JSON_THROW_ON_ERROR,
        ));
    }

    private static function normalize(mixed $value): mixed
    {
        if ($value instanceof \stdClass) {
            $map = [];

            foreach (get_object_vars($value) as $key => $child) {
                $map[$key] = self::normalize($child);
            }

            return new Map($map);
        }

        if (is_array($value)) {
            foreach ($value as $key => $child) {
                $value[$key] = self::normalize($child);
            }
        }

        return $value;
    }
}
