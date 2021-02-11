<?php

use PHPUnit\Framework\TestCase;
use App\OSSIndex\OSSIndex;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;

class OSSIndexTest extends TestCase
{
    public function testOSSIndexGetVulns()
    {
        $file = file_get_contents($this->join_paths(dirname(__FILE__), "ossindexresponse.txt"));

        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], $file)
        ]);

        $handlerStack = HandlerStack::create($mock);

        $client = new Client(['handler' => $handlerStack]);

        $oss_index = new OSSIndex($client);

        $coordinates["coordinates"] = ['pkg:test/pkga@1.0.0'];

        $vulns = $oss_index->get_vulns($coordinates);

        $this->assertEquals(count($vulns), 60);
    }

    private function join_paths(...$paths) {
        return preg_replace('~[/\\\\]+~', DIRECTORY_SEPARATOR, implode(DIRECTORY_SEPARATOR, $paths));
    }
}
