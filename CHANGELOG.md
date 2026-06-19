# Changelog — waffle-commons/telemetry

All notable changes to this component are documented in this file.
The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and the project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).
Released in lockstep with the Waffle Commons umbrella tag.

## [0.1.0-beta5] — 2026-06-19

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
