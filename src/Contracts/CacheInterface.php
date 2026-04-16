<?php

namespace HRC\NectaPay\Contracts;

interface CacheInterface
{
    /**
     * Get a cached value by key.
     */
    public function get(string $key): ?string;

    /**
     * Store a value in cache.
     *
     * @param  int  $ttlSeconds  Time to live in seconds
     */
    public function set(string $key, string $value, int $ttlSeconds): void;

    /**
     * Remove a value from cache.
     */
    public function forget(string $key): void;
}
