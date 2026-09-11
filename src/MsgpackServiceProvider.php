<?php

namespace SmMehdiSharifi\LaravelMsgpack;

use Illuminate\Contracts\Debug\ExceptionHandler as ExceptionHandlerContract;
use Illuminate\Support\ServiceProvider;
use SmMehdiSharifi\LaravelMsgpack\Console\BenchmarkCommand;
use SmMehdiSharifi\LaravelMsgpack\Middleware\MsgpackMiddleware;
use SmMehdiSharifi\LaravelMsgpack\Support\ContentNegotiator;
use SmMehdiSharifi\LaravelMsgpack\Support\MsgpackExceptionHandler;
use SmMehdiSharifi\LaravelMsgpack\Support\RequestMacro;
use SmMehdiSharifi\LaravelMsgpack\Support\ResponseMacro;
use SmMehdiSharifi\LaravelMsgpack\Support\ResponseTransformer;

class MsgpackServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/msgpack.php', 'msgpack');

        $this->app->singleton(MsgpackManager::class);
        $this->app->alias(MsgpackManager::class, 'msgpack');
        $this->app->singleton(ContentNegotiator::class);
        $this->app->singleton(ResponseTransformer::class);
        $this->app->extend(ExceptionHandlerContract::class, function ($handler, $app) {
            return new MsgpackExceptionHandler(
                $handler,
                $app->make(ContentNegotiator::class),
                $app->make(ResponseTransformer::class),
            );
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/msgpack.php' => config_path('msgpack.php'),
        ], 'msgpack-config');

        $this->app['router']->aliasMiddleware('msgpack', MsgpackMiddleware::class);

        ResponseMacro::register($this->app['Illuminate\Contracts\Routing\ResponseFactory']);
        RequestMacro::register();

        if ($this->app->runningInConsole()) {
            $this->commands([BenchmarkCommand::class]);
        }
    }
}
