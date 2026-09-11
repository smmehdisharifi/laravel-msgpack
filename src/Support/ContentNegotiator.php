<?php

namespace SmMehdiSharifi\LaravelMsgpack\Support;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\AcceptHeader;

final class ContentNegotiator
{
    public const FORMAT_JSON = 'json';

    public const FORMAT_MESSAGE_PACK = 'msgpack';

    public function isMessagePackContentType(?string $contentType): bool
    {
        return in_array(
            $this->mediaType($contentType),
            $this->configuredTypes('request_content_types'),
            true,
        );
    }

    public function isMessagePackResponseContentType(?string $contentType): bool
    {
        $mediaType = $this->mediaType($contentType);

        return $mediaType === $this->responseContentType()
            || in_array($mediaType, $this->configuredTypes('accept_content_types'), true);
    }

    public function acceptsMessagePack(Request $request): bool
    {
        return $this->negotiate($request) === self::FORMAT_MESSAGE_PACK;
    }

    public function negotiate(Request $request): ?string
    {
        $accepted = $this->parseAcceptHeader($request->header('Accept'));

        if ($accepted === []) {
            return self::FORMAT_JSON;
        }

        $messagePackQuality = $this->explicitMessagePackQuality($accepted);
        $jsonQuality = $this->qualityFor($accepted, ['application/json']);

        if ($this->explicitMessagePackQuality($accepted) > 0
            && $messagePackQuality >= $jsonQuality
        ) {
            return self::FORMAT_MESSAGE_PACK;
        }

        if ($jsonQuality > 0) {
            return self::FORMAT_JSON;
        }

        // JSON is the package fallback unless the client explicitly excludes it.
        if ($this->isUnacceptable($accepted, 'application/json')) {
            return null;
        }

        return self::FORMAT_JSON;
    }

    public function mentionsMessagePack(Request $request): bool
    {
        $accepted = $this->parseAcceptHeader($request->header('Accept'));

        foreach ($this->configuredTypes('accept_content_types') as $mediaType) {
            if (array_key_exists($mediaType, $accepted)) {
                return true;
            }
        }

        return false;
    }

    public function responseContentType(?Request $request = null): string
    {
        $configuredType = $this->mediaType((string) config('msgpack.content_type', 'application/msgpack'));

        if ($request === null) {
            return $configuredType;
        }

        $accepted = $this->parseAcceptHeader($request->header('Accept'));
        $candidates = [];

        foreach ($this->configuredTypes('accept_content_types') as $mediaType) {
            $quality = $this->explicitQualityFor($accepted, $mediaType);

            if ($quality !== null && $quality > 0) {
                $candidates[] = [
                    'media_type' => $mediaType,
                    'quality' => $quality,
                    'configured' => $mediaType === $configuredType,
                ];
            }
        }

        usort($candidates, static function (array $left, array $right): int {
            return [$right['quality'], $right['configured']] <=> [$left['quality'], $left['configured']];
        });

        return $candidates[0]['media_type'] ?? $configuredType;
    }

    /**
     * @return array<string, array{quality: float}>
     */
    private function parseAcceptHeader(?string $header): array
    {
        if ($header === null || trim($header) === '') {
            return [];
        }

        $accepted = [];

        try {
            foreach (AcceptHeader::fromString($header)->all() as $item) {
                $mediaType = strtolower(trim($item->getValue()));

                if ($mediaType === '') {
                    continue;
                }

                $accepted[$mediaType] = [
                    'quality' => max(0.0, min(1.0, $item->getQuality())),
                ];
            }
        } catch (\Throwable) {
            return [];
        }

        return $accepted;
    }

    /**
     * @param  array<string, array{quality: float}>  $accepted
     * @param  list<string>  $mediaTypes
     */
    private function qualityFor(array $accepted, array $mediaTypes): float
    {
        $quality = 0.0;

        foreach ($mediaTypes as $mediaType) {
            $mediaType = strtolower(trim($mediaType));

            foreach ($this->rangesFor($mediaType) as $range) {
                if (array_key_exists($range, $accepted)) {
                    $quality = max($quality, $accepted[$range]['quality']);
                    break;
                }
            }
        }

        return $quality;
    }

    /**
     * @param  array<string, array{quality: float}>  $accepted
     */
    private function explicitQualityFor(array $accepted, string $mediaType): ?float
    {
        $mediaType = strtolower(trim($mediaType));

        return array_key_exists($mediaType, $accepted)
            ? $accepted[$mediaType]['quality']
            : null;
    }

    /**
     * @param  array<string, array{quality: float}>  $accepted
     */
    private function isUnacceptable(array $accepted, string $mediaType): bool
    {
        foreach ($this->rangesFor($mediaType) as $range) {
            if (array_key_exists($range, $accepted)) {
                return $accepted[$range]['quality'] === 0.0;
            }
        }

        return false;
    }

    /**
     * @param  array<string, array{quality: float}>  $accepted
     */
    private function explicitMessagePackQuality(array $accepted): float
    {
        $quality = 0.0;

        foreach ($this->configuredTypes('accept_content_types') as $mediaType) {
            $quality = max($quality, $this->explicitQualityFor($accepted, $mediaType) ?? 0.0);
        }

        return $quality;
    }

    /**
     * @return list<string>
     */
    private function rangesFor(string $mediaType): array
    {
        $ranges = [$mediaType];

        if (str_contains($mediaType, '/')) {
            $ranges[] = substr($mediaType, 0, strpos($mediaType, '/')).'/*';
        }

        $ranges[] = '*/*';
        $ranges[] = '*';

        return $ranges;
    }

    private function mediaType(?string $contentType): string
    {
        if ($contentType === null || trim($contentType) === '') {
            return '';
        }

        return strtolower(trim(explode(';', $contentType, 2)[0]));
    }

    /**
     * @return list<string>
     */
    private function configuredTypes(string $key): array
    {
        $types = config("msgpack.{$key}", []);

        if (! is_array($types)) {
            $types = [];
        }

        if ($key === 'accept_content_types') {
            $types[] = config('msgpack.content_type', 'application/msgpack');
        }

        return array_values(array_filter(array_map(
            static fn (mixed $type): string => strtolower(trim((string) $type)),
            $types,
        )));
    }
}
