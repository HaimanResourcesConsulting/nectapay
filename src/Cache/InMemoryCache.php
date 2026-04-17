<?php

namespace HRC\NectaPay\Cache;

use HRC\NectaPay\Contracts\CacheInterface;

class InMemoryCache implements CacheInterface
{
    private array $store = [];

    public function get(string $key): ?string
    {
        if (!isset($this->store[$key])) {
            return null;
        }

        if ($this->store[$key]['expires_at'] < time()) {
            unset($this->store[$key]);
            return null;
        }

        return $this->store[$key]['value'];
    }

    public function set(string $key, string $value, int $ttlSeconds): void
    {
        $this->store[$key] = [
            'value' => $value,
            'expires_at' => time() + $ttlSeconds,
        ];
    }

    public function forget(string $key): void
    {
        unset($this->store[$key]);
    }
}
