<?php

declare(strict_types=1);

namespace Waffle\Commons\Telemetry\Middleware;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Waffle\Commons\Telemetry\Exporter\PrometheusExporter;
use Waffle\Commons\Telemetry\Support\Coerce;

use function hash_equals;
use function in_array;

/**
 * Serves the Prometheus scrape endpoint at `/waffle-metrics`. **Fail-closed:** a
 * request is answered ONLY when it presents the configured bearer token or comes
 * from an allow-listed client IP; otherwise it gets a `404` (the endpoint's
 * existence is never revealed, mirroring AXE 0 LEAK-03). Every other path passes
 * straight through. Stateless.
 */
final readonly class MetricsMiddleware implements MiddlewareInterface
{
    public const string PATH = '/waffle-metrics';

    /** @param list<string> $allowedIps Exact `REMOTE_ADDR` values permitted to scrape. */
    public function __construct(
        private PrometheusExporter $exporter,
        private ResponseFactoryInterface $responseFactory,
        private StreamFactoryInterface $streamFactory,
        #[\SensitiveParameter]
        private ?string $bearerToken = null,
        private array $allowedIps = [],
    ) {}

    /** @throws \InvalidArgumentException From the PSR-17 factories on an invalid status code or stream. */
    #[\Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($request->getUri()->getPath() !== self::PATH) {
            return $handler->handle($request);
        }

        if (!$this->authorised($request)) {
            return $this->responseFactory->createResponse(404);
        }

        return $this->responseFactory
            ->createResponse(200)
            ->withHeader('Content-Type', PrometheusExporter::CONTENT_TYPE)
            ->withBody($this->streamFactory->createStream($this->exporter->render()));
    }

    private function authorised(ServerRequestInterface $request): bool
    {
        return $this->tokenMatches($request) || $this->ipAllowed($request);
    }

    private function tokenMatches(ServerRequestInterface $request): bool
    {
        if ($this->bearerToken === null) {
            return false;
        }

        $presented = $request->getHeaderLine('Authorization');

        return $presented !== '' && hash_equals('Bearer ' . $this->bearerToken, $presented);
    }

    private function ipAllowed(ServerRequestInterface $request): bool
    {
        if ($this->allowedIps === []) {
            return false;
        }

        $remote = Coerce::toString($request->getServerParams()['REMOTE_ADDR'] ?? null);

        return in_array($remote, $this->allowedIps, true);
    }
}
