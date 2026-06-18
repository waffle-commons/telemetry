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

> **Status:** scaffolding for **Beta 5 / AXE 5 (RFC-005)** — no implementation yet. The OpenTelemetry SDK
> bridge lives in the separate [`waffle-commons/telemetry-otel`](https://github.com/waffle-commons/telemetry-otel)
> package so the vendor SDK never enters the core perimeter.

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
