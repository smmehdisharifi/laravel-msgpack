<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Response media type
    |--------------------------------------------------------------------------
    |
    | application/msgpack is the registered MessagePack media type. The
    | legacy x-msgpack type remains accepted for incoming requests.
    |
    */
    'content_type' => 'application/msgpack',

    'request_content_types' => [
        'application/msgpack',
        'application/x-msgpack',
    ],

    'accept_content_types' => [
        'application/msgpack',
        'application/x-msgpack',
    ],

    /*
    |--------------------------------------------------------------------------
    | Request limits
    |--------------------------------------------------------------------------
    |
    | Set this to null or 0 to disable the package-level limit. Laravel or the
    | web server may still enforce a lower request size limit.
    |
    */
    'max_request_size' => 10 * 1024 * 1024,

    /*
    |--------------------------------------------------------------------------
    | Decode limits
    |--------------------------------------------------------------------------
    |
    | Set either value to null or 0 to disable that package-level guard.
    |
    */
    'max_depth' => 64,

    'max_nodes' => 100000,

    /*
    |--------------------------------------------------------------------------
    | HTTP client decode limits
    |--------------------------------------------------------------------------
    |
    | Responses decoded through the Laravel HTTP client use dedicated limits
    | so untrusted upstream payloads cannot disable the package-level guard.
    | Set either value to null or 0 to disable that guard.
    |
    */
    'http_client' => [
        'max_depth' => 64,
        'max_nodes' => 100000,
    ],
];
