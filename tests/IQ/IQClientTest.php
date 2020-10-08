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

    public function testIQClientSubmitSbom()
    {
        $file = file_get_contents($this->join_paths(dirname(__FILE__), "iqsbomresponse.txt"));

        $mock = new MockHandler([
            new Response(202, ['Content-Type' => 'application/json'], $file)
        ]);

        $handlerStack = HandlerStack::create($mock);

        $client = new Client(['handler' => $handlerStack]);

        $iq_client = new IQClient($client);

        $status_url = $iq_client->submit_sbom('sbom', '4537e6fe68c24dd5ac83efd97d4fc2f4', 'develop');

        $this->assertEquals($status_url, "api/v2/scan/applications/a20bc16e83944595a94c2e36c1cd228e/status/9cee2b6366fc4d328edc318eae46b2cb");
    }

    public function testIQClientPollURL()
    {
        $file = file_get_contents($this->join_paths(dirname(__FILE__), "iqpolicyresponse.txt"));

        $mock = new MockHandler([
            new Response(404, []),
            new Response(200, [], $file)
        ]);

        $handlerStack = HandlerStack::create($mock);

        $client = new Client([
            'handler' => $handlerStack,
            'http_errors' => false
            ]);

        $iq_client = new IQClient($client);

        $response = $iq_client->poll_status_url("api/v2/scan/applications/a20bc16e83944595a94c2e36c1cd228e/status/9cee2b6366fc4d328edc318eae46b2cb");

        $this->assertEquals($response->policyAction, 'None');
    }

    private function join_paths(...$paths) {
        return preg_replace('~[/\\\\]+~', DIRECTORY_SEPARATOR, implode(DIRECTORY_SEPARATOR, $paths));
    }
}
