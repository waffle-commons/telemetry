# Changelog — waffle-commons/telemetry

All notable changes to this component are documented in this file.
The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and the project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).
Released in lockstep with the Waffle Commons umbrella tag.

## [Unreleased]

### Added
- **Dev profiler headers (DXP-01, RFC-018 §3.2):** `Middleware\ProfilerHeadersMiddleware` adds `X-Waffle-Time` (ms), `X-Waffle-Memory` (current usage in MiB, never the process-wide peak `/waffle-metrics` reports) and `Server-Timing: app;dur=…` to every response, plus `X-Waffle-Queries` when given a `Profiler\QueryCountingTracer`. That tracer decorates the registered `TracerInterface` and counts the `waffle.db.query` spans the data layer opens per query; it implements `ResettableInterface`, so the count is per request. Raw PDO on a pooled connection opens no span and is not counted. Debug only: the headers disclose internals (RFC-018 §4).

## [0.1.0-beta6] — 2026-09

**Theme: documentation.**

### Changed
- `composer.json` gains a `suggest` entry for `ext-apcu`, the extension `ApcuMetricStore` needs. It was previously an undeclared runtime requirement.

### Fixed
- README corrected: the dependency perimeter is contracts-only — no `utils` requirement exists in `composer.json`, `mago.toml` or any import.

### Documentation
- The README now links into the central Diátaxis documentation tree (DOC-02).

## [0.1.0-beta5] — 2026-07-08

**Theme: enterprise telemetry & worker metrics (AXE 5 / RFC-005).**

### Added
- `Metric\MetricsRegistry` (counters / gauges / histograms) over `Metric\ApcuMetricStore`, keeping all metric
  state in APCu shared memory rather than on the resettable worker heap; falls back to the contract
  `NullMetricsRegistry` when APCu is unavailable (OBS-02).
- Stateless collectors: `Collector\MemoryCollector`, `Collector\GcCollector`, `Collector\PoolUtilizationCollector` (OBS-02).
- `Exporter\PrometheusExporter` (text exposition format) and the fail-closed `Middleware\MetricsMiddleware`
  serving `/waffle-metrics` — 404 unless a bearer token or allow-listed client IP matches, so the endpoint is
  never disclosed to unauthorized callers (OBS-02).
- `Middleware\TracingMiddleware` opens the per-request server span (extracting an inbound W3C `traceparent`) and
  records request count + duration; defaults to the contract no-ops so it is safe to install unconditionally (OBS-01).
- Decorators `Cache\MeteredCache` (cache hit / miss counters) and `Repository\TracingRepositoryDecorator`
  (optional client spans for non-core repositories; core `data/` emits DB spans natively).
- `Support\Coerce` mixed-narrowing helpers (no casts, no suppressions) for APCu / `json_decode` returns.

### Notes
- Depends only on `waffle-commons/contracts`; counters live in shared memory (`wfl igor` 0 KO).
