<?php

use PHPUnit\Framework\TestCase;
use App\IQ\IQClient;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;

class IQClientTest extends TestCase
{
    public function testIQClientGetApplicationID()
    {
        $file = file_get_contents($this->join_paths(dirname(__FILE__), "iqapplicationresponse.txt"));

        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], $file)
        ]);

        $handlerStack = HandlerStack::create($mock);

        $client = new Client(['handler' => $handlerStack]);

        $iq_client = new IQClient($client);

        $internal_id = $iq_client->get_internal_application_id('testapp');

        $this->assertEquals($internal_id, "4537e6fe68c24dd5ac83efd97d4fc2f4");
    }

    private function join_paths(...$paths) {
        return preg_replace('~[/\\\\]+~', DIRECTORY_SEPARATOR, implode(DIRECTORY_SEPARATOR, $paths));
    }
}
