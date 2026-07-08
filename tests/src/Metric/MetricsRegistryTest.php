<?php

declare(strict_types=1);

namespace WaffleTests\Commons\Telemetry\Metric;

use PHPUnit\Framework\Attributes\CoversClass;
use Waffle\Commons\Contracts\Telemetry\Metrics\Enum\MetricType;
use Waffle\Commons\Contracts\Telemetry\Metrics\MetricSample;
use Waffle\Commons\Telemetry\Metric\ApcuMetricStore;
use Waffle\Commons\Telemetry\Metric\MetricsRegistry;
use WaffleTests\Commons\Telemetry\AbstractTestCase;

use function apcu_clear_cache;
use function apcu_enabled;
use function iterator_to_array;

#[CoversClass(MetricsRegistry::class)]
final class MetricsRegistryTest extends AbstractTestCase
{
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        if (!apcu_enabled()) {
            self::markTestSkipped('APCu is not enabled in this environment.');
        }
        apcu_clear_cache();
    }

    public function testIncrementAccumulatesAsACounter(): void
    {
        $registry = new MetricsRegistry(new ApcuMetricStore('m:'));
        $registry->increment('waffle_http_requests_total', 1.0, ['method' => 'GET']);
        $registry->increment('waffle_http_requests_total', 2.0, ['method' => 'GET']);

        $sample = $this->single($registry->collect());
        static::assertSame('waffle_http_requests_total', $sample->name);
        static::assertSame(MetricType::Counter, $sample->type);
        static::assertSame(3.0, $sample->value);
        static::assertSame(['method' => 'GET'], $sample->labels);
    }

    public function testGaugeRecordsAnAbsoluteValue(): void
    {
        $registry = new MetricsRegistry(new ApcuMetricStore('m:'));
        $registry->gauge('waffle_memory_bytes', 2048.0);
        $registry->gauge('waffle_memory_bytes', 1024.0);

        $sample = $this->single($registry->collect());
        static::assertSame(MetricType::Gauge, $sample->type);
        static::assertSame(1024.0, $sample->value);
    }

    public function testObserveRecordsSumAndCount(): void
    {
        $registry = new MetricsRegistry(new ApcuMetricStore('m:'));
        $registry->observe('waffle_request_seconds', 0.5);
        $registry->observe('waffle_request_seconds', 0.5);

        $byName = [];
        foreach ($registry->collect() as $sample) {
            $byName[$sample->name] = $sample->value;
        }

        static::assertSame(1.0, $byName['waffle_request_seconds_sum'] ?? null);
        static::assertSame(2.0, $byName['waffle_request_seconds_count'] ?? null);
    }

    public function testCollectSkipsMalformedKeys(): void
    {
        $store = new ApcuMetricStore('m:');
        $store->set('not-a-descriptor', 5.0);

        static::assertCount(0, iterator_to_array(new MetricsRegistry($store)->collect(), preserve_keys: false));
    }

    /**
     * @param iterable<MetricSample> $samples
     */
    private function single(iterable $samples): MetricSample
    {
        $all = iterator_to_array($samples, preserve_keys: false);
        static::assertCount(1, $all);
        $first = $all[0] ?? null;
        static::assertInstanceOf(MetricSample::class, $first);

        return $first;
    }
}
