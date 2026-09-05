<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Tests;

use Mifrial\Core\SmartTable\Dto\ListQuery;
use Mifrial\Core\SmartTable\Exception\Map\MapInvalidException;
use PHPUnit\Framework\TestCase;

final class ListQueryTest extends TestCase
{
    /**
     * Принимает синтаксически верный запрос и пустой [] у =.
     *
     * @return void
     */
    public function testFromOptionsAcceptsEmptyEqualsList(): void
    {
        $listQuery = ListQuery::fromOptions([
            'limit' => 10,
            'filter' => ['id' => []],
        ]);
        self::assertSame(10, $listQuery->limit());
        self::assertNotNull($listQuery->filter());
    }

    /**
     * Потолок getList — 10000; 501 допустим.
     *
     * @return void
     */
    public function testFromOptionsAcceptsLimit501AndMax(): void
    {
        self::assertSame(501, ListQuery::fromOptions(['limit' => 501])->limit());
        self::assertSame(
            ListQuery::MAX_LIMIT,
            ListQuery::fromOptions(['limit' => ListQuery::MAX_LIMIT])->limit(),
        );
        $direct = new ListQuery(null, [], 500, 0, false, null);
        self::assertSame(500, $direct->limit());
    }

    /**
     * Отказывает без limit, с 0, 10001 и неизвестным префиксом.
     *
     * @return void
     */
    public function testFromOptionsRejectsLimitAndPrefix(): void
    {
        $invalidOptions = [
            [],
            ['limit' => 0],
            ['limit' => ListQuery::MAX_LIMIT + 1],
            ['limit' => 10, 'filter' => ['~title' => 'a']],
        ];
        foreach ($invalidOptions as $options) {
            try {
                ListQuery::fromOptions($options);
                self::fail('invalid options must fail');
            } catch (MapInvalidException $exception) {
                self::assertSame('MAP_INVALID', $exception->getErrorCode());
            }
        }
    }

    /**
     * Отказывает >< не из двух элементов и мусорный LOGIC.
     *
     * @return void
     */
    public function testFromOptionsRejectsBetweenAndLogic(): void
    {
        try {
            ListQuery::fromOptions(['limit' => 1, 'filter' => ['><age' => [1]]]);
            self::fail('between arity must fail');
        } catch (MapInvalidException $exception) {
            self::assertSame('MAP_INVALID', $exception->getErrorCode());
        }

        try {
            ListQuery::fromOptions(['limit' => 1, 'filter' => ['LOGIC' => 'XOR', '=id' => 1]]);
            self::fail('logic must fail');
        } catch (MapInvalidException $exception) {
            self::assertSame('MAP_INVALID', $exception->getErrorCode());
        }
    }

    /**
     * Конструктор не обходит диапазон limit.
     *
     * @return void
     */
    public function testConstructorRejectsLimitZeroAndAboveMax(): void
    {
        foreach ([0, ListQuery::MAX_LIMIT + 1] as $limit) {
            try {
                new ListQuery(null, [], $limit, 0, false, null);
                self::fail('limit out of range must fail');
            } catch (MapInvalidException $exception) {
                self::assertSame('MAP_INVALID', $exception->getErrorCode());
            }
        }
    }
}
