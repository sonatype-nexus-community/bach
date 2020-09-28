<?php
namespace App\IQ;

use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;

class IQClient
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
    }

    public function submit_sbom($sbom) {
        
    }
}
