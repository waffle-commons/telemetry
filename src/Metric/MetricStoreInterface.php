<?php

declare(strict_types=1);

namespace Waffle\Commons\Telemetry\Metric;

/**
 * Shared, worker-safe storage for raw metric values (opaque key → float). The
 * production binding ({@see ApcuMetricStore}) keeps values in instance-shared
 * memory, NEVER on the resident worker heap, so cumulative counters survive
 * across requests and a single scrape sees every worker's contribution.
 */
interface MetricStoreInterface
{
    /** Atomically add $delta to $key (treating an absent key as 0). */
    public function add(string $key, float $delta): void;

    /** Set $key to an absolute $value. */
    public function set(string $key, float $value): void;

    /**
     * Every stored key with its current value.
     *
     * @return array<string, float>
     */
    public function snapshot(): array;
}
