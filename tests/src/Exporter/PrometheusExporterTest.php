<?php

declare(strict_types=1);

namespace WaffleTests\Commons\Telemetry\Exporter;

use PHPUnit\Framework\Attributes\CoversClass;
use Waffle\Commons\Contracts\Telemetry\Metrics\Enum\MetricType;
use Waffle\Commons\Contracts\Telemetry\Metrics\MetricSample;
use Waffle\Commons\Contracts\Telemetry\Metrics\MetricsCollectorInterface;
use Waffle\Commons\Telemetry\Exporter\PrometheusExporter;
use WaffleTests\Commons\Telemetry\AbstractTestCase;

use function substr_count;

#[CoversClass(PrometheusExporter::class)]
final class PrometheusExporterTest extends AbstractTestCase
{
    public function testRendersHelpTypeAndLabelledSamples(): void
    {
        $exporter = new PrometheusExporter([$this->collector(
            new MetricSample(
                'waffle_http_requests_total',
                MetricType::Counter,
                3.0,
                ['method' => 'GET'],
                'Total requests.',
            ),
            new MetricSample('waffle_http_requests_total', MetricType::Counter, 1.0, ['method' => 'POST']),
        )]);

        $output = $exporter->render();

        static::assertStringContainsString('# HELP waffle_http_requests_total Total requests.', $output);
        static::assertStringContainsString('# TYPE waffle_http_requests_total counter', $output);
        static::assertStringContainsString('waffle_http_requests_total{method="GET"} 3', $output);
        static::assertStringContainsString('waffle_http_requests_total{method="POST"} 1', $output);
        // HELP/TYPE are declared exactly once per metric name.
        static::assertSame(1, substr_count($output, '# TYPE waffle_http_requests_total counter'));
    }

    public function testFormatsValuesEscapesAndOmitsEmptyLabels(): void
    {
        $exporter = new PrometheusExporter([$this->collector(
            new MetricSample('waffle_gauge', MetricType::Gauge, 42.0),
            new MetricSample('waffle_seconds', MetricType::Gauge, 0.012),
            new MetricSample('waffle_quote', MetricType::Gauge, 1.0, ['note' => 'a"b']),
        )]);

        $output = $exporter->render();

        static::assertStringContainsString("waffle_gauge 42\n", $output);
        static::assertStringContainsString("waffle_seconds 0.012\n", $output);
        static::assertStringContainsString('waffle_quote{note="a\"b"} 1', $output);
    }

    private function collector(MetricSample ...$samples): MetricsCollectorInterface
    {
        return new class($samples) implements MetricsCollectorInterface {
            /** @param array<array-key, MetricSample> $samples */
            public function __construct(
                private readonly array $samples,
            ) {}

            #[\Override]
            public function collect(): iterable
            {
                yield from $this->samples;
            }
        };
    }
}
