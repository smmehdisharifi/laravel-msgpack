<?php

namespace SmMehdiSharifi\LaravelMsgpack\Facades;

use Illuminate\Support\Facades\Facade;

class Msgpack extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'msgpack';
    }
}
