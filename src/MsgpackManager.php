<?php

namespace SmMehdiSharifi\LaravelMsgpack;

use MessagePack\Packer;
use MessagePack\BufferUnpacker;

class MsgpackManager
{
    protected Packer $packer;

    public function __construct()
    {
        $this->packer = new Packer();
    }

    public function encode(mixed $data): string
    {
        return $this->packer->pack($data);
    }

    public function decode(string $msgpack): mixed
    {
        $unpacker = new BufferUnpacker();
        $unpacker->reset($msgpack);
        return $unpacker->unpack();
    }
}
