<?php

declare(strict_types=1);

namespace WaffleTests\Commons\Telemetry\Cache;

use PHPUnit\Framework\Attributes\CoversClass;
use Waffle\Commons\Telemetry\Cache\MeteredCache;
use Waffle\Commons\Telemetry\Metric\ApcuMetricStore;
use Waffle\Commons\Telemetry\Metric\MetricsRegistry;
use WaffleTests\Commons\Telemetry\AbstractTestCase;
use WaffleTests\Commons\Telemetry\Helper\ArrayCache;

use function apcu_clear_cache;
use function apcu_enabled;

#[CoversClass(MeteredCache::class)]
final class MeteredCacheTest extends AbstractTestCase
{
    public function testGetRecordsHitsAndMisses(): void
    {
        if (!apcu_enabled()) {
            self::markTestSkipped('APCu is not enabled in this environment.');
        }
        apcu_clear_cache();

        $inner = new ArrayCache();
        $inner->set('present', 'value');
        $registry = new MetricsRegistry(new ApcuMetricStore('c:'));
        $cache = new MeteredCache($inner, $registry);

        static::assertSame('value', $cache->get('present'));
        static::assertSame('fallback', $cache->get('absent', 'fallback'));

        $metrics = [];
        foreach ($registry->collect() as $sample) {
            $metrics[$sample->name] = $sample->value;
        }

        static::assertSame(1.0, $metrics['waffle_cache_hits_total'] ?? null);
        static::assertSame(1.0, $metrics['waffle_cache_misses_total'] ?? null);
    }

    public function testDelegatesEveryOperationToTheInnerCache(): void
    {
        // No registry passed → the no-op default; exercises every delegated method.
        $cache = new MeteredCache(new ArrayCache());

        static::assertTrue($cache->set('k', 1));
        static::assertTrue($cache->has('k'));
        static::assertSame(1, $cache->get('k'));
        static::assertTrue($cache->setMultiple(['a' => 1, 'b' => 2]));

        $multi = [];
        foreach ($cache->getMultiple(['a', 'b']) as $key => $value) {
            $multi[$key] = $value;
        }
        static::assertSame(['a' => 1, 'b' => 2], $multi);

        static::assertTrue($cache->deleteMultiple(['a']));
        static::assertFalse($cache->has('a'));
        static::assertTrue($cache->delete('k'));
        static::assertTrue($cache->clear());
        static::assertFalse($cache->has('b'));
    }
}
