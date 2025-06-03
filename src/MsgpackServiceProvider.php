<?php

namespace SmMehdiSharifi\LaravelMsgpack;

use Illuminate\Support\ServiceProvider;
use SmMehdiSharifi\LaravelMsgpack\Support\ResponseMacro;
use SmMehdiSharifi\LaravelMsgpack\Middleware\MsgpackMiddleware;

class MsgpackServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/msgpack.php', 'msgpack');

        $this->app->singleton('msgpack', fn () => new MsgpackManager());
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/msgpack.php' => config_path('msgpack.php'),
        ], 'msgpack-config');

        $this->app['router']->aliasMiddleware('msgpack', MsgpackMiddleware::class);

        ResponseMacro::register($this->app['Illuminate\Contracts\Routing\ResponseFactory']);
    }
}
