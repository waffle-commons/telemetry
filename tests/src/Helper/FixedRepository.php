<?php

declare(strict_types=1);

namespace WaffleTests\Commons\Telemetry\Helper;

use Generator;
use stdClass;
use Waffle\Commons\Contracts\Data\Query\QueryInterface;
use Waffle\Commons\Contracts\Data\Repository\RepositoryInterface;

/**
 * Repository test double that always returns a single fixed DTO.
 *
 * @implements RepositoryInterface<stdClass>
 */
final class FixedRepository implements RepositoryInterface
{
    public function __construct(
        private readonly stdClass $dto,
    ) {}

    /** @return list<stdClass> */
    #[\Override]
    public function find(QueryInterface $query): array
    {
        return [$this->dto];
    }

    #[\Override]
    public function findOne(QueryInterface $query): ?object
    {
        return $this->dto;
    }

    /** @return Generator<int, stdClass> */
    #[\Override]
    public function stream(QueryInterface $query): Generator
    {
        yield $this->dto;
    }
}
