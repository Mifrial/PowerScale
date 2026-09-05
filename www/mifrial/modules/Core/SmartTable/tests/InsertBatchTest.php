<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Tests;

use Mifrial\Core\SmartTable\Dto\ListQuery;
use Mifrial\Core\SmartTable\Exception\Map\MapInvalidException;
use Mifrial\Core\SmartTable\Service\Query\InsertBatch;
use PHPUnit\Framework\TestCase;

final class InsertBatchTest extends TestCase
{
    /**
     * Пустой набор, не-list и 10001 — отказ без SQL.
     *
     * @return void
     */
    public function testAssertRowsRejectsShape(): void
    {
        $insertBatch = new InsertBatch();
        $tooLong = array_fill(0, ListQuery::MAX_LIMIT + 1, ['title' => 'a']);
        $invalidBatches = [
            [],
            [1 => ['title' => 'a']],
            $tooLong,
            [['title' => 'a'], 'nope'],
            [['title' => 'a'], [0 => 'x']],
        ];
        foreach ($invalidBatches as $rows) {
            try {
                $insertBatch->assertRows($rows);
                self::fail('invalid batch must fail');
            } catch (MapInvalidException $exception) {
                self::assertSame('MAP_INVALID', $exception->getErrorCode());
            }
        }
    }

    /**
     * List карт длины 1..max допустим.
     *
     * @return void
     */
    public function testAssertRowsAcceptsList(): void
    {
        $insertBatch = new InsertBatch();
        $insertBatch->assertRows([['title' => 'a'], ['title' => 'b']]);
        self::assertTrue(true);
    }
}
