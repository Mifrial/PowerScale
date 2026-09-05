<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Tests;

use Mifrial\Core\Kernel\Value\DateTime as UnixDateTime;
use Mifrial\Core\SmartTable\Dto\AggregateQuery;
use Mifrial\Core\SmartTable\Dto\CountField;
use Mifrial\Core\SmartTable\Dto\ListQuery;
use Mifrial\Core\SmartTable\Dto\MaxField;
use Mifrial\Core\SmartTable\Dto\OuterColumn;
use Mifrial\Core\SmartTable\Dto\SubqueryValue;
use Mifrial\Core\SmartTable\Service\Cache\ListCacheKey;
use Mifrial\Core\SmartTable\Tests\Fixture\SampleTable;
use PHPUnit\Framework\TestCase;

final class ListCacheKeyTest extends TestCase
{
    /**
     * Разные DateTime в filter дают разные ключи.
     *
     * @return void
     */
    public function testDateTimeInFilterChangesKey(): void
    {
        $cacheKey = new ListCacheKey();
        $first = $cacheKey->make('users', ListQuery::fromOptions([
            'limit' => 10,
            'filter' => ['created' => UnixDateTime::fromUnix(1)],
        ]));
        $second = $cacheKey->make('users', ListQuery::fromOptions([
            'limit' => 10,
            'filter' => ['created' => UnixDateTime::fromUnix(2)],
        ]));
        self::assertNotSame($first, $second);
        self::assertSame($first, $cacheKey->make('users', ListQuery::fromOptions([
            'limit' => 10,
            'filter' => ['created' => UnixDateTime::fromUnix(1)],
        ])));
    }

    /**
     * Агрегат: kind, меры и подзапрос в каноне, не {}.
     *
     * @return void
     */
    public function testAggregateCanonDistinguishesMeasureAndSubquery(): void
    {
        $cacheKey = new ListCacheKey();
        $countQuery = $this->aggregateWithMeasure(new CountField('n'));
        $maxQuery = $this->aggregateWithMeasure(new MaxField('id', 'n'));
        $countKey = $cacheKey->makeAggregate('st_sample', $countQuery);
        self::assertNotSame($countKey, $cacheKey->makeAggregate('st_sample', $maxQuery));
        self::assertSame($countKey, $cacheKey->makeAggregate('st_sample', $countQuery));
        self::assertStringContainsString('"kind":"aggregate"', $countKey);
        self::assertStringContainsString('"count":"n"', $countKey);

        $zero = $cacheKey->makeAggregate('st_sample', $this->aggregateWithCoalesce(0));
        $one = $cacheKey->makeAggregate('st_sample', $this->aggregateWithCoalesce(1));
        self::assertNotSame($zero, $one);
        self::assertStringContainsString('"subquery"', $zero);
        self::assertStringContainsString((string) json_encode(SampleTable::class), $zero);
        $listKey = $cacheKey->make('st_sample', ListQuery::fromOptions(['limit' => 10]));
        self::assertStringContainsString('"kind":"list"', $listKey);
        self::assertNotSame($listKey, $countKey);
    }

    /**
     * Агрегат с одной мерой без фильтра.
     *
     * @param CountField|MaxField $measure Мера select.
     *
     * @return AggregateQuery Запрос.
     */
    private function aggregateWithMeasure(CountField|MaxField $measure): AggregateQuery
    {
        return AggregateQuery::fromOptions([
            'group' => ['id'],
            'select' => ['id', $measure],
            'limit' => 10,
        ]);
    }

    /**
     * Агрегат с подзапросом и coalesce.
     *
     * @param int $coalesce Порог COALESCE.
     *
     * @return AggregateQuery Запрос.
     */
    private function aggregateWithCoalesce(int $coalesce): AggregateQuery
    {
        return AggregateQuery::fromOptions([
            'filter' => [
                '>id' => new SubqueryValue(
                    SampleTable::class,
                    'id',
                    filter: ['title' => new OuterColumn('title')],
                    coalesce: $coalesce,
                ),
            ],
            'group' => ['id'],
            'select' => ['id', new CountField('n')],
            'limit' => 10,
        ]);
    }
}
