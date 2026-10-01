<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\RuleSpace\Tests;

use Mifrial\Core\SmartTable\Dto\AggregateQuery;
use Mifrial\Core\SmartTable\Dto\AggregateResult;
use Mifrial\Core\SmartTable\Dto\FilterCondition;
use Mifrial\Core\SmartTable\Dto\ListQuery;
use Mifrial\Core\SmartTable\Dto\ListResult;
use Mifrial\Core\SmartTable\Interface\Service\IOpenedRecords;
use Mifrial\Roleplay\RuleSpace\Repository\RuleSpaceCatalogRepository;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Один запрос карточек снимка и порядок секций.
 */
final class RuleSpaceCatalogPlacementQueryTest extends TestCase
{
    /**
     * Три секции дают один item-запрос и порядок по sort секции.
     *
     * @return void
     */
    public function testPlacementsUseOneQueryInSectionOrder(): void
    {
        $sectionRecords = new CatalogOpenedRecords([
            $this->sectionRow(30, 'first', 1),
            $this->sectionRow(10, 'middle', 2),
            $this->sectionRow(20, 'third', 3),
        ]);
        $itemRecords = new CatalogOpenedRecords([
            ['section_id' => 30, 'rule_code' => 'alpha', 'sort_order' => 1],
            ['section_id' => 20, 'rule_code' => 'mid', 'sort_order' => 1],
            ['section_id' => 99, 'rule_code' => 'foreign', 'sort_order' => 2],
            ['section_id' => 30, 'rule_code' => 'zeta', 'sort_order' => 3],
        ]);
        $repository = new RuleSpaceCatalogRepository(
            $sectionRecords,
            $itemRecords,
            new CatalogOpenedRecords([]),
        );

        $placements = $repository->getBySectionVersion(1, 1)->getPlacements();

        self::assertCount(1, $itemRecords->queries);
        $this->assertItemQuery($itemRecords->queries[0], [30, 10, 20]);
        self::assertSame(
            [
                ['alpha', 'first', 1],
                ['zeta', 'first', 3],
                ['mid', 'third', 1],
            ],
            array_map(
                static fn ($placement): array => [
                    $placement->getRuleCode(),
                    $placement->getSectionCode(),
                    $placement->getSortOrder(),
                ],
                $placements,
            ),
        );
    }

    /**
     * Пустой список секций не читает карточки.
     *
     * @return void
     */
    public function testEmptySectionsDoNotQueryItems(): void
    {
        $itemRecords = new CatalogOpenedRecords([]);
        $repository = new RuleSpaceCatalogRepository(
            new CatalogOpenedRecords([]),
            $itemRecords,
            new CatalogOpenedRecords([]),
        );

        $catalog = $repository->getBySectionVersion(1, 1);

        self::assertSame([], $catalog->getSections());
        self::assertSame([], $catalog->getPlacements());
        self::assertSame([], $itemRecords->queries);
    }

    /**
     * Строка узла в порядке, который вернул бы getList.
     *
     * @param int $sectionId PK.
     * @param string $code Код.
     * @param int $sortOrder Порядок.
     *
     * @return array<string, mixed> Колонки.
     */
    private function sectionRow(int $sectionId, string $code, int $sortOrder): array
    {
        return [
            'id' => $sectionId,
            'code' => $code,
            'name' => $code,
            'parent_code' => null,
            'sort_order' => $sortOrder,
            'catalog_root_for' => null,
        ];
    }

    /**
     * Проверяет IN, sort и limit одного запроса карточек.
     *
     * @param ListQuery $listQuery Запрос items.
     * @param array<int, int> $sectionIds Ожидаемые id.
     *
     * @return void
     */
    private function assertItemQuery(ListQuery $listQuery, array $sectionIds): void
    {
        $filter = $listQuery->filter();
        self::assertNotNull($filter);
        $children = $filter->children();
        self::assertCount(1, $children);
        $condition = $children[0];
        self::assertInstanceOf(FilterCondition::class, $condition);
        self::assertSame('section_id', $condition->fieldName());
        self::assertSame('=', $condition->operator());
        self::assertSame($sectionIds, $condition->operand());
        self::assertSame(
            ['sort_order' => 'ASC', 'rule_code' => 'ASC'],
            $listQuery->sort(),
        );
        self::assertSame(ListQuery::MAX_LIMIT, $listQuery->limit());
    }
}

/**
 * Отдаёт заранее заданные строки getList и считает вызовы.
 */
final class CatalogOpenedRecords implements IOpenedRecords
{
    /** @var array<int, ListQuery> */
    public array $queries = [];

    /**
     * Создаёт фейк.
     *
     * @param array<int, array<string, mixed>> $rows Строки getList.
     *
     * @return void
     */
    public function __construct(
        private readonly array $rows,
    ) {
    }

    /**
     * @param array<string, mixed> $values Значения.
     *
     * @return int Id.
     */
    public function add(array $values): int
    {
        throw new RuntimeException('add is unused');
    }

    /**
     * @param array<int, array<string, mixed>> $rows Ряды.
     *
     * @return array<int, int> Id.
     */
    public function addMany(array $rows): array
    {
        throw new RuntimeException('addMany is unused');
    }

    /**
     * @param int $rowId Id.
     * @param array<string, mixed> $values Поля.
     *
     * @return void
     */
    public function update(int $rowId, array $values): void
    {
        throw new RuntimeException('update is unused');
    }

    /**
     * @param int $rowId Id.
     *
     * @return void
     */
    public function delete(int $rowId): void
    {
        throw new RuntimeException('delete is unused');
    }

    /**
     * @param int $rowId Id.
     * @param int|null $cacheTtl TTL.
     *
     * @return array<string, mixed>|null Строка.
     */
    public function getById(int $rowId, ?int $cacheTtl = null): ?array
    {
        throw new RuntimeException('getById is unused');
    }

    /**
     * @param ListQuery $listQuery Запрос.
     * @param int|null $cacheTtl TTL.
     *
     * @return ListResult Страница.
     */
    public function getList(ListQuery $listQuery, ?int $cacheTtl = null): ListResult
    {
        $this->queries[] = $listQuery;

        return new ListResult($this->rows, null);
    }

    /**
     * @param ListQuery $listQuery Запрос.
     * @param int|null $cacheTtl TTL.
     *
     * @return array<string, mixed>|null Строка.
     */
    public function getUnique(ListQuery $listQuery, ?int $cacheTtl = null): ?array
    {
        throw new RuntimeException('getUnique is unused');
    }

    /**
     * @param ListQuery $listQuery Запрос.
     * @param int|null $cacheTtl TTL.
     *
     * @return array<string, mixed>|null Строка.
     */
    public function getFirst(ListQuery $listQuery, ?int $cacheTtl = null): ?array
    {
        throw new RuntimeException('getFirst is unused');
    }

    /**
     * @param AggregateQuery $aggregateQuery Запрос.
     * @param int|null $cacheTtl TTL.
     *
     * @return AggregateResult Группы.
     */
    public function aggregate(AggregateQuery $aggregateQuery, ?int $cacheTtl = null): AggregateResult
    {
        throw new RuntimeException('aggregate is unused');
    }
}
