<?php

declare(strict_types=1);

namespace Waffle\Commons\Telemetry\Profiler;

use Waffle\Commons\Contracts\Service\ResettableInterface;
use Waffle\Commons\Contracts\Telemetry\Enum\SpanKind;
use Waffle\Commons\Contracts\Telemetry\SpanContextInterface;
use Waffle\Commons\Contracts\Telemetry\SpanInterface;
use Waffle\Commons\Contracts\Telemetry\TracerInterface;

/**
 * Dev profiler ([DXP-01]): a tracer decorator that counts the database queries
 * of the current request. The data layer opens one `waffle.db.query` span per
 * query (`Waffle\Commons\Data\Telemetry\QueryTracer`, the source of that name),
 * so the count is what the traces show: queries run through a repository wired
 * with this tracer. Raw PDO on a pooled connection opens no span and is not
 * counted.
 *
 * Every span still goes to the decorated tracer. The count is per request:
 * {@see self::reset()} clears it with the container's reset, and forwards the
 * reset to the decorated tracer, which this one replaces in the container.
 */
final class QueryCountingTracer implements TracerInterface, ResettableInterface
{
    /** The span name the data layer gives each query. */
    public const string QUERY_SPAN = 'waffle.db.query';

    private int $queries = 0;

    public function __construct(
        private readonly TracerInterface $tracer,
    ) {}

    #[\Override]
    public function startSpan(
        string $name,
        SpanKind $kind = SpanKind::Internal,
        ?SpanContextInterface $parent = null,
    ): SpanInterface {
        if ($name === self::QUERY_SPAN) {
            ++$this->queries;
        }

        return $this->tracer->startSpan($name, $kind, $parent);
    }

    #[\Override]
    public function currentContext(): ?SpanContextInterface
    {
        return $this->tracer->currentContext();
    }

    /** Queries counted since the last reset, that is during the current request. */
    public function queryCount(): int
    {
        return $this->queries;
    }

    #[\Override]
    public function reset(): void
    {
        $this->queries = 0;
        if ($this->tracer instanceof ResettableInterface) {
            $this->tracer->reset();
        }
    }
}
