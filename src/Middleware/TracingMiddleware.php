<?php

declare(strict_types=1);

namespace Waffle\Commons\Telemetry\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;
use Waffle\Commons\Contracts\Telemetry\Enum\SpanKind;
use Waffle\Commons\Contracts\Telemetry\Enum\SpanStatus;
use Waffle\Commons\Contracts\Telemetry\Metrics\MetricsRegistryInterface;
use Waffle\Commons\Contracts\Telemetry\Metrics\NullMetricsRegistry;
use Waffle\Commons\Contracts\Telemetry\NullTextMapPropagator;
use Waffle\Commons\Contracts\Telemetry\NullTracer;
use Waffle\Commons\Contracts\Telemetry\SpanContextInterface;
use Waffle\Commons\Contracts\Telemetry\TextMapPropagatorInterface;
use Waffle\Commons\Contracts\Telemetry\TracerInterface;

use function microtime;

/**
 * Opens the per-request root span (`SpanKind::Server`) around the whole pipeline
 * and records request count + duration metrics. Defaults to the no-op tracer and
 * registry, so it is safe to install even when no backend is bound. Stateless —
 * cumulative state lives in the registry's shared store, never on the worker heap.
 */
final readonly class TracingMiddleware implements MiddlewareInterface
{
    public function __construct(
        private TracerInterface $tracer = new NullTracer(),
        private MetricsRegistryInterface $metrics = new NullMetricsRegistry(),
        private TextMapPropagatorInterface $propagator = new NullTextMapPropagator(),
    ) {}

    /**
     * @throws Throwable Re-thrown from the inner handler once the span and metrics
     *                   have been recorded (finish-request semantics).
     */
    #[\Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $method = $request->getMethod();
        $span = $this->tracer->startSpan('http.request', SpanKind::Server, $this->extractParent($request));
        $span->setAttribute('http.request.method', $method);
        $span->setAttribute('url.path', $request->getUri()->getPath());

        $start = microtime(true);
        $status = 500;
        try {
            $response = $handler->handle($request);
            $status = $response->getStatusCode();
            $span->setAttribute('http.response.status_code', $status);
            $span->setStatus($status >= 500 ? SpanStatus::Error : SpanStatus::Ok);

            return $response;
        } catch (Throwable $error) {
            $span->recordException($error);
            $span->setStatus(SpanStatus::Error);

            throw $error;
        } finally {
            $span->end();
            $this->record($method, $status, microtime(true) - $start);
        }
    }

    /**
     * Extracts an upstream W3C trace context from the inbound request headers, so this service
     * continues a caller's distributed trace (the downstream half of context propagation). An
     * absent `traceparent`, or the default no-op propagator, yields null ⇒ a fresh root span.
     */
    private function extractParent(ServerRequestInterface $request): ?SpanContextInterface
    {
        if (!$request->hasHeader('traceparent')) {
            return null;
        }

        $carrier = ['traceparent' => $request->getHeaderLine('traceparent')];
        if ($request->hasHeader('tracestate')) {
            $carrier['tracestate'] = $request->getHeaderLine('tracestate');
        }

        return $this->propagator->extract($carrier);
    }

    private function record(string $method, int $status, float $duration): void
    {
        $this->metrics->increment('waffle_http_requests_total', 1.0, [
            'method' => $method,
            'status' => (string) $status,
        ]);
        $this->metrics->observe('waffle_http_request_duration_seconds', $duration, ['method' => $method]);
    }
}
