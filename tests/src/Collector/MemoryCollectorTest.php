<?php

declare(strict_types=1);

namespace WaffleTests\Commons\Telemetry\Collector;

use PHPUnit\Framework\Attributes\CoversClass;
use Waffle\Commons\Contracts\Telemetry\Metrics\Enum\MetricType;
use Waffle\Commons\Telemetry\Collector\MemoryCollector;
use WaffleTests\Commons\Telemetry\AbstractTestCase;

#[CoversClass(MemoryCollector::class)]
final class MemoryCollectorTest extends AbstractTestCase
{
    public function testYieldsCurrentAndPeakMemoryGauges(): void
    {
        $names = [];
        foreach (new MemoryCollector()->collect() as $sample) {
            static::assertSame(MetricType::Gauge, $sample->type);
            static::assertGreaterThan(0.0, $sample->value);
            $names[] = $sample->name;
        }

        static::assertSame(['waffle_memory_usage_bytes', 'waffle_memory_peak_bytes'], $names);
    }
}
