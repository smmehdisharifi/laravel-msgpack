<?php

namespace SmMehdiSharifi\LaravelMsgpack\Support;

use Illuminate\Http\Request;

final class RequestMacro
{
    public const PAYLOAD_ATTRIBUTE = 'msgpack_payload';

    public static function register(): void
    {
        if (Request::hasMacro('msgpack')) {
            return;
        }

        Request::macro('msgpack', function (mixed $default = null): mixed {
            return $this->attributes->get(RequestMacro::PAYLOAD_ATTRIBUTE, $default);
        });
    }
}
