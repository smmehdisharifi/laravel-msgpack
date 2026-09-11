<?php

namespace SmMehdiSharifi\LaravelMsgpack\Support;

use Illuminate\Contracts\Routing\ResponseFactory;
use SmMehdiSharifi\LaravelMsgpack\Facades\Msgpack;

class ResponseMacro
{
    public static function register(ResponseFactory $factory): void
    {
        $factory->macro('msgpack', function (mixed $value, int $status = 200, array $headers = []) {
            $headers['Content-Type'] = config('msgpack.content_type', 'application/msgpack');

            return response(
                in_array($status, [204, 205, 304], true) ? null : Msgpack::encode($value),
                $status,
                $headers,
            );
        });
    }
}
