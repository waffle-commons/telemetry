<?php

declare(strict_types=1);

namespace WaffleTests\Commons\Telemetry\Helper;

use DateInterval;
use Waffle\Commons\Contracts\Cache\CacheInterface;

use function array_key_exists;

/** Minimal in-memory PSR-16 cache for decorator tests. */
final class ArrayCache implements CacheInterface
{
    /** @var array<array-key, mixed> */
    private array $store = [];

    #[\Override]
    public function get(string $key, mixed $default = null): mixed
    {
        return array_key_exists($key, $this->store) ? $this->store[$key] : $default;
    }

    #[\Override]
    public function set(string $key, mixed $value, int|DateInterval|null $ttl = null): bool
    {
        $this->store[$key] = $value;

        return true;
    }

    #[\Override]
    public function delete(string $key): bool
    {
        unset($this->store[$key]);

        return true;
    }

    #[\Override]
    public function clear(): bool
    {
        $this->store = [];

        return true;
    }

    #[\Override]
    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $out = [];
        foreach ($keys as $key) {
            $out[$key] = $this->get($key, $default);
        }

        return $out;
    }

    #[\Override]
    public function setMultiple(iterable $values, int|DateInterval|null $ttl = null): bool
    {
        foreach ($values as $key => $value) {
            $this->store[$key] = $value;
        }

        return true;
    }

    #[\Override]
    public function deleteMultiple(iterable $keys): bool
    {
        foreach ($keys as $key) {
            unset($this->store[$key]);
        }

        return true;
    }

    #[\Override]
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->store);
    }
}
