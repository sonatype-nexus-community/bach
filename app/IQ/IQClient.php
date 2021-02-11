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

    private $username;

    private $token;

    private $stage;

    /**
     * The passed in value must be a Guzzle Client
     *
     * @param Client|null $client
     */
    public function __construct(
        $client = null,
        $host = 'http://localhost:8070/',
        $username = 'admin',
        $token = 'admin123',
        $stage = 'develop'
    ) {
        if ($client == null) {
            $this->client = new Client(
                [
                    'base_uri' => $host,
                    'timeout' => 100.0,
                    'http_errors' => false
                ]
            );
        } else {
            $this->client = $client;
        }

        $this->username = $username;
        $this->token = $token;
        $this->stage = $stage;
    }

    public function getInternalApplicationId($public_application_name)
    {
        try {
            $response = $this->client->get(
                'api/v2/applications?publicId=' . $public_application_name,
                [
                'auth' => [
                    $this->username,
                    $this->token
                ]
                ]
            );

            $code = $response->getStatusCode();
            if ($code != 200) {
                echo "HTTP request did not return 200 OK: " . $code . ".";
                return;
            } else {
                $internal_id = \json_decode($response->getBody(), true);

                return $internal_id['applications'][0]['id'];
            }
        } catch (Exception $e) {
            echo "Exception thrown making HTTP request: " . $e->getMessage() . ".";
            return [];
        }
    }

    public function submitSbom($sbom, $internal_application_id)
    {
        try {
            $response = $this->client->post(
                'api/v2/scan/applications/' . $internal_application_id . '/sources/bach?stageId=' . $this->stage,
                [
                'auth' => [
                    $this->username,
                    $this->token
                ],
                'headers' => [
                    'Content-Type' => 'application/xml'
                ],
                'body' => $sbom
                ]
            );

            $code = $response->getStatusCode();
            if ($code != 202) {
                echo "HTTP request did not return 200 OK: " . $code . ".";
                return;
            } else {
                $status_url = \json_decode($response->getBody(), true);
                
                return $status_url['statusUrl'];
            }
        } catch (Exception $e) {
            echo "Exception thrown making HTTP request: " . $e->getMessage() . ".";
            return [];
        }
    }

    public function pollStatusUrl($status_url)
    {
        do {
            try {
                $response = $this->client->get(
                    $status_url,
                    [
                    'auth' => [
                        $this->username,
                        $this->token
                    ]
                    ]
                );
        
                $code = $response->getStatusCode();
                if ($code == 200) {
                    $results = \json_decode($response->getBody(), true);

                    $iq_policy_response = new IQPolicyResponse();
                    foreach ($results as $key => $value) {
                        $iq_policy_response->{$key} = $value;
                    }
                    
                    return $iq_policy_response;
                }
                echo '.';
        
                sleep(1);
            } catch (Exception $e) {
                echo $e;
            }
        } while ($code == 404);
    }
}
