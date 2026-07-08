<?php

declare(strict_types=1);

namespace Waffle\Commons\Telemetry\Collector;

use Waffle\Commons\Contracts\Telemetry\Metrics\Enum\MetricType;
use Waffle\Commons\Contracts\Telemetry\Metrics\MetricSample;
use Waffle\Commons\Contracts\Telemetry\Metrics\MetricsCollectorInterface;

use function memory_get_peak_usage;
use function memory_get_usage;

/**
 * Samples the worker's live memory footprint (real allocation, not just the
 * emalloc pool) — current usage and the peak since the process started.
 */
final readonly class MemoryCollector implements MetricsCollectorInterface
{
    #[\Override]
    public function collect(): iterable
    {
        yield new MetricSample(
            'waffle_memory_usage_bytes',
            MetricType::Gauge,
            (float) memory_get_usage(true),
            [],
            'Current real memory used by the PHP worker, in bytes.',
        );
        yield new MetricSample(
            'waffle_memory_peak_bytes',
            MetricType::Gauge,
            (float) memory_get_peak_usage(true),
            [],
            'Peak real memory used by the PHP worker since start, in bytes.',
        );
    }
}
