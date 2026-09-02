<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Tests;

use Mifrial\Core\SmartTable\Service\Query\FieldPathWalker;
use Mifrial\Core\SmartTable\Service\Query\PathListHydrator;
use Mifrial\Core\SmartTable\Tests\Fixture\PathChildTable;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class PathListHydratorTest extends TestCase
{
    /**
     * Пустой FK: скаляр пути — null, не [].
     *
     * @return void
     */
    public function testNullScalarPathHydratesToNull(): void
    {
        $reflection = new ReflectionClass(PathListHydrator::class);
        $hydrator = $reflection->newInstanceWithoutConstructor();
        $reflection->getProperty('fieldPathWalker')->setValue($hydrator, new FieldPathWalker());
        $hydratedRow = $hydrator->hydratePaths(
            ['parent_id.active' => null],
            [],
            new PathChildTable(),
            ['parent_id.active'],
        );
        self::assertNull($hydratedRow['parent_id.active']);
    }
}
