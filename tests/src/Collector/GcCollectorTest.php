<?php

declare(strict_types=1);

namespace WaffleTests\Commons\Telemetry\Collector;

use PHPUnit\Framework\Attributes\CoversClass;
use Waffle\Commons\Contracts\Telemetry\Metrics\Enum\MetricType;
use Waffle\Commons\Telemetry\Collector\GcCollector;
use WaffleTests\Commons\Telemetry\AbstractTestCase;

use function iterator_to_array;

#[CoversClass(GcCollector::class)]
final class GcCollectorTest extends AbstractTestCase
{
    public function testYieldsGcCountersAndRootsGauge(): void
    {
        $byName = [];
        foreach (iterator_to_array(new GcCollector()->collect(), preserve_keys: false) as $sample) {
            $byName[$sample->name] = $sample->type;
        }

        static::assertSame(MetricType::Counter, $byName['waffle_gc_runs_total'] ?? null);
        static::assertSame(MetricType::Counter, $byName['waffle_gc_collected_total'] ?? null);
        static::assertSame(MetricType::Gauge, $byName['waffle_gc_roots'] ?? null);
    }
}
