<?php

namespace SmMehdiSharifi\LaravelMsgpack\Support;

use Illuminate\Contracts\Routing\ResponseFactory;
use SmMehdiSharifi\LaravelMsgpack\Facades\Msgpack;

class ResponseMacro
{
    public static function register(ResponseFactory $factory): void
    {
        $factory->macro('msgpack', function ($value) {
            return response(Msgpack::encode($value), 200, [
                'Content-Type' => 'application/x-msgpack',
            ]);
        });
    }
}
