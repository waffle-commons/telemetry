<?php

declare(strict_types=1);

namespace Waffle\Commons\Telemetry\Metric;

use Waffle\Commons\Telemetry\Support\Coerce;

use function apcu_fetch;
use function apcu_store;
use function in_array;

/**
 * APCu-backed metric store: counters live in the host's shared memory, never on
 * the resident worker heap, so `wfl igor` stays clean and one `/waffle-metrics`
 * scrape aggregates every worker on the instance. A small index key tracks the
 * live key set, so {@see self::snapshot()} needs no `APCUIterator`.
 *
 * Requires the `apcu` extension (with `apc.enable_cli` under the worker SAPI).
 * When APCu is unavailable the wiring falls back to the contracts `NullMetricsRegistry`
 * (no-op), so the endpoint still serves live gauges.
 *
 * Stateless from the worker's view — the only instance field is the immutable key
 * prefix; all mutable state is external (APCu).
 */
final readonly class ApcuMetricStore implements MetricStoreInterface
{
    private const string INDEX_KEY = '__index__';

    public function __construct(
        private string $prefix = 'wfl_metric:',
    ) {}

    #[\Override]
    public function add(string $key, float $delta): void
    {
        $full = $this->prefix . $key;
        // Read-modify-write (non-atomic, but metrics are intentionally approximate);
        // Coerce keeps apcu_fetch()'s mixed out of the local — an absent key ⇒ 0.0.
        $current = Coerce::toFloat(apcu_fetch($full));
        apcu_store($full, $current + $delta);
        $this->index($key);
    }

    #[\Override]
    public function set(string $key, float $value): void
    {
        apcu_store($this->prefix . $key, $value);
        $this->index($key);
    }

    #[\Override]
    public function snapshot(): array
    {
        $out = [];
        foreach ($this->keys() as $key) {
            $out[$key] = Coerce::toFloat(apcu_fetch($this->prefix . $key));
        }

        return $out;
    }

    private function index(string $key): void
    {
        $keys = $this->keys();
        if (!in_array($key, $keys, strict: true)) {
            $keys[] = $key;
            apcu_store($this->prefix . self::INDEX_KEY, $keys);
        }
    }

    /** @return list<string> */
    private function keys(): array
    {
        // Coerce turns apcu_fetch()'s mixed (or an absent index) into a clean
        // list<string> — no foreach over a mixed array.
        return Coerce::toStringList(apcu_fetch($this->prefix . self::INDEX_KEY));
    }
}
