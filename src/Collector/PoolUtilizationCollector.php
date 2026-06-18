<?php

declare(strict_types=1);

namespace Waffle\Commons\Telemetry\Collector;

use Waffle\Commons\Contracts\Telemetry\Metrics\Enum\MetricType;
use Waffle\Commons\Contracts\Telemetry\Metrics\MetricSample;
use Waffle\Commons\Contracts\Telemetry\Metrics\MetricsCollectorInterface;
use Waffle\Commons\Contracts\Telemetry\Metrics\PoolStatsInterface;

/**
 * Samples database connection-pool utilisation from a bound {@see PoolStatsInterface}
 * (the AXE 4 `DBAL-01` pooler). Until a pool is wired in the collector reports
 * zeros, so the metric exists from day one and gains real values when pooling lands.
 */
final readonly class PoolUtilizationCollector implements MetricsCollectorInterface
{
    public function __construct(
        private ?PoolStatsInterface $pool = null,
    ) {}

    #[\Override]
    public function collect(): iterable
    {
        yield new MetricSample(
            'waffle_db_pool_active',
            MetricType::Gauge,
            (float) ($this->pool?->activeLeases() ?? 0),
            [],
            'Connections currently leased to in-flight requests.',
        );
        yield new MetricSample(
            'waffle_db_pool_idle',
            MetricType::Gauge,
            (float) ($this->pool?->idle() ?? 0),
            [],
            'Idle connections available to lease.',
        );
        yield new MetricSample(
            'waffle_db_pool_capacity',
            MetricType::Gauge,
            (float) ($this->pool?->capacity() ?? 0),
            [],
            'Maximum pool capacity.',
        );
    }
}
