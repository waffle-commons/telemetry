<?php

declare(strict_types=1);

namespace WaffleTests\Commons\Telemetry\Profiler;

use PHPUnit\Framework\Attributes\CoversClass;
use Waffle\Commons\Contracts\Telemetry\Enum\SpanKind;
use Waffle\Commons\Contracts\Telemetry\NullTracer;
use Waffle\Commons\Telemetry\Profiler\QueryCountingTracer;
use WaffleTests\Commons\Telemetry\AbstractTestCase;
use WaffleTests\Commons\Telemetry\Helper\RecordingTracer;

#[CoversClass(QueryCountingTracer::class)]
final class QueryCountingTracerTest extends AbstractTestCase
{
    public function testCountsQuerySpansOnlyAndForwardsEverySpan(): void
    {
        $inner = new RecordingTracer();
        $tracer = new QueryCountingTracer($inner);

        $tracer->startSpan(QueryCountingTracer::QUERY_SPAN, SpanKind::Client);
        $tracer->startSpan('http.request', SpanKind::Server);
        $tracer->startSpan('waffle.db.query', SpanKind::Client);

        static::assertSame(2, $tracer->queryCount());
        static::assertSame(['waffle.db.query', 'http.request', 'waffle.db.query'], $inner->started);
    }

    public function testResetStartsTheNextRequestAtZeroAndResetsTheDecoratedTracer(): void
    {
        $inner = new RecordingTracer();
        $tracer = new QueryCountingTracer($inner);
        $tracer->startSpan(QueryCountingTracer::QUERY_SPAN);

        $tracer->reset();

        static::assertSame(0, $tracer->queryCount());
        static::assertSame(1, $inner->resets);
    }

    public function testDelegatesTheCurrentContext(): void
    {
        $tracer = new QueryCountingTracer(new NullTracer());

        static::assertNull($tracer->currentContext());

        $tracer->reset(); // a decorated tracer that is not resettable is left alone
        static::assertSame(0, $tracer->queryCount());
    }
}
