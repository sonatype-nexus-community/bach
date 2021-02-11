<?php
namespace App\OSSIndex;

use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;

class OSSIndex
{
    /**
     * @var Client
     */
    private $client;

    /**
     * The passed in value must be a Guzzle Client
     *
     * @param Client|null $client
     */
    public function __construct(
        $client = null
    ) {
        if ($client == null) {
            $this->client = new Client(
                ['base_uri' => 'https://ossindex.sonatype.org/api/',
                'timeout' => 100.0,
                ]
            );
        } else {
            $this->client = $client;
        }
    }

    public function getVulns($coordinates)
    {
        try {
            $coord_chunks = array_chunk($coordinates["coordinates"], 128);
            $vulnerabilities = array();

            foreach ($coord_chunks as $chunk) {
                $chonk = (object)[];
                $chonk->coordinates = $chunk;

                $response = $this->client->post('v3/component-report', [
                    RequestOptions::JSON => $chonk
                ]);

                $code = $response->getStatusCode();
                if ($code != 200) {
                    echo "HTTP request did not return 200 OK: " . $code . ".";
                    return;
                } else {
                    $vulnerabilities = array_merge($vulnerabilities, \json_decode($response->getBody(), true));
                }
            }

            return $vulnerabilities;
        } catch (Exception $e) {
            echo "Exception thrown making HTTP request: " . $e->getMessage() . ".";
            return [];
        }
    }
}
