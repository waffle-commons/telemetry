<?php

declare(strict_types=1);

namespace WaffleTests\Commons\Telemetry\Middleware;

use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Waffle\Commons\Contracts\Telemetry\NullTracer;
use Waffle\Commons\Telemetry\Middleware\ProfilerHeadersMiddleware;
use Waffle\Commons\Telemetry\Profiler\QueryCountingTracer;
use WaffleTests\Commons\Telemetry\AbstractTestCase;

#[CoversClass(ProfilerHeadersMiddleware::class)]
final class ProfilerHeadersMiddlewareTest extends AbstractTestCase
{
    public function testAddsTimeMemoryServerTimingAndTheRequestsQueryCount(): void
    {
        $queries = new QueryCountingTracer(new NullTracer());
        $handler = $this->handler(static function () use ($queries): void {
            $queries->startSpan(QueryCountingTracer::QUERY_SPAN);
            $queries->startSpan(QueryCountingTracer::QUERY_SPAN);
        });

        $response = new ProfilerHeadersMiddleware($queries)->process($this->request(), $handler);

        static::assertSame(201, $response->getStatusCode());
        static::assertMatchesRegularExpression('/^\d+\.\dms$/', $response->getHeaderLine('X-Waffle-Time'));
        static::assertMatchesRegularExpression('/^\d+\.\dMiB$/', $response->getHeaderLine('X-Waffle-Memory'));
        static::assertMatchesRegularExpression('/^app;dur=\d+\.\d$/', $response->getHeaderLine('Server-Timing'));
        static::assertSame('2', $response->getHeaderLine('X-Waffle-Queries'));
    }

    public function testLeavesTheQueryCountOutWhenNoCountingTracerIsWired(): void
    {
        $response = new ProfilerHeadersMiddleware()->process($this->request(), $this->handler(static function (): void {}));

        static::assertFalse($response->hasHeader('X-Waffle-Queries'));
        static::assertTrue($response->hasHeader('X-Waffle-Time'));
    }

    private function request(): ServerRequestInterface
    {
        return new Psr17Factory()->createServerRequest('GET', '/');
    }

    /**
     * @param \Closure(): void $work What the inner handler does before answering.
     */
    private function handler(\Closure $work): RequestHandlerInterface
    {
        return new readonly class($work) implements RequestHandlerInterface {
            /**
             * @param \Closure(): void $work
             */
            public function __construct(
                private \Closure $work,
            ) {}

            #[\Override]
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                ($this->work)();

                return new Psr17Factory()->createResponse(201);
            }
        };
    }
}
