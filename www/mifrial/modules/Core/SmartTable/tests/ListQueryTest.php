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
     * Отказывает без limit, с 0, 501 и неизвестным префиксом.
     *
     * @return void
     */
    public function testFromOptionsRejectsLimitAndPrefix(): void
    {
        foreach ([[], ['limit' => 0], ['limit' => 501], ['limit' => 10, 'filter' => ['~title' => 'a']]] as $options) {
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
    public function testConstructorRejectsLimitZero(): void
    {
        try {
            new ListQuery(null, [], 0, 0, false, null);
            self::fail('limit 0 must fail');
        } catch (MapInvalidException $exception) {
            self::assertSame('MAP_INVALID', $exception->getErrorCode());
        }
    }
}
