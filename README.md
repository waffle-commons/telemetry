[![Discord](https://img.shields.io/discord/755288001592033391?logo=discord)](https://discord.gg/eKgywnfXr2)
[![PHP Version Require](http://poser.pugx.org/waffle-commons/telemetry/require/php)](https://packagist.org/packages/waffle-commons/telemetry)
[![PHP CI](https://github.com/waffle-commons/telemetry/actions/workflows/main.yml/badge.svg)](https://github.com/waffle-commons/telemetry/actions/workflows/main.yml)
[![codecov](https://codecov.io/gh/waffle-commons/telemetry/graph/badge.svg)](https://codecov.io/gh/waffle-commons/telemetry)
[![Latest Stable Version](http://poser.pugx.org/waffle-commons/telemetry/v)](https://packagist.org/packages/waffle-commons/telemetry)
[![Packagist License](https://img.shields.io/packagist/l/waffle-commons/telemetry)](https://github.com/waffle-commons/telemetry/blob/main/LICENSE.md)

# Waffle Commons — Telemetry

SDK-free enterprise telemetry for the [Waffle Commons](https://github.com/waffle-commons) framework. This
package provides the **no-op tracing defaults**, a **Prometheus `/waffle-metrics`** exporter + diagnostics
middleware, and **stateless worker-metric collectors** (memory peaks, GC cycles, request timing, cache hit
ratio, DB-pool utilization) for long-running FrankenPHP workers.

> **Status:** shipped in **Beta 5 / AXE 5 (RFC-005)**. The OpenTelemetry SDK bridge lives in the separate
> [`waffle-commons/telemetry-otel`](https://github.com/waffle-commons/telemetry-otel) package so the vendor
> SDK never enters the core perimeter.

## What's inside

- **Metrics** — `Metric\MetricsRegistry` (counters / gauges / histograms) backed by `Metric\ApcuMetricStore`,
  so counters live in APCu shared memory and never on the worker heap. Falls back to the contract's
  `NullMetricsRegistry` when APCu is unavailable.
- **Collectors** — stateless `Collector\MemoryCollector` (usage + peak), `Collector\GcCollector` (cycles,
  collected objects, root buffer) and `Collector\PoolUtilizationCollector` (DB-pool active / idle / capacity).
- **Exporter & endpoint** — `Exporter\PrometheusExporter` renders the text exposition format;
  `Middleware\MetricsMiddleware` serves a **fail-closed** `/waffle-metrics` scrape (404 unless a bearer token
  or allow-listed client IP matches — the endpoint is never revealed to unauthorized callers).
- **Request instrumentation** — `Middleware\TracingMiddleware` opens the per-request server span (extracting an
  inbound W3C `traceparent`) and records request count + duration; defaults to the contract no-ops so it is safe
  to install unconditionally.
- **Decorators** — `Cache\MeteredCache` (hit / miss counters around any PSR-16 cache) and
  `Repository\TracingRepositoryDecorator` (optional client spans around an RFC-022 repository; core `data/` now
  emits DB spans natively, so this decorator is an add-on for non-core repositories).

## Perimeter

Depends only on `waffle-commons/contracts` (+ `waffle-commons/utils`). Every telemetry interface
(`Waffle\Commons\Contracts\Telemetry\*`) lands in `contracts` first — `mago guard` enforces the boundary.
Counters live in shared memory (APCu), never on the resettable worker heap (`wfl igor` 0 KO).

## Development

```shell
composer install
composer mago     # fmt + lint + analyze + guard — must be ZERO output
composer tests    # PHPUnit 12.5, >=95% coverage
composer igor     # worker-safety audit — 0 KO
```

## License

MIT — see [LICENSE.md](./LICENSE.md).
