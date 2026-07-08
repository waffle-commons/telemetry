<?php

declare(strict_types=1);

namespace Waffle\Commons\Telemetry\Exporter;

use Waffle\Commons\Contracts\Telemetry\Metrics\MetricSample;
use Waffle\Commons\Contracts\Telemetry\Metrics\MetricsCollectorInterface;

use function array_key_exists;
use function implode;
use function rtrim;
use function sprintf;
use function str_replace;

/**
 * Renders the samples from a set of collectors into the Prometheus text exposition
 * format (v0.0.4) — no SDK required. `# HELP`/`# TYPE` are emitted once per metric
 * name; stateless.
 */
final readonly class PrometheusExporter
{
    public const string CONTENT_TYPE = 'text/plain; version=0.0.4; charset=utf-8';

    /** @param iterable<MetricsCollectorInterface> $collectors */
    public function __construct(
        private iterable $collectors,
    ) {}

    public function render(): string
    {
        /** @var array<string, true> $declared */
        $declared = [];
        $lines = [];

        foreach ($this->collectors as $collector) {
            foreach ($collector->collect() as $sample) {
                if (!array_key_exists($sample->name, $declared)) {
                    $declared[$sample->name] = true;
                    if ($sample->help !== '') {
                        $lines[] = '# HELP ' . $sample->name . ' ' . $this->escape($sample->help);
                    }
                    $lines[] = '# TYPE ' . $sample->name . ' ' . $sample->type->value;
                }

                $lines[] = $sample->name . $this->labels($sample) . ' ' . $this->value($sample->value);
            }
        }

        return implode("\n", $lines) . "\n";
    }

    private function labels(MetricSample $sample): string
    {
        if ($sample->labels === []) {
            return '';
        }

        $parts = [];
        foreach ($sample->labels as $name => $value) {
            $parts[] = $name . '="' . $this->escape($value) . '"';
        }

        return '{' . implode(',', $parts) . '}';
    }

    private function escape(string $value): string
    {
        return str_replace(['\\', "\n", '"'], ['\\\\', '\\n', '\\"'], $value);
    }

    private function value(float $value): string
    {
        // Fixed notation, then trim trailing zeros: 42.0 → "42", 0.012000 → "0.012",
        // 0.0 → "0" (the integer part guarantees a non-empty result).
        return rtrim(rtrim(sprintf('%.6f', $value), characters: '0'), characters: '.');
    }
}
