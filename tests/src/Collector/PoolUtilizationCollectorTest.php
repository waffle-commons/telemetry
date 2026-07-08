<?php

declare(strict_types=1);

namespace WaffleTests\Commons\Telemetry\Collector;

use PHPUnit\Framework\Attributes\CoversClass;
use Waffle\Commons\Contracts\Telemetry\Metrics\Enum\MetricType;
use Waffle\Commons\Contracts\Telemetry\Metrics\PoolStatsInterface;
use Waffle\Commons\Telemetry\Collector\PoolUtilizationCollector;
use WaffleTests\Commons\Telemetry\AbstractTestCase;

use function iterator_to_array;

#[CoversClass(PoolUtilizationCollector::class)]
final class PoolUtilizationCollectorTest extends AbstractTestCase
{
    public function testReportsZerosWithoutABoundPool(): void
    {
        $samples = iterator_to_array(new PoolUtilizationCollector()->collect(), preserve_keys: false);

        static::assertCount(3, $samples);
        foreach ($samples as $sample) {
            static::assertSame(MetricType::Gauge, $sample->type);
            static::assertSame(0.0, $sample->value);
        }
    }

    public function testReportsBoundPoolStats(): void
    {
        $pool = new class implements PoolStatsInterface {
            #[\Override]
            public function activeLeases(): int
            {
                return 3;
            }

            #[\Override]
            public function idle(): int
            {
                return 7;
            }

            #[\Override]
            public function capacity(): int
            {
                return 10;
            }
        };

        $byName = [];
        foreach (new PoolUtilizationCollector($pool)->collect() as $sample) {
            $byName[$sample->name] = $sample->value;
        }

        static::assertSame(3.0, $byName['waffle_db_pool_active'] ?? null);
        static::assertSame(7.0, $byName['waffle_db_pool_idle'] ?? null);
        static::assertSame(10.0, $byName['waffle_db_pool_capacity'] ?? null);
    }
}
