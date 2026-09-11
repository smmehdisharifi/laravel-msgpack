<?php

namespace SmMehdiSharifi\LaravelMsgpack\Support;

final class MessagePackPayloadValidator
{
    private int $offset = 0;

    private int $nodeCount = 0;

    private int $length;

    public function __construct(
        private readonly string $payload,
        private readonly int $maxDepth,
        private readonly int $maxNodes,
    ) {
        $this->length = strlen($payload);
    }

    public function validate(): void
    {
        $this->scanValue(0);
    }

    private function scanValue(int $depth): void
    {
        if ($this->maxDepth > 0 && $depth > $this->maxDepth) {
            throw new \LengthException('The MessagePack request payload exceeds the maximum nesting depth.');
        }

        if ($this->maxNodes > 0 && ++$this->nodeCount > $this->maxNodes) {
            throw new \LengthException('The MessagePack request payload contains too many values.');
        }

        $code = $this->readByte();

        if ($code <= 0x7F || $code >= 0xE0 || in_array($code, [0xC0, 0xC2, 0xC3], true)) {
            return;
        }

        if ($code >= 0x80 && $code <= 0x8F) {
            $this->scanMap($code & 0x0F, $depth);

            return;
        }

        if ($code >= 0x90 && $code <= 0x9F) {
            $this->scanArray($code & 0x0F, $depth);

            return;
        }

        if ($code >= 0xA0 && $code <= 0xBF) {
            $this->skip($code & 0x1F);

            return;
        }

        switch ($code) {
            case 0xCA:
                $this->skip(4);

                return;
            case 0xCB:
                $this->skip(8);

                return;
            case 0xCC:
            case 0xD0:
                $this->skip(1);

                return;
            case 0xCD:
            case 0xD1:
                $this->skip(2);

                return;
            case 0xCE:
            case 0xD2:
                $this->skip(4);

                return;
            case 0xCF:
            case 0xD3:
                $this->skip(8);

                return;
            case 0xC4:
            case 0xD9:
                $this->skip($this->readLength(1));

                return;
            case 0xC5:
            case 0xDA:
                $this->skip($this->readLength(2));

                return;
            case 0xC6:
            case 0xDB:
                $this->skip($this->readLength(4));

                return;
            case 0xC7:
                $length = $this->readLength(1);
                $this->skip(1);
                $this->skip($length);

                return;
            case 0xC8:
                $length = $this->readLength(2);
                $this->skip(1);
                $this->skip($length);

                return;
            case 0xC9:
                $length = $this->readLength(4);
                $this->skip(1);
                $this->skip($length);

                return;
            case 0xD4:
                $this->skip(2);

                return;
            case 0xD5:
                $this->skip(3);

                return;
            case 0xD6:
                $this->skip(5);

                return;
            case 0xD7:
                $this->skip(9);

                return;
            case 0xD8:
                $this->skip(17);

                return;
            case 0xDC:
                $this->scanArray($this->readLength(2), $depth);

                return;
            case 0xDD:
                $this->scanArray($this->readLength(4), $depth);

                return;
            case 0xDE:
                $this->scanMap($this->readLength(2), $depth);

                return;
            case 0xDF:
                $this->scanMap($this->readLength(4), $depth);

                return;
        }

        throw new \UnexpectedValueException('Invalid MessagePack payload.');
    }

    private function scanArray(int $size, int $depth): void
    {
        while ($size-- > 0) {
            $this->scanValue($depth + 1);
        }
    }

    private function scanMap(int $size, int $depth): void
    {
        while ($size-- > 0) {
            $this->scanValue($depth + 1);
            $this->scanValue($depth + 1);
        }
    }

    private function readByte(): int
    {
        if (! isset($this->payload[$this->offset])) {
            throw new \UnexpectedValueException('Invalid MessagePack payload.');
        }

        return ord($this->payload[$this->offset++]);
    }

    private function readLength(int $bytes): int
    {
        $format = match ($bytes) {
            1 => 'C',
            2 => 'n',
            4 => 'N',
        };

        return (int) unpack($format, $this->readBytes($bytes))[1];
    }

    private function skip(int $bytes): void
    {
        $this->readBytes($bytes);
    }

    private function readBytes(int $bytes): string
    {
        if ($bytes < 0 || $bytes > $this->length - $this->offset) {
            throw new \UnexpectedValueException('Invalid MessagePack payload.');
        }

        $value = substr($this->payload, $this->offset, $bytes);
        $this->offset += $bytes;

        return $value;
    }
}
