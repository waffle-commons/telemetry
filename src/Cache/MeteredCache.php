<?php

declare(strict_types=1);

namespace Waffle\Commons\Telemetry\Cache;

use DateInterval;
use Psr\SimpleCache\InvalidArgumentException;
use Waffle\Commons\Contracts\Cache\CacheInterface;
use Waffle\Commons\Contracts\Telemetry\Metrics\MetricsRegistryInterface;
use Waffle\Commons\Contracts\Telemetry\Metrics\NullMetricsRegistry;

/**
 * Decorates any Waffle/PSR-16 cache to record hit/miss counters into a metrics
 * registry, feeding the `/waffle-metrics` cache-hit-ratio. Hit vs miss is judged
 * with `has()` so the stored value is never captured into a `mixed` local; every
 * other operation delegates untouched. Stateless — counters live in the
 * registry's shared store, never on the worker heap.
 */
final readonly class MeteredCache implements CacheInterface
{
    public function __construct(
        private CacheInterface $inner,
        private MetricsRegistryInterface $metrics = new NullMetricsRegistry(),
    ) {}

    /** @throws InvalidArgumentException */
    #[\Override]
    public function get(string $key, mixed $default = null): mixed
    {
        $metric = $this->inner->has($key) ? 'waffle_cache_hits_total' : 'waffle_cache_misses_total';
        $this->metrics->increment($metric);

        return $this->inner->get($key, $default);
    }

    /** @throws InvalidArgumentException */
    #[\Override]
    public function set(string $key, mixed $value, int|DateInterval|null $ttl = null): bool
    {
        return $this->inner->set($key, $value, $ttl);
    }

    /** @throws InvalidArgumentException */
    #[\Override]
    public function delete(string $key): bool
    {
        return $this->inner->delete($key);
    }

    #[\Override]
    public function clear(): bool
    {
        return $this->inner->clear();
    }

    /**
     * @return iterable<string, mixed>
     * @throws InvalidArgumentException
     */
    #[\Override]
    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        return $this->inner->getMultiple($keys, $default);
    }

    /** @throws InvalidArgumentException */
    #[\Override]
    public function setMultiple(iterable $values, int|DateInterval|null $ttl = null): bool
    {
        return $this->inner->setMultiple($values, $ttl);
    }

    /** @throws InvalidArgumentException */
    #[\Override]
    public function deleteMultiple(iterable $keys): bool
    {
        return $this->inner->deleteMultiple($keys);
    }

    /** @throws InvalidArgumentException */
    #[\Override]
    public function has(string $key): bool
    {
        return $this->inner->has($key);
    }
}
