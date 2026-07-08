<?php

declare(strict_types=1);

namespace WaffleTests\Commons\Telemetry\Repository;

use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;
use stdClass;
use Waffle\Commons\Contracts\Data\Query\QueryInterface;
use Waffle\Commons\Telemetry\Repository\TracingRepositoryDecorator;
use WaffleTests\Commons\Telemetry\AbstractTestCase;
use WaffleTests\Commons\Telemetry\Helper\FixedRepository;
use WaffleTests\Commons\Telemetry\Helper\ThrowingRepository;

use function iterator_to_array;

#[CoversClass(TracingRepositoryDecorator::class)]
final class TracingRepositoryDecoratorTest extends AbstractTestCase
{
    public function testDelegatesReadsToTheInnerRepository(): void
    {
        $dto = new stdClass();
        $decorator = new TracingRepositoryDecorator(new FixedRepository($dto));
        $query = $this->query();

        static::assertSame([$dto], $decorator->find($query));
        static::assertSame($dto, $decorator->findOne($query));
        static::assertSame([$dto], iterator_to_array($decorator->stream($query)));
    }

    public function testFindRecordsTheErrorAndRethrows(): void
    {
        $this->expectException(RuntimeException::class);

        new TracingRepositoryDecorator(new ThrowingRepository())->find($this->query());
    }

    public function testFindOneRecordsTheErrorAndRethrows(): void
    {
        $this->expectException(RuntimeException::class);

        new TracingRepositoryDecorator(new ThrowingRepository())->findOne($this->query());
    }

    public function testStreamRecordsTheErrorAndRethrows(): void
    {
        $this->expectException(RuntimeException::class);

        iterator_to_array(new TracingRepositoryDecorator(new ThrowingRepository())->stream($this->query()));
    }

    private function query(): QueryInterface
    {
        return new class implements QueryInterface {
            /** @var list<string> */
            public array $fields = [];
            public ?string $from = null;
            /** @var list<\Waffle\Commons\Contracts\Data\Query\ComparisonInterface> */
            public array $criteria = [];
            /** @var list<\Waffle\Commons\Contracts\Data\Query\OrderInterface> */
            public array $orderings = [];
            public ?int $limit = null;
            public ?int $offset = null;
        };
    }
}
