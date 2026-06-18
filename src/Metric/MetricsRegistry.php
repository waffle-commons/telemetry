<?php

declare(strict_types=1);

namespace Waffle\Commons\Telemetry\Metric;

use Waffle\Commons\Contracts\Telemetry\Metrics\Enum\MetricType;
use Waffle\Commons\Contracts\Telemetry\Metrics\MetricSample;
use Waffle\Commons\Contracts\Telemetry\Metrics\MetricsCollectorInterface;
use Waffle\Commons\Contracts\Telemetry\Metrics\MetricsRegistryInterface;
use Waffle\Commons\Telemetry\Support\Coerce;

use function json_decode;
use function json_encode;
use function ksort;

/**
 * Records metrics into a shared {@see MetricStoreInterface} and reads them back as
 * {@see MetricSample}s for export. Stateless: every cumulative value lives in the
 * store (APCu shared memory), never on the worker heap — so it is worker-safe and
 * one scrape reflects all workers.
 *
 * `observe()` is stored as a summary (`<name>_sum` + `<name>_count` counters) so a
 * mean (e.g. average request duration) is derivable downstream. The metric
 * descriptor (name, type, labels) is JSON-encoded into the store key, so it can be
 * faithfully reconstructed for export.
 */
final readonly class MetricsRegistry implements MetricsRegistryInterface, MetricsCollectorInterface
{
    public function __construct(
        private MetricStoreInterface $store,
    ) {}

    #[\Override]
    public function increment(string $name, float $value = 1.0, array $labels = []): void
    {
        $this->store->add($this->key($name, MetricType::Counter, $labels), $value);
    }

    #[\Override]
    public function gauge(string $name, float $value, array $labels = []): void
    {
        $this->store->set($this->key($name, MetricType::Gauge, $labels), $value);
    }

    #[\Override]
    public function observe(string $name, float $value, array $labels = []): void
    {
        $this->store->add($this->key($name . '_sum', MetricType::Counter, $labels), $value);
        $this->store->add($this->key($name . '_count', MetricType::Counter, $labels), 1.0);
    }

    #[\Override]
    public function collect(): iterable
    {
        foreach ($this->store->snapshot() as $key => $value) {
            $descriptor = $this->descriptor($key);
            if ($descriptor === null) {
                continue;
            }

            [$name, $type, $labels] = $descriptor;
            yield new MetricSample($name, $type, $value, $labels);
        }
    }

    /** @param array<string, string> $labels */
    private function key(string $name, MetricType $type, array $labels): string
    {
        ksort($labels);

        return (string) json_encode(['n' => $name, 't' => $type->value, 'l' => $labels]);
    }

    /**
     * Reconstruct the (name, type, labels) descriptor encoded in a store key.
     *
     * @return array{0: string, 1: MetricType, 2: array<string, string>}|null
     */
    private function descriptor(string $key): ?array
    {
        // Coerce keeps json_decode()'s mixed out of the locals — no casts, no
        // suppression; a malformed key yields empty values and is dropped below.
        $data = Coerce::toArray(json_decode($key, associative: true));
        $name = Coerce::toString($data['n'] ?? null);
        $type = MetricType::tryFrom(Coerce::toString($data['t'] ?? null));
        if ($name === '' || $type === null) {
            return null;
        }

        return [$name, $type, Coerce::toStringMap($data['l'] ?? null)];
    }
}
