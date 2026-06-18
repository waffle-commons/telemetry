<?php

declare(strict_types=1);

namespace WaffleTests\Commons\Telemetry\Middleware;

use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Waffle\Commons\Contracts\Telemetry\Metrics\Enum\MetricType;
use Waffle\Commons\Contracts\Telemetry\Metrics\MetricSample;
use Waffle\Commons\Contracts\Telemetry\Metrics\MetricsCollectorInterface;
use Waffle\Commons\Telemetry\Exporter\PrometheusExporter;
use Waffle\Commons\Telemetry\Middleware\MetricsMiddleware;
use WaffleTests\Commons\Telemetry\AbstractTestCase;

#[CoversClass(MetricsMiddleware::class)]
final class MetricsMiddlewareTest extends AbstractTestCase
{
    private Psr17Factory $psr17;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->psr17 = new Psr17Factory();
    }

    public function testPassesThroughNonMetricsPaths(): void
    {
        $response = $this->middleware()->process(
            $this->psr17->createServerRequest('GET', '/app/home'),
            $this->handlerReturning(204),
        );

        static::assertSame(204, $response->getStatusCode());
    }

    public function testReturns404WhenUnauthorised(): void
    {
        $response = $this->middleware()->process(
            $this->psr17->createServerRequest('GET', '/waffle-metrics'),
            $this->handlerReturning(204),
        );

        static::assertSame(404, $response->getStatusCode());
    }

    public function testServesMetricsWithAValidBearerToken(): void
    {
        $request = $this->psr17->createServerRequest('GET', '/waffle-metrics')->withHeader(
            'Authorization',
            'Bearer s3cret',
        );

        $response = $this->middleware('s3cret')->process($request, $this->handlerReturning(204));

        static::assertSame(200, $response->getStatusCode());
        static::assertStringContainsString('text/plain', $response->getHeaderLine('Content-Type'));
        static::assertStringContainsString('waffle_demo_total', (string) $response->getBody());
    }

    public function testServesMetricsFromAnAllowedIp(): void
    {
        $request = $this->psr17->createServerRequest('GET', '/waffle-metrics', ['REMOTE_ADDR' => '10.0.0.5']);

        $response = $this->middleware(allowedIps: ['10.0.0.5'])->process($request, $this->handlerReturning(204));

        static::assertSame(200, $response->getStatusCode());
    }

    /** @param list<string> $allowedIps */
    private function middleware(
        #[\SensitiveParameter]
        ?string $bearerToken = null,
        array $allowedIps = [],
    ): MetricsMiddleware {
        $collector = new class implements MetricsCollectorInterface {
            #[\Override]
            public function collect(): iterable
            {
                yield new MetricSample('waffle_demo_total', MetricType::Counter, 1.0);
            }
        };

        return new MetricsMiddleware(
            new PrometheusExporter([$collector]),
            $this->psr17,
            $this->psr17,
            $bearerToken,
            $allowedIps,
        );
    }

    private function handlerReturning(int $status): RequestHandlerInterface
    {
        return new class($this->psr17, $status) implements RequestHandlerInterface {
            public function __construct(
                private readonly Psr17Factory $psr17,
                private readonly int $status,
            ) {}

            #[\Override]
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return $this->psr17->createResponse($this->status);
            }
        };
    }
}
