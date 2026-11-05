<?php

declare(strict_types=1);

namespace WaffleTests\Commons\Telemetry\Helper;

use Waffle\Commons\Contracts\Service\ResettableInterface;
use Waffle\Commons\Contracts\Telemetry\Enum\SpanKind;
use Waffle\Commons\Contracts\Telemetry\NullSpan;
use Waffle\Commons\Contracts\Telemetry\SpanContextInterface;
use Waffle\Commons\Contracts\Telemetry\SpanInterface;
use Waffle\Commons\Contracts\Telemetry\TracerInterface;

/**
 * A resettable tracer that records the names of the spans it starts and how
 * often it was reset, for decorator tests.
 */
final class RecordingTracer implements TracerInterface, ResettableInterface
{
    /** @var list<string> */
    public array $started = [];

    public int $resets = 0;

    #[\Override]
    public function startSpan(
        string $name,
        SpanKind $kind = SpanKind::Internal,
        ?SpanContextInterface $parent = null,
    ): SpanInterface {
        $this->started[] = $name;

        return new NullSpan();
    }

    #[\Override]
    public function currentContext(): ?SpanContextInterface
    {
        return null;
    }

    #[\Override]
    public function reset(): void
    {
        ++$this->resets;
    }
}
