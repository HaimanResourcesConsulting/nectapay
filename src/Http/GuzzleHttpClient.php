<?php

namespace HRC\NectaPay\Http;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use HRC\NectaPay\Contracts\HttpClientInterface;

class GuzzleHttpClient implements HttpClientInterface
{
    private Client $client;

    public function __construct(?Client $client = null, array $options = [])
    {
        $this->client = $client ?? new Client(array_merge([
            'timeout' => 30,
            'connect_timeout' => 10,
        ], $options));
    }

    public function get(string $url, array $headers = []): array
    {
        try {
            $response = $this->client->get($url, [
                'headers' => array_merge(['Accept' => 'application/json'], $headers),
            ]);

            return [
                'status' => $response->getStatusCode(),
                'body' => json_decode($response->getBody()->getContents(), true) ?? [],
            ];
        } catch (GuzzleException $e) {
            $status = 500;
            if ($e instanceof RequestException && $e->hasResponse()) {
                $status = $e->getResponse()->getStatusCode();
            }

            return [
                'status' => $status,
                'body' => ['error' => $e->getMessage()],
            ];
        }
    }

    public function post(string $url, array $data = [], array $headers = []): array
    {
        try {
            $response = $this->client->post($url, [
                'headers' => array_merge([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ], $headers),
                'json' => $data,
            ]);

            return [
                'status' => $response->getStatusCode(),
                'body' => json_decode($response->getBody()->getContents(), true) ?? [],
            ];
        } catch (GuzzleException $e) {
            $status = 500;
            if ($e instanceof RequestException && $e->hasResponse()) {
                $status = $e->getResponse()->getStatusCode();
            }

            return [
                'status' => $status,
                'body' => ['error' => $e->getMessage()],
            ];
        }
    }
}
