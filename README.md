[![Discord](https://img.shields.io/discord/755288001592033391?logo=discord)](https://discord.gg/eKgywnfXr2)
[![PHP Version Require](http://poser.pugx.org/waffle-commons/telemetry/require/php)](https://packagist.org/packages/waffle-commons/telemetry)
[![PHP CI](https://github.com/waffle-commons/telemetry/actions/workflows/main.yml/badge.svg)](https://github.com/waffle-commons/telemetry/actions/workflows/main.yml)
[![codecov](https://codecov.io/gh/waffle-commons/telemetry/graph/badge.svg)](https://codecov.io/gh/waffle-commons/telemetry)
[![Latest Stable Version](http://poser.pugx.org/waffle-commons/telemetry/v)](https://packagist.org/packages/waffle-commons/telemetry)
[![Packagist License](https://img.shields.io/packagist/l/waffle-commons/telemetry)](https://github.com/waffle-commons/telemetry/blob/main/LICENSE.md)

Waffle Telemetry Component
===========================

> **Release:** `0.1.0-beta6` &nbsp;|&nbsp; [`CHANGELOG.md`](./CHANGELOG.md)
> **RFC:** RFC-005 (`OBS-02`) — contract-first, SDK-free observability

SDK-free enterprise telemetry for the [Waffle Commons](https://github.com/waffle-commons) framework: a
**Prometheus `/waffle-metrics`** exporter fed by **stateless worker-metric collectors** (memory, GC, DB-pool
utilization), plus request instrumentation that defaults to the contract's no-op tracer so it costs nothing
until a real backend is wired in. The OpenTelemetry SDK bridge lives in the separate
[`waffle-commons/telemetry-otel`](https://github.com/waffle-commons/telemetry-otel) package, so the vendor SDK
never enters this core perimeter.

## 📦 Installation

```bash
composer require waffle-commons/telemetry
```

## 🧱 Surface

| Class | Role |
| :--- | :--- |
| `Waffle\Commons\Telemetry\Metric\MetricsRegistry` | `final readonly` implementation of `MetricsRegistryInterface` **and** `MetricsCollectorInterface`. Records `increment()` / `gauge()` / `observe()` into a `MetricStoreInterface` and reads them back as `MetricSample`s for export. |
| `Waffle\Commons\Telemetry\Metric\ApcuMetricStore` | `final readonly` `MetricStoreInterface` backed by APCu shared memory — counters live off the worker heap so one scrape aggregates every worker on the instance. |
| `Waffle\Commons\Telemetry\Collector\MemoryCollector` | Stateless collector — current + peak real memory usage (`memory_get_usage(true)` / `memory_get_peak_usage(true)`). |
| `Waffle\Commons\Telemetry\Collector\GcCollector` | Stateless collector — cumulative GC cycle runs, objects collected, and current root-buffer size (`gc_status()`). |
| `Waffle\Commons\Telemetry\Collector\PoolUtilizationCollector` | Stateless collector — DB connection-pool active / idle / capacity from a bound `PoolStatsInterface` (reports zeros until a pool is wired in). |
| `Waffle\Commons\Telemetry\Exporter\PrometheusExporter` | `final readonly` — renders a set of collectors' samples into the Prometheus text exposition format (v0.0.4), no SDK required. |
| `Waffle\Commons\Telemetry\Middleware\MetricsMiddleware` | `final readonly` PSR-15 middleware serving the **fail-closed** `/waffle-metrics` scrape endpoint. |
| `Waffle\Commons\Telemetry\Middleware\TracingMiddleware` | `final readonly` PSR-15 middleware opening the per-request `SpanKind::Server` root span and recording request count + duration. |
| `Waffle\Commons\Telemetry\Cache\MeteredCache` | `final readonly` decorator — wraps any `Contracts\Cache\CacheInterface` to record hit/miss counters. |
| `Waffle\Commons\Telemetry\Repository\TracingRepositoryDecorator` | `final readonly` decorator — wraps any RFC-022 `RepositoryInterface` to emit a `waffle.db.query` client span per call. |

## 🔌 Wiring

Real wiring from the template `AppKernelFactory` (see `skeleton/src/Factory/AppKernelFactory.php`):

```php
use Waffle\Commons\Contracts\Telemetry\Metrics\MetricsCollectorInterface;
use Waffle\Commons\Contracts\Telemetry\Metrics\MetricsRegistryInterface;
use Waffle\Commons\Contracts\Telemetry\Metrics\NullMetricsRegistry;
use Waffle\Commons\Telemetry\Collector\GcCollector;
use Waffle\Commons\Telemetry\Collector\MemoryCollector;
use Waffle\Commons\Telemetry\Collector\PoolUtilizationCollector;
use Waffle\Commons\Telemetry\Exporter\PrometheusExporter;
use Waffle\Commons\Telemetry\Metric\ApcuMetricStore;
use Waffle\Commons\Telemetry\Metric\MetricsRegistry;
use Waffle\Commons\Telemetry\Middleware\MetricsMiddleware;
use Waffle\Commons\Telemetry\Middleware\TracingMiddleware;

// Counters in APCu shared memory (never the worker heap); no-op fallback without APCu.
$metricsRegistry = apcu_enabled() ? new MetricsRegistry(new ApcuMetricStore()) : new NullMetricsRegistry();
$container->set(MetricsRegistryInterface::class, $metricsRegistry);

$collectors = [new MemoryCollector(), new GcCollector(), new PoolUtilizationCollector()];
if ($metricsRegistry instanceof MetricsCollectorInterface) {
    $collectors[] = $metricsRegistry; // the registry is itself a collector — export its own counters too
}

// Placed early: /waffle-metrics short-circuits before the application pipeline and applies
// its own fail-closed security — localhost only by default; pass a bearer token to allow a
// remote scrape.
$stack->add(new MetricsMiddleware(
    new PrometheusExporter($collectors),
    $responseFactory,
    $streamFactory,
    bearerToken: null,
    allowedIps: ['127.0.0.1', '::1'],
));

// Opens the server root span (extracts an inbound `traceparent`) and records request
// count + duration; no-op cost while $tracer is the contract's NullTracer.
$stack->add(new TracingMiddleware($tracer, $metricsRegistry, $tracePropagator));
```

`$tracer` defaults to `Contracts\Telemetry\NullTracer` and `$tracePropagator` to
`Contracts\Telemetry\NullTextMapPropagator`. Swap in `waffle-commons/telemetry-otel`'s
`OtelTracerFactory::console(...)` (or an OTLP exporter) to activate real distributed tracing without touching
this package.

## 🛡️ Configuration — `/waffle-metrics` is fail-closed

`MetricsMiddleware` answers the scrape path **only** when the request presents the configured bearer token or
comes from an allow-listed client IP; every other request to that path gets a `404` — the endpoint's existence
is never revealed to an unauthorized caller (mirrors AXE 0 `LEAK-03`).

| Constructor argument | Default | Effect |
| :--- | :--- | :--- |
| `?string $bearerToken` | `null` | When set, a request with `Authorization: Bearer <token>` (compared via `hash_equals()`) is authorised. |
| `array $allowedIps` | `[]` | Exact `REMOTE_ADDR` values permitted to scrape (e.g. `['127.0.0.1', '::1']` for localhost-only). |

Both are opt-in allow-lists — an empty configuration means the endpoint accepts nothing, not everything.

## 📊 Collectors

| Metric(s) | Type | Source |
| :--- | :--- | :--- |
| `waffle_memory_usage_bytes`, `waffle_memory_peak_bytes` | Gauge | `MemoryCollector` — `memory_get_usage(true)` / `memory_get_peak_usage(true)`. |
| `waffle_gc_runs_total`, `waffle_gc_collected_total`, `waffle_gc_roots` | Counter / Counter / Gauge | `GcCollector` — `gc_status()`. |
| `waffle_db_pool_active`, `waffle_db_pool_idle`, `waffle_db_pool_capacity` | Gauge | `PoolUtilizationCollector` — a bound `PoolStatsInterface` (zeros until a pool is wired in). |
| `waffle_http_requests_total`, `waffle_http_request_duration_seconds` | Counter / Counter (`_sum`/`_count`) | `TracingMiddleware`, via `MetricsRegistryInterface`. |
| `waffle_cache_hits_total`, `waffle_cache_misses_total` | Counter | `MeteredCache`, judged with `has()` so the cached value is never captured into a `mixed` local. |

`MetricsRegistry::observe()` stores a summary (`<name>_sum` + `<name>_count`), so a mean is derivable
downstream without a histogram implementation.

## 🧭 Perimeter

Depends only on `waffle-commons/contracts`. Every telemetry interface (`Waffle\Commons\Contracts\Telemetry\*`)
lands in `contracts` first — `mago guard` enforces the boundary at `[guard.perimeter]` in
[`mago.toml`](./mago.toml); a forbidden `use` statement fails the build, not a reviewer. Production code under
`Waffle\Commons\Telemetry` may depend only on itself, `Contracts\**`, `Psr\**`, and PHP core / `Psl\**`. Test
code under `WaffleTests\Commons\Telemetry` is unrestricted (`@all`).

Contract-first, component-agnostic by construction: components compose through `waffle-commons/contracts`,
never directly through one another.

## 🧵 Worker safety

Every cumulative counter lives in `ApcuMetricStore` (APCu shared memory), **never on the resident worker
heap** — the only instance field on the store is its immutable key prefix. Collectors and decorators are all
`final readonly` and stateless per request, so the component passes the `igor-php` worker-mode audit with zero
findings (`wfl igor` 0 KO).

## 🧪 Testing

```bash
docker exec -w /waffle-commons/telemetry waffle-dev composer tests
```

## 📚 Documentation

Central framework docs (Diátaxis) for this component:

- Reference: [`reference/telemetry.md`](https://github.com/waffle-commons/documentation/blob/main/reference/telemetry.md)
- Explanation: [`explanation/observability-telemetry.md`](https://github.com/waffle-commons/documentation/blob/main/explanation/observability-telemetry.md)
- Full documentation tree: [waffle-commons/documentation](https://github.com/waffle-commons/documentation)

## 📄 License

MIT — see [LICENSE.md](./LICENSE.md).

