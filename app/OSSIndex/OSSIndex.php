<?php
namespace App\OSSIndex;

use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;

class OSSIndex 
{
    private $base_uri = 'https://ossindex.sonatype.org/api/';

    public function get_vulns($coordinates)
    {
        $client = new Client([
            // Base URI is used with relative requests
            'base_uri' => $this->base_uri,
            // You can set any number of default request options.
            'timeout'  => 100.0,
        ]);
        
        try
        {
            $response = $client->post('v3/component-report', [
                RequestOptions::JSON => $coordinates
            ]);
            $code = $response->getStatusCode();
            if ($code != 200)
            {
                echo "HTTP request did not return 200 OK: " . $code . ".";
                return;
    
            }
            else
            {
                echo $response->getBody();
                $vulnerabilities = \json_decode($response->getBody(), true);
                return $vulnerabilities;
            }    
        }
        catch (Exception $e)
        {
            echo "Exception thrown making HTTP request: " . $e->getMessage() . ".";
            return [];
        }
    }
}
