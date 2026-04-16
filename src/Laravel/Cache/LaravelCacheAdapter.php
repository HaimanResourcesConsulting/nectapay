<?php

namespace HRC\NectaPay\Laravel\Cache;

use HRC\NectaPay\Contracts\CacheInterface;
use Illuminate\Support\Facades\Cache;

class LaravelCacheAdapter implements CacheInterface
{
    public function get(string $key): ?string
    {
        return Cache::get($key);
    }

    public function set(string $key, string $value, int $ttlSeconds): void
    {
        Cache::put($key, $value, $ttlSeconds);
    }

    public function forget(string $key): void
    {
        Cache::forget($key);
    }
}
