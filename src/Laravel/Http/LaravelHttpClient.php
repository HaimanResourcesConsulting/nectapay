<?php

namespace HRC\NectaPay\Laravel\Http;

use HRC\NectaPay\Contracts\HttpClientInterface;
use Illuminate\Support\Facades\Http;

class LaravelHttpClient implements HttpClientInterface
{
    public function get(string $url, array $headers = []): array
    {
        $response = Http::withHeaders($headers)
            ->timeout(30)
            ->retry(2, 500)
            ->get($url);

        return [
            'status' => $response->status(),
            'body' => $response->json() ?? [],
        ];
    }

    public function post(string $url, array $data = [], array $headers = []): array
    {
        $response = Http::withHeaders($headers)
            ->timeout(30)
            ->retry(2, 500)
            ->post($url, $data);

        return [
            'status' => $response->status(),
            'body' => $response->json() ?? [],
        ];
    }
}
