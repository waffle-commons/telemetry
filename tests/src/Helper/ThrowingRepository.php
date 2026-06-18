<?php

declare(strict_types=1);

namespace WaffleTests\Commons\Telemetry\Helper;

use Generator;
use RuntimeException;
use stdClass;
use Waffle\Commons\Contracts\Data\Query\QueryInterface;
use Waffle\Commons\Contracts\Data\Repository\RepositoryInterface;

/**
 * Repository test double whose every read fails, to exercise span error paths.
 *
 * @implements RepositoryInterface<stdClass>
 */
final class ThrowingRepository implements RepositoryInterface
{
    /** @return list<stdClass> */
    #[\Override]
    public function find(QueryInterface $query): array
    {
        throw new RuntimeException('boom');
    }

    #[\Override]
    public function findOne(QueryInterface $query): ?object
    {
        throw new RuntimeException('boom');
    }

    /** @return Generator<int, stdClass> */
    #[\Override]
    public function stream(QueryInterface $query): Generator
    {
        yield from [];

        throw new RuntimeException('boom');
    }
}
