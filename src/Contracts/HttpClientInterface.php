<?php

namespace HRC\NectaPay\Contracts;

interface HttpClientInterface
{
    /**
     * Send a GET request.
     *
     * @param  string  $url      Full URL
     * @param  array   $headers  Request headers
     * @return array{status: int, body: array}
     */
    public function get(string $url, array $headers = []): array;

    /**
     * Send a POST request with JSON body.
     *
     * @param  string  $url      Full URL
     * @param  array   $data     Request body (will be JSON-encoded)
     * @param  array   $headers  Request headers
     * @return array{status: int, body: array}
     */
    public function post(string $url, array $data = [], array $headers = []): array;
}
