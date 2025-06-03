<?php

namespace SmMehdiSharifi\LaravelMsgpack\Tests;

use Orchestra\Testbench\TestCase;
use SmMehdiSharifi\LaravelMsgpack\Facades\Msgpack;
use SmMehdiSharifi\LaravelMsgpack\MsgpackServiceProvider;

class MsgpackTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [MsgpackServiceProvider::class];
    }

    protected function getPackageAliases($app): array
    {
        return ['Msgpack' => Msgpack::class];
    }


    public function testEncodeDecode()
    {
        $data = ['name' => 'Mehdi', 'age' => 30];
        $encoded = Msgpack::encode($data);
        $decoded = Msgpack::decode($encoded);

        $this->assertEquals($data, $decoded);
    }

    public function testEncodeDecodeVariousData()
    {
        $samples = [
            'string' => 'Hello, world!',
            'int' => 12345,
            'float' => 123.45,
            'array' => ['foo' => 'bar', 'baz' => [1, 2, 3]],
            'bool' => true,
            'null' => null,
        ];

        foreach ($samples as $input) {
            $encoded = Msgpack::encode($input);
            $decoded = Msgpack::decode($encoded);
            $this->assertEquals($input, $decoded);
        }
    }

    public function testResponseMacro()
    {
        $response = response()->msgpack(['hello' => 'world']);

        $this->assertEquals('application/x-msgpack', $response->headers->get('Content-Type'));

        $decoded = Msgpack::decode($response->getContent());

        $this->assertEquals(['hello' => 'world'], $decoded);
    }

    public function testServiceProviderRegistersSingleton()
    {
        $this->assertTrue($this->app->bound('msgpack'));

        $instance1 = $this->app->make('msgpack');
        $instance2 = $this->app->make('msgpack');

        $this->assertSame($instance1, $instance2);
    }
}
