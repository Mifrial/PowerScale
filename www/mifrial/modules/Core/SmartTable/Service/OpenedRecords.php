<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Service;

use Mifrial\Core\Cache\Interface\Service\ICacheStore;
use Mifrial\Core\SmartTable\Dto\AggregateQuery;
use Mifrial\Core\SmartTable\Dto\AggregateResult;
use Mifrial\Core\SmartTable\Dto\ListQuery;
use Mifrial\Core\SmartTable\Dto\ListResult;
use Mifrial\Core\SmartTable\Exception\Map\MapInvalidException;
use Mifrial\Core\SmartTable\Interface\Service\IOpenedRecords;
use Mifrial\Core\SmartTable\Service\Cache\ListCacheKey;
use Mifrial\Core\SmartTable\Service\Cache\TableCache;
use Mifrial\Core\SmartTable\Service\Query\TableAggregate;
use Mifrial\Core\SmartTable\Service\Query\TableList;
use Mifrial\Core\SmartTable\Service\Query\TableRows;
use Mifrial\Core\SmartTable\Service\Schema\TableSchema;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;

/**
 * Строки открытой карты: CRUD, список, unique/first, агрегат.
 */
final class OpenedRecords implements IOpenedRecords
{
    /**
     * Создаёт порт строк.
     *
     * @param SmartTableDefinition $tableDefinition Определение.
     * @param TableRows $tableRows Строки.
     * @param TableList $tableList Список.
     * @param TableCache $tableCache Кэш.
     * @param TableSchema $tableSchema Имена CASCADE/SET NULL-детей.
     * @param TableAggregate $tableAggregate Агрегат.
     *
     * @return void
     */
    public function __construct(
        private readonly SmartTableDefinition $tableDefinition,
        private readonly TableRows $tableRows,
        private readonly TableList $tableList,
        private readonly TableCache $tableCache,
        private readonly TableSchema $tableSchema,
        private readonly TableAggregate $tableAggregate,
    ) {
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
     * Вставляет пачку строк и возвращает id в порядке входа.
     *
     * @param array<int, array<string, mixed>> $rows Список карт без id.
     *
     * @return array<int, int> Новые id.
     */
    public function addMany(array $rows): array
    {
        $rowIds = $this->tableRows->addMany($this->tableDefinition, $rows);
        $this->tableCache->noteAdd($this->tableDefinition->getName());

        return $rowIds;
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
        $tableName = $this->tableDefinition->getName();
        $this->tableRows->delete($this->tableDefinition, $rowId);
        $this->tableCache->noteDelete(
            $tableName,
            $rowId,
            fn (): array => $this->tableSchema->cacheDependentTableNames($tableName),
        );
    }

    /**
     * Читает строку по id или null.
     *
     * @param int $rowId Идентификатор.
     * @param int|null $cacheTtl Секунды кэша или БД.
     *
     * @return array<string, mixed>|null Гидратированные поля или null.
     *
     * @throws MapInvalidException Если TTL вне 1..2592000.
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
     * @throws MapInvalidException Если TTL вне 1..2592000.
     */
    public function getList(ListQuery $listQuery, ?int $cacheTtl = null): ListResult
    {
        $this->assertTtl($cacheTtl);
        $tableName = $this->tableDefinition->getName();
        $cacheKey = (new ListCacheKey())->make($tableName, $listQuery);
        if ($cacheTtl !== null) {
            $cacheHit = $this->tableCache->lookupTagged($cacheKey);
            if ($cacheHit->found() && $cacheHit->value() instanceof ListResult) {
                return $cacheHit->value();
            }
        }

        $listResult = $this->tableList->getList($this->tableDefinition, $listQuery);
        if ($cacheTtl !== null) {
            $this->tableCache->saveTagged(
                $tableName,
                $cacheKey,
                $listResult,
                $cacheTtl,
                $this->listFieldTags($listQuery),
            );
        }

        return $listResult;
    }

    /**
     * Возвращает пачку GROUP BY своей таблицы.
     *
     * @param AggregateQuery $aggregateQuery Запрос агрегата.
     * @param int|null $cacheTtl Секунды кэша или БД.
     *
     * @return AggregateResult Ряды групп.
     *
     * @throws MapInvalidException Если TTL вне 1..2592000 или запрос не сходится с картой.
     */
    public function aggregate(AggregateQuery $aggregateQuery, ?int $cacheTtl = null): AggregateResult
    {
        $this->assertTtl($cacheTtl);
        $tableName = $this->tableDefinition->getName();
        $cacheKey = (new ListCacheKey())->makeAggregate($tableName, $aggregateQuery);
        if ($cacheTtl !== null) {
            $cacheHit = $this->tableCache->lookupTagged($cacheKey);
            if ($cacheHit->found() && $cacheHit->value() instanceof AggregateResult) {
                return $cacheHit->value();
            }
        }

        $aggregateResult = $this->tableAggregate->aggregate($this->tableDefinition, $aggregateQuery);
        if ($cacheTtl !== null) {
            $this->tableCache->saveTagged(
                $tableName,
                $cacheKey,
                $aggregateResult,
                $cacheTtl,
                $this->tableAggregate->cacheFieldTags($this->tableDefinition, $aggregateQuery),
            );
        }

        return $aggregateResult;
    }

    /**
     * Ищет ровно одну строку по запросу getList.
     *
     * @param ListQuery $listQuery Запрос; offset и countTotal недопустимы.
     * @param int|null $cacheTtl Секунды кэша или БД.
     *
     * @return array<string, mixed>|null Строка или null.
     *
     * @throws MapInvalidException Если запрос непригоден, TTL вне 1..2592000 или совпадений больше одного.
     */
    public function getUnique(ListQuery $listQuery, ?int $cacheTtl = null): ?array
    {
        $rows = $this->singletonRows($listQuery, 2, $cacheTtl, true);
        if ($rows === []) {
            return null;
        }

        if (count($rows) > 1) {
            throw new MapInvalidException('List query matched more than one row');
        }

        return $rows[0];
    }

    /**
     * Возвращает первую строку по запросу getList.
     *
     * @param ListQuery $listQuery Запрос; offset и countTotal недопустимы.
     * @param int|null $cacheTtl Секунды кэша или БД.
     *
     * @return array<string, mixed>|null Строка или null.
     *
     * @throws MapInvalidException Если запрос непригоден или TTL вне 1..2592000.
     */
    public function getFirst(ListQuery $listQuery, ?int $cacheTtl = null): ?array
    {
        $rows = $this->singletonRows($listQuery, 1, $cacheTtl, false);
        if ($rows === []) {
            return null;
        }

        return $rows[0];
    }

    /**
     * getList с принудительным limit.
     *
     * @param ListQuery $listQuery Запрос соседа.
     * @param int $limit 1 или 2.
     * @param int|null $cacheTtl TTL.
     * @param bool $filterRequired Unique требует filter.
     *
     * @return array<int, array<string, mixed>> Строки.
     *
     * @throws MapInvalidException Если offset, countTotal или пустой смысл.
     */
    private function singletonRows(
        ListQuery $listQuery,
        int $limit,
        ?int $cacheTtl,
        bool $filterRequired,
    ): array {
        if ($listQuery->offset() !== 0 || $listQuery->countTotal()) {
            throw new MapInvalidException('Row query must not use offset or countTotal');
        }

        if ($filterRequired && $listQuery->filter() === null) {
            throw new MapInvalidException('Row filter is empty');
        }

        if (!$filterRequired && $listQuery->filter() === null && $listQuery->sort() === []) {
            throw new MapInvalidException('Row query needs filter or sort');
        }

        return $this->getList(new ListQuery(
            $listQuery->filter(),
            $listQuery->sort(),
            $limit,
            0,
            false,
            $listQuery->select(),
        ), $cacheTtl)->rows();
    }

    /**
     * Отвергает TTL вне 1..2592000.
     *
     * @param int|null $cacheTtl Секунды или null.
     *
     * @return void
     *
     * @throws MapInvalidException Если TTL вне диапазона.
     */
    private function assertTtl(?int $cacheTtl): void
    {
        if ($cacheTtl !== null && ($cacheTtl < 1 || $cacheTtl > ICacheStore::MAX_TTL_SECONDS)) {
            throw new MapInvalidException('Cache TTL must be 1..2592000');
        }
    }

    /**
     * Теги getList: стол:поле, в том числе сегменты пути.
     *
     * @param ListQuery $listQuery Запрос.
     *
     * @return array<int, string> Теги.
     */
    private function listFieldTags(ListQuery $listQuery): array
    {
        return $this->tableList->cacheFieldTags($this->tableDefinition, $listQuery);
    }
}
