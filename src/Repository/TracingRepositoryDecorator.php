<?php

declare(strict_types=1);

namespace Waffle\Commons\Telemetry\Repository;

use Generator;
use Throwable;
use Waffle\Commons\Contracts\Data\Query\QueryInterface;
use Waffle\Commons\Contracts\Data\Repository\RepositoryInterface;
use Waffle\Commons\Contracts\Telemetry\Enum\SpanKind;
use Waffle\Commons\Contracts\Telemetry\Enum\SpanStatus;
use Waffle\Commons\Contracts\Telemetry\NullTracer;
use Waffle\Commons\Contracts\Telemetry\SpanInterface;
use Waffle\Commons\Contracts\Telemetry\TracerInterface;

/**
 * Decorates any RFC-022 repository to emit a `waffle.db.query` CLIENT span around
 * each read, so database calls show up in distributed traces (OBS-01). Defaults to
 * the no-op tracer; stateless and holds no record state, so it stays safe across
 * resident-worker requests.
 *
 * @template T of object
 * @implements RepositoryInterface<T>
 */
final readonly class TracingRepositoryDecorator implements RepositoryInterface
{
    /** @param RepositoryInterface<T> $inner */
    public function __construct(
        private RepositoryInterface $inner,
        private TracerInterface $tracer = new NullTracer(),
        private string $system = 'sql',
    ) {}

    /**
     * @return list<T>
     * @throws Throwable
     */
    #[\Override]
    public function find(QueryInterface $query): array
    {
        $span = $this->span('find');
        try {
            return $this->inner->find($query);
        } catch (Throwable $error) {
            $this->fail($span, $error);
        } finally {
            $span->end();
        }
    }

    /**
     * @return T|null
     * @throws Throwable
     */
    #[\Override]
    public function findOne(QueryInterface $query): ?object
    {
        $span = $this->span('findOne');
        try {
            return $this->inner->findOne($query);
        } catch (Throwable $error) {
            $this->fail($span, $error);
        } finally {
            $span->end();
        }
    }

    /**
     * @return Generator<int, T>
     * @throws Throwable
     */
    #[\Override]
    public function stream(QueryInterface $query): Generator
    {
        $span = $this->span('stream');
        try {
            yield from $this->inner->stream($query);
        } catch (Throwable $error) {
            $this->fail($span, $error);
        } finally {
            $span->end();
        }
    }

    private function span(string $operation): SpanInterface
    {
        $span = $this->tracer->startSpan('waffle.db.query', SpanKind::Client);
        $span->setAttribute('db.system', $this->system);
        $span->setAttribute('db.operation', $operation);

        return $span;
    }

    /**
     * @throws Throwable Always — records the error on the span and re-throws.
     */
    private function fail(SpanInterface $span, Throwable $error): never
    {
        $span->recordException($error);
        $span->setStatus(SpanStatus::Error);

        throw $error;
    }
}
