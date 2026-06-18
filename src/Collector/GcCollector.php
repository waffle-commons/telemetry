<?php

declare(strict_types=1);

namespace Waffle\Commons\Telemetry\Collector;

use Waffle\Commons\Contracts\Telemetry\Metrics\Enum\MetricType;
use Waffle\Commons\Contracts\Telemetry\Metrics\MetricSample;
use Waffle\Commons\Contracts\Telemetry\Metrics\MetricsCollectorInterface;
use Waffle\Commons\Telemetry\Support\Coerce;

use function gc_status;

/**
 * Samples the cyclic garbage collector — cumulative cycle runs and objects
 * collected (counters), plus the current root-buffer size (gauge). Rising GC runs
 * under load is the classic worker-mode memory-pressure signal.
 */
final readonly class GcCollector implements MetricsCollectorInterface
{
    #[\Override]
    public function collect(): iterable
    {
        $status = gc_status();

        yield new MetricSample(
            'waffle_gc_runs_total',
            MetricType::Counter,
            Coerce::toFloat($status['runs']),
            [],
            'Total garbage-collection cycles run since worker start.',
        );
        yield new MetricSample(
            'waffle_gc_collected_total',
            MetricType::Counter,
            Coerce::toFloat($status['collected']),
            [],
            'Total objects reclaimed by the garbage collector.',
        );
        yield new MetricSample(
            'waffle_gc_roots',
            MetricType::Gauge,
            Coerce::toFloat($status['roots']),
            [],
            'Current number of roots in the GC buffer.',
        );
    }
}
