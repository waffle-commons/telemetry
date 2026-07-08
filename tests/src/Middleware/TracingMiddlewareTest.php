<?php

declare(strict_types=1);

namespace WaffleTests\Commons\Telemetry\Middleware;

use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;
use Waffle\Commons\Contracts\Telemetry\Enum\SpanKind;
use Waffle\Commons\Contracts\Telemetry\NullSpan;
use Waffle\Commons\Contracts\Telemetry\NullSpanContext;
use Waffle\Commons\Contracts\Telemetry\NullTracer;
use Waffle\Commons\Contracts\Telemetry\SpanContextInterface;
use Waffle\Commons\Contracts\Telemetry\SpanInterface;
use Waffle\Commons\Contracts\Telemetry\TextMapPropagatorInterface;
use Waffle\Commons\Contracts\Telemetry\TracerInterface;
use Waffle\Commons\Telemetry\Metric\ApcuMetricStore;
use Waffle\Commons\Telemetry\Metric\MetricsRegistry;
use Waffle\Commons\Telemetry\Middleware\TracingMiddleware;
use WaffleTests\Commons\Telemetry\AbstractTestCase;

use function apcu_clear_cache;
use function apcu_enabled;

#[CoversClass(TracingMiddleware::class)]
final class TracingMiddlewareTest extends AbstractTestCase
{
    private Psr17Factory $psr17;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        if (!apcu_enabled()) {
            self::markTestSkipped('APCu is not enabled in this environment.');
        }
        apcu_clear_cache();
        $this->psr17 = new Psr17Factory();
    }

    public function testRecordsRequestMetricsAndReturnsTheResponse(): void
    {
        $registry = new MetricsRegistry(new ApcuMetricStore('t:'));
        $middleware = new TracingMiddleware(new NullTracer(), $registry);

        $handler = new class($this->psr17) implements RequestHandlerInterface {
            public function __construct(
                private readonly Psr17Factory $psr17,
            ) {}

            #[\Override]
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return $this->psr17->createResponse(200);
            }
        };

        $response = $middleware->process($this->psr17->createServerRequest('GET', '/x'), $handler);

        static::assertSame(200, $response->getStatusCode());
        $metrics = $this->collect($registry);
        static::assertSame(1.0, $metrics['waffle_http_requests_total'] ?? null);
        static::assertSame(1.0, $metrics['waffle_http_request_duration_seconds_count'] ?? null);
    }

    public function testPropagatesHandlerExceptionAfterRecordingMetrics(): void
    {
        $registry = new MetricsRegistry(new ApcuMetricStore('t:'));
        $middleware = new TracingMiddleware(new NullTracer(), $registry);

        $handler = new class implements RequestHandlerInterface {
            #[\Override]
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                throw new RuntimeException('boom');
            }
        };

        $caught = false;
        try {
            $middleware->process($this->psr17->createServerRequest('POST', '/x'), $handler);
        } catch (RuntimeException) {
            $caught = true;
        }

        static::assertTrue($caught, 'the handler exception must propagate');
        static::assertSame(1.0, $this->collect($registry)['waffle_http_requests_total'] ?? null);
    }

    public function testContinuesAnInboundTraceContextAsTheSpanParent(): void
    {
        $remoteParent = new NullSpanContext();
        $propagator = new class($remoteParent) implements TextMapPropagatorInterface {
            public function __construct(
                private readonly SpanContextInterface $parent,
            ) {}

            /**
             * @param array<string, string> $carrier
             * @param-out array<string, string> $carrier
             */
            #[\Override]
            public function inject(SpanContextInterface $context, array &$carrier): void
            {
                // no-op: this double only exercises extraction.
            }

            /**
             * @param array<string, string> $carrier
             */
            #[\Override]
            public function extract(array $carrier): ?SpanContextInterface
            {
                return ($carrier['traceparent'] ?? '') !== '' ? $this->parent : null;
            }
        };
        $tracer = new class implements TracerInterface {
            public ?SpanContextInterface $capturedParent = null;

            #[\Override]
            public function startSpan(
                string $name,
                SpanKind $kind = SpanKind::Internal,
                ?SpanContextInterface $parent = null,
            ): SpanInterface {
                $this->capturedParent = $parent;

                return new NullSpan();
            }

            #[\Override]
            public function currentContext(): ?SpanContextInterface
            {
                return null;
            }
        };

        $registry = new MetricsRegistry(new ApcuMetricStore('t:'));
        $middleware = new TracingMiddleware($tracer, $registry, $propagator);
        $handler = new class($this->psr17) implements RequestHandlerInterface {
            public function __construct(
                private readonly Psr17Factory $psr17,
            ) {}

            #[\Override]
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return $this->psr17->createResponse(200);
            }
        };

        // Inbound traceparent + tracestate ⇒ both forwarded; the extracted context parents the span.
        $withState = $this->psr17
            ->createServerRequest('GET', '/x')
            ->withHeader('traceparent', '00-0af7651916cd43dd8448eb211c80319c-b7ad6b7169203331-01')
            ->withHeader('tracestate', 'vendor=value');
        $middleware->process($withState, $handler);
        static::assertSame($remoteParent, $tracer->capturedParent);

        // Inbound traceparent only (no tracestate) ⇒ still parented off the extracted context.
        $tracer->capturedParent = null;
        $withParentOnly = $this->psr17->createServerRequest('GET', '/x')->withHeader(
            'traceparent',
            '00-0af7651916cd43dd8448eb211c80319c-b7ad6b7169203331-01',
        );
        $middleware->process($withParentOnly, $handler);
        static::assertSame($remoteParent, $tracer->capturedParent);

        // No traceparent ⇒ a fresh root span (null parent); the propagator is not consulted.
        $middleware->process($this->psr17->createServerRequest('GET', '/y'), $handler);
        static::assertNull($tracer->capturedParent);
    }

    /** @return array<string, float> */
    private function collect(MetricsRegistry $registry): array
    {
        $metrics = [];
        foreach ($registry->collect() as $sample) {
            $metrics[$sample->name] = $sample->value;
        }

        return $metrics;
    }
}
