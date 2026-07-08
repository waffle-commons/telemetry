<?php

declare(strict_types=1);

namespace WaffleTests\Commons\Telemetry\Metric;

use PHPUnit\Framework\Attributes\CoversClass;
use Waffle\Commons\Telemetry\Metric\ApcuMetricStore;
use WaffleTests\Commons\Telemetry\AbstractTestCase;

use function apcu_clear_cache;
use function apcu_enabled;

#[CoversClass(ApcuMetricStore::class)]
final class ApcuMetricStoreTest extends AbstractTestCase
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

    public function testAddAccumulatesAcrossCalls(): void
    {
        $store = new ApcuMetricStore('test:');
        $store->add('alpha', 1.5);
        $store->add('alpha', 2.0);

        static::assertSame(['alpha' => 3.5], $store->snapshot());
    }

    public function testSetOverwrites(): void
    {
        $store = new ApcuMetricStore('test:');
        $store->set('beta', 9.0);
        $store->set('beta', 4.0);

        static::assertSame(['beta' => 4.0], $store->snapshot());
    }

    public function testSnapshotReturnsEveryKnownKey(): void
    {
        $store = new ApcuMetricStore('test:');
        $store->add('one', 1.0);
        $store->set('two', 2.0);

        static::assertSame(['one' => 1.0, 'two' => 2.0], $store->snapshot());
    }

    public function testPrefixIsolatesStores(): void
    {
        new ApcuMetricStore('a:')->add('k', 1.0);

        static::assertSame([], new ApcuMetricStore('b:')->snapshot());
    }
}
