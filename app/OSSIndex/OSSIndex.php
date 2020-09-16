<?php
namespace App\OSSIndex;

use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;

class OSSIndex 
{
    public function get_vulns($coordinates)
    {
        $client = new Client([
            // Base URI is used with relative requests
            'base_uri' => 'https://ossindex.sonatype.org/api/',
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
                $this->error("HTTP request did not return 200 OK: ".$code . ".");
                return;
    
            }
            else
            {
                $vulnerabilities = \json_decode($response->getBody(), true);
                return $vulnerabilities;
            }    
        }
        catch (Exception $e)
        {
            $this->error("Exception thrown making HTTP request: ".$e->getMessage() . ".");
            return [];
        }
    }
}
