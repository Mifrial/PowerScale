<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Tests;

use Mifrial\Core\SmartTable\Dto\AggregateQuery;
use Mifrial\Core\SmartTable\Dto\CountField;
use Mifrial\Core\SmartTable\Dto\OuterColumn;
use Mifrial\Core\SmartTable\Dto\SubqueryValue;
use Mifrial\Core\SmartTable\Exception\Map\MapInvalidException;
use Mifrial\Core\SmartTable\Tests\Fixture\SampleTable;
use PHPUnit\Framework\TestCase;

final class AggregateQueryTest extends TestCase
{
    /**
     * Принимает group, меру и named subquery.
     *
     * @return void
     */
    public function testFromOptionsAcceptsMeasureAndSubquery(): void
    {
        $query = AggregateQuery::fromOptions([
            'group' => ['group_id'],
            'select' => ['group_id', new CountField('unread')],
            'limit' => 10,
            'filter' => [
                '>id' => new SubqueryValue(
                    SampleTable::class,
                    'id',
                    filter: ['title' => new OuterColumn('title')],
                    coalesce: 0,
                ),
            ],
        ]);
        self::assertSame(['group_id'], $query->group());
        self::assertSame(10, $query->limit());
        self::assertNotNull($query->filter());
    }

    /**
     * Отказывает без меры, с offset и пустым внутренним filter.
     *
     * @return void
     */
    public function testFromOptionsRejectsSyntax(): void
    {
        $cases = [
            ['group' => ['group_id'], 'select' => ['group_id'], 'limit' => 10],
            ['group' => [], 'select' => ['group_id', new CountField('c')], 'limit' => 10],
            ['group' => ['group_id'], 'select' => ['group_id', new CountField('c')], 'limit' => 0],
            [
                'group' => ['group_id'],
                'select' => ['group_id', new CountField('c')],
                'limit' => 10,
                'offset' => 1,
            ],
            ['group' => ['a.b'], 'select' => ['a.b', new CountField('c')], 'limit' => 10],
        ];
        foreach ($cases as $options) {
            try {
                AggregateQuery::fromOptions($options);
                self::fail('invalid aggregate options must fail');
            } catch (MapInvalidException $exception) {
                self::assertSame('MAP_INVALID', $exception->getErrorCode());
            }
        }

        try {
            new SubqueryValue(SampleTable::class, 'id', filter: []);
            self::fail('empty subquery filter must fail');
        } catch (MapInvalidException $exception) {
            self::assertSame('MAP_INVALID', $exception->getErrorCode());
        }
    }
}
