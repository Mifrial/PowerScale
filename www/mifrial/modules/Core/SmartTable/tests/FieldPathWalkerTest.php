<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Tests;

use Mifrial\Core\SmartTable\Exception\Map\MapInvalidException;
use Mifrial\Core\SmartTable\Service\Query\FieldPathWalker;
use Mifrial\Core\SmartTable\Tests\Fixture\PathChildTable;
use Mifrial\Core\SmartTable\Tests\Fixture\PathParentTable;
use PHPUnit\Framework\TestCase;

final class FieldPathWalkerTest extends TestCase
{
    /**
     * Hop только reference; лист любой; повтор стола законен.
     *
     * @return void
     */
    public function testResolveHopsAndRejects(): void
    {
        $walker = new FieldPathWalker();
        $childTable = new PathChildTable();
        $activePath = $walker->resolve($childTable, 'parent_id.active');
        self::assertSame('bool', $activePath->leafField()->type());
        self::assertSame((new PathParentTable())->getName(), $activePath->leafTable()->getName());
        $twoHop = $walker->resolve($childTable, 'parent_id.owner_id.login');
        self::assertSame('login', $twoHop->leafField()->name());
        $tagsPath = $walker->resolve($childTable, 'parent_id.tags');
        self::assertTrue($tagsPath->leafField()->settings()->multiple());
        foreach (['parent_id.nope', 'parent_id.tags.x', 'title.active'] as $invalidPath) {
            try {
                $walker->resolve($childTable, $invalidPath);
                self::fail('walker must reject ' . $invalidPath);
            } catch (MapInvalidException $exception) {
                self::assertSame('MAP_INVALID', $exception->getErrorCode());
            }
        }
    }
}
