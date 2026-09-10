<?php

declare(strict_types=1);

namespace Mifrial\Core\Logger\Tests;

use Mifrial\Core\Kernel\Value\DateTime;
use Mifrial\Core\Logger\Dto\LogPageQuery;
use Mifrial\Core\Logger\Repository\LogRepository;
use Mifrial\Core\SmartTable\Dto\FilterCondition;
use Mifrial\Core\SmartTable\Dto\FilterGroup;
use Mifrial\Core\SmartTable\Dto\ListQuery;
use Mifrial\Core\SmartTable\Dto\ListResult;
use Mifrial\Core\SmartTable\Interface\Service\IOpenedRecords;
use PHPUnit\Framework\TestCase;

/**
 * Фильтры репозитория журнала до SmartTable.
 */
final class LogRepositoryFilterTest extends TestCase
{
    /**
     * from/to — DateTime; contains экранирует % и _.
     *
     * @return void
     */
    public function testDateBoundsAreDateTimeAndContainsIsEscaped(): void
    {
        $capturedQuery = null;
        $logRecords = $this->createMock(IOpenedRecords::class);
        $logRecords->method('getList')->willReturnCallback(
            static function (ListQuery $listQuery) use (&$capturedQuery): ListResult {
                $capturedQuery = $listQuery;

                return new ListResult([], 0);
            },
        );
        $logRepository = new LogRepository($logRecords);
        $logRepository->findPage(new LogPageQuery(
            10,
            0,
            null,
            'a%b_c',
            true,
            null,
            false,
            100,
            200,
        ));
        self::assertInstanceOf(ListQuery::class, $capturedQuery);
        $filterGroup = $capturedQuery->filter();
        self::assertInstanceOf(FilterGroup::class, $filterGroup);
        $createdFrom = null;
        $createdTo = null;
        $sourceLike = null;
        foreach ($this->filterConditions($filterGroup) as $condition) {
            if ($condition->fieldName() === 'created_at' && $condition->operator() === '>=') {
                $createdFrom = $condition->operand();
            }

            if ($condition->fieldName() === 'created_at' && $condition->operator() === '<=') {
                $createdTo = $condition->operand();
            }

            if ($condition->fieldName() === 'source' && $condition->operator() === '%') {
                $sourceLike = $condition->operand();
            }
        }

        self::assertInstanceOf(DateTime::class, $createdFrom);
        self::assertInstanceOf(DateTime::class, $createdTo);
        self::assertSame(100, $createdFrom->toUnix());
        self::assertSame(200, $createdTo->toUnix());
        self::assertSame('%a\%b\_c%', $sourceLike);
    }

    /**
     * Плоский список условий фильтра.
     *
     * @param FilterGroup $filterGroup Дерево.
     *
     * @return array<int, FilterCondition> Условия.
     */
    private function filterConditions(FilterGroup $filterGroup): array
    {
        $conditions = [];
        foreach ($filterGroup->children() as $child) {
            if ($child instanceof FilterGroup) {
                $conditions = array_merge($conditions, $this->filterConditions($child));
                continue;
            }

            $conditions[] = $child;
        }

        return $conditions;
    }
}
