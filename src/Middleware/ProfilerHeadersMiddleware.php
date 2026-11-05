<?php

declare(strict_types=1);

namespace Waffle\Commons\Telemetry\Middleware;

use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Waffle\Commons\Telemetry\Profiler\QueryCountingTracer;

use function memory_get_usage;
use function microtime;
use function sprintf;

/**
 * Dev profiler headers ([DXP-01], RFC-018 §3.2): the time the request took, the
 * worker's memory once it is answered and its database queries, on every
 * response — no web toolbar, an API reads them with `curl -i`. `Server-Timing`
 * carries the time too, so browser devtools show it on their own.
 *
 * Debug only, never in production (RFC-018 §4): these headers disclose internals.
 * The application adds this middleware to its stack only in debug mode, as the
 * outermost one, so the time covers the whole pipeline and error responses carry
 * the headers as well.
 *
 * Memory is the current usage, not the peak: the peak is process-wide in a
 * worker, and resetting it per request would corrupt the peak `/waffle-metrics`
 * reports.
 */
final readonly class ProfilerHeadersMiddleware implements MiddlewareInterface
{
    public const string TIME_HEADER = 'X-Waffle-Time';

    public const string MEMORY_HEADER = 'X-Waffle-Memory';

    public const string QUERIES_HEADER = 'X-Waffle-Queries';

    public const string SERVER_TIMING_HEADER = 'Server-Timing';

    /**
     * @param ?QueryCountingTracer $queries The registered tracer, when it counts queries; without it
     *                                      `X-Waffle-Queries` is left out rather than reported as 0.
     */
    public function __construct(
        private ?QueryCountingTracer $queries = null,
    ) {}

    /** @throws \InvalidArgumentException From PSR-7 `withHeader()`; the names and values here are always valid. */
    #[Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $start = microtime(true);
        $response = $handler->handle($request);
        $milliseconds = (microtime(true) - $start) * 1000;

        $response = $response
            ->withHeader(self::TIME_HEADER, sprintf('%.1fms', $milliseconds))
            ->withHeader(self::MEMORY_HEADER, sprintf('%.1fMiB', memory_get_usage() / 1_048_576))
            ->withHeader(self::SERVER_TIMING_HEADER, sprintf('app;dur=%.1f', $milliseconds));

        if ($this->queries === null) {
            return $response;
        }

        return $response->withHeader(self::QUERIES_HEADER, (string) $this->queries->queryCount());
    }
}
