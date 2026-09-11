<?php

namespace SmMehdiSharifi\LaravelMsgpack;

use MessagePack\BufferUnpacker;
use MessagePack\Packer;
use MessagePack\TypeTransformer\MapTransformer;
use SmMehdiSharifi\LaravelMsgpack\Support\MessagePackPayloadValidator;

class MsgpackManager
{
    protected Packer $packer;

    public function __construct()
    {
        $this->packer = new Packer(null, [new MapTransformer]);
    }

    public function encode(mixed $data): string
    {
        return $this->packer->pack($data);
    }

    public function decode(string $msgpack, int $maxDepth = 0, int $maxNodes = 0): mixed
    {
        if ($maxDepth > 0 || $maxNodes > 0) {
            (new MessagePackPayloadValidator($msgpack, $maxDepth, $maxNodes))->validate();
        }

        $unpacker = new BufferUnpacker;
        $unpacker->reset($msgpack);
        $decoded = $unpacker->unpack();

        if ($unpacker->hasRemaining()) {
            throw new \UnexpectedValueException('The MessagePack payload contains trailing data.');
        }

        return $decoded;
    }
}
