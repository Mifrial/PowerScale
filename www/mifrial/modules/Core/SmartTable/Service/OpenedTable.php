<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Service;

use Mifrial\Core\SmartTable\Dto\FilterCondition;
use Mifrial\Core\SmartTable\Dto\FilterGroup;
use Mifrial\Core\SmartTable\Dto\ListQuery;
use Mifrial\Core\SmartTable\Dto\ListResult;
use Mifrial\Core\SmartTable\Exception\Map\MapInvalidException;
use Mifrial\Core\SmartTable\Interface\Service\IOpenedTable;
use Mifrial\Core\SmartTable\Service\Cache\TableCache;
use Mifrial\Core\SmartTable\Service\Query\TableList;
use Mifrial\Core\SmartTable\Service\Query\TableRows;
use Mifrial\Core\SmartTable\Service\Schema\TableSchema;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;

/**
 * Тонкий handle: делегирует DDL и строку, опционально кэш TTL.
 */
final class OpenedTable implements IOpenedTable
{
    /**
     * Создаёт handle открытой таблицы.
     *
     * @param SmartTableDefinition $tableDefinition Определение.
     * @param TableSchema $tableSchema DDL.
     * @param TableRows $tableRows Строки.
     * @param TableList $tableList Список.
     * @param TableCache $tableCache Кэш get/getList.
     *
     * @return void
     */
    public function __construct(
        private readonly SmartTableDefinition $tableDefinition,
        private readonly TableSchema $tableSchema,
        private readonly TableRows $tableRows,
        private readonly TableList $tableList,
        private readonly TableCache $tableCache,
    ) {
    }

    /**
     * Создаёт физическую таблицу по карте.
     *
     * @return void
     */
    public function createTable(): void
    {
        $this->tableSchema->createTable($this->tableDefinition);
        $this->tableCache->noteDdl($this->tableDefinition->getName());
    }

    /**
     * Добавляет отсутствующие колонки, индексы, mfv и FK карты.
     *
     * @return void
     */
    public function updateTable(): void
    {
        $this->tableSchema->updateTable($this->tableDefinition);
        $this->tableCache->noteDdl($this->tableDefinition->getName());
    }

    /**
     * Добавляет недостающее по карте и снимает leftover.
     *
     * @return void
     */
    public function forceUpdateTable(): void
    {
        $this->tableSchema->forceUpdateTable($this->tableDefinition);
        $this->tableCache->noteDdl($this->tableDefinition->getName());
    }

    /**
     * Удаляет физическую таблицу и mfv полей карты.
     *
     * @return void
     */
    public function deleteTable(): void
    {
        $this->tableSchema->deleteTable($this->tableDefinition);
        $this->tableCache->noteDdl($this->tableDefinition->getName());
    }

    /**
     * Вставляет строку и возвращает id.
     *
     * @param array<string, mixed> $values Значения полей.
     *
     * @return int Новый id.
     */
    public function add(array $values): int
    {
        $rowId = $this->tableRows->add($this->tableDefinition, $values);
        $this->tableCache->noteAdd($this->tableDefinition->getName());

        return $rowId;
    }

    /**
     * Обновляет переданные поля строки.
     *
     * @param int $rowId Идентификатор.
     * @param array<string, mixed> $values Поля к записи.
     *
     * @return void
     */
    public function update(int $rowId, array $values): void
    {
        $this->tableRows->update($this->tableDefinition, $rowId, $values);
        $this->tableCache->noteUpdate(
            $this->tableDefinition->getName(),
            $rowId,
            array_keys($values),
        );
    }

    /**
     * Удаляет строку.
     *
     * @param int $rowId Идентификатор.
     *
     * @return void
     */
    public function delete(int $rowId): void
    {
        $this->tableRows->delete($this->tableDefinition, $rowId);
        $this->tableCache->noteDelete($this->tableDefinition->getName(), $rowId);
    }

    /**
     * Читает строку по id или null.
     *
     * @param int $rowId Идентификатор.
     * @param int|null $cacheTtl Секунды кэша или БД.
     *
     * @return array<string, mixed>|null Гидратированные поля или null.
     *
     * @throws MapInvalidException Если TTL ≤ 0.
     */
    public function getById(int $rowId, ?int $cacheTtl = null): ?array
    {
        $this->assertTtl($cacheTtl);
        $tableName = $this->tableDefinition->getName();
        if ($cacheTtl !== null) {
            $cacheHit = $this->tableCache->lookupGet($tableName, $rowId);
            if ($cacheHit->found()) {
                $cachedRow = $cacheHit->value();
                if ($cachedRow === null || is_array($cachedRow)) {
                    return $cachedRow;
                }
            }
        }

        $row = $this->tableRows->getById($this->tableDefinition, $rowId);
        if ($cacheTtl !== null) {
            $this->tableCache->saveGet($tableName, $rowId, $row, $cacheTtl);
        }

        return $row;
    }

    /**
     * Возвращает страницу строк по запросу.
     *
     * @param ListQuery $listQuery Запрос списка.
     * @param int|null $cacheTtl Секунды кэша или БД.
     *
     * @return ListResult Строки и optional total.
     *
     * @throws MapInvalidException Если TTL ≤ 0.
     */
    public function getList(ListQuery $listQuery, ?int $cacheTtl = null): ListResult
    {
        $this->assertTtl($cacheTtl);
        $tableName = $this->tableDefinition->getName();
        if ($cacheTtl !== null) {
            $cacheHit = $this->tableCache->lookupList($tableName, $listQuery);
            if ($cacheHit->found() && $cacheHit->value() instanceof ListResult) {
                return $cacheHit->value();
            }
        }

        $listResult = $this->tableList->getList($this->tableDefinition, $listQuery);
        if ($cacheTtl !== null) {
            $this->tableCache->saveList(
                $tableName,
                $listQuery,
                $listResult,
                $cacheTtl,
                $this->listFieldNames($listQuery),
            );
        }

        return $listResult;
    }

    /**
     * Отвергает неположительный TTL.
     *
     * @param int|null $cacheTtl Секунды или null.
     *
     * @return void
     *
     * @throws MapInvalidException Если TTL ≤ 0.
     */
    private function assertTtl(?int $cacheTtl): void
    {
        if ($cacheTtl !== null && $cacheTtl <= 0) {
            throw new MapInvalidException('Cache TTL must be greater than 0');
        }
    }

    /**
     * Поля запроса для тегов getList.
     *
     * @param ListQuery $listQuery Запрос.
     *
     * @return array<int, string> Имена полей.
     */
    private function listFieldNames(ListQuery $listQuery): array
    {
        $fieldNames = $listQuery->select() ?? array_keys($this->tableDefinition->getMap());
        foreach (array_keys($listQuery->sort()) as $sortField) {
            $fieldNames[] = $sortField;
        }

        return array_values(array_unique(array_merge(
            $fieldNames,
            $this->filterFieldNames($listQuery->filter()),
        )));
    }

    /**
     * Имена полей из дерева фильтра.
     *
     * @param FilterGroup|null $filterGroup Дерево.
     *
     * @return array<int, string> Имена.
     */
    private function filterFieldNames(?FilterGroup $filterGroup): array
    {
        if ($filterGroup === null) {
            return [];
        }

        $fieldNames = [];
        foreach ($filterGroup->children() as $child) {
            array_push($fieldNames, ...$this->childFieldNames($child));
        }

        return $fieldNames;
    }

    /**
     * Поля одного узла фильтра.
     *
     * @param FilterGroup|FilterCondition $child Узел.
     *
     * @return array<int, string> Имена.
     */
    private function childFieldNames(FilterGroup|FilterCondition $child): array
    {
        if ($child instanceof FilterCondition) {
            return [$child->fieldName()];
        }

        return $this->filterFieldNames($child);
    }
}
