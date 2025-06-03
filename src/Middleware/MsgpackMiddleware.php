<?php

namespace SmMehdiSharifi\LaravelMsgpack\Middleware;

use Closure;
use Illuminate\Http\Request;
use SmMehdiSharifi\LaravelMsgpack\Facades\Msgpack;

class MsgpackMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->header('Content-Type') === 'application/x-msgpack') {
            try {
                $decoded = Msgpack::decode($request->getContent());
                if (is_array($decoded)) {
                    $request->merge($decoded);
                }
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return $next($request);
    }
}
