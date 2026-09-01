<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Service;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Mifrial\Core\SmartTable\Dto\ListQuery;
use Mifrial\Core\SmartTable\Dto\ListResult;
use Mifrial\Core\SmartTable\Exception\Map\MapInvalidException;
use Mifrial\Core\SmartTable\Exception\Schema\SchemaMismatchException;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;

/**
 * Выборка страницы строк и optional COUNT.
 */
final class TableList
{
    /**
     * Создаёт доступ к списку.
     *
     * @param IlluminateDatabaseConnection $databaseConnection Адаптер.
     * @param RowAssembler $rowAssembler Гидратация выбранных полей.
     * @param DriverErrorTranslator $driverErrors Переводчик SQLSTATE.
     * @param ListQueryCompiler $listQueryCompiler WHERE/ORDER/page.
     * @param MfvRows $mfvRows Догрузка множеств.
     *
     * @return void
     */
    public function __construct(
        private readonly IlluminateDatabaseConnection $databaseConnection,
        private readonly RowAssembler $rowAssembler,
        private readonly DriverErrorTranslator $driverErrors,
        private readonly ListQueryCompiler $listQueryCompiler,
        private readonly MfvRows $mfvRows,
    ) {
    }

    /**
     * Возвращает страницу гидратированных строк.
     *
     * @param SmartTableDefinition $tableDefinition Определение.
     * @param ListQuery $listQuery Запрос.
     *
     * @return ListResult Строки и optional total.
     *
     * @throws MapInvalidException Если select/sort/filter не сходятся с картой.
     */
    public function getList(SmartTableDefinition $tableDefinition, ListQuery $listQuery): ListResult
    {
        $hydrateNames = $this->resolveSelect($listQuery, $tableDefinition);
        $sqlColumns = $this->sqlColumns($hydrateNames, $tableDefinition);
        $filteredQuery = $this->filteredBuilder($tableDefinition, $listQuery);
        $totalCount = $this->optionalTotal($filteredQuery, $listQuery);
        $this->listQueryCompiler->applyOrder($filteredQuery, $listQuery, $tableDefinition);
        $this->listQueryCompiler->applyPage($filteredQuery, $listQuery);
        $databaseRows = $this->driverErrors->run(
            static fn (): Collection => $filteredQuery->select($sqlColumns)->get(),
        );

        return new ListResult($this->hydrateRows($databaseRows, $tableDefinition, $hydrateNames), $totalCount);
    }

    /**
     * Билдер с WHERE без ORDER/LIMIT.
     *
     * @param SmartTableDefinition $tableDefinition Определение.
     * @param ListQuery $listQuery Запрос.
     *
     * @return Builder Билдер.
     */
    private function filteredBuilder(SmartTableDefinition $tableDefinition, ListQuery $listQuery): Builder
    {
        $query = $this->databaseConnection->illuminateConnection()->table($tableDefinition->getName());
        $this->listQueryCompiler->applyWhere($query, $listQuery, $tableDefinition);

        return $query;
    }

    /**
     * COUNT с тем же WHERE, без сортировки и страницы.
     *
     * @param Builder $filteredQuery Билдер после WHERE.
     * @param ListQuery $listQuery Запрос.
     *
     * @return int|null COUNT или null.
     */
    private function optionalTotal(Builder $filteredQuery, ListQuery $listQuery): ?int
    {
        if (!$listQuery->countTotal()) {
            return null;
        }

        $totalCount = $this->driverErrors->run(
            static fn (): int => (int) (clone $filteredQuery)->count(),
        );

        return $totalCount;
    }

    /**
     * Имена колонок select.
     *
     * @param ListQuery $listQuery Запрос.
     * @param SmartTableDefinition $tableDefinition Карта.
     *
     * @return array<int, string> Колонки.
     *
     * @throws MapInvalidException Если поля нет в карте.
     */
    private function resolveSelect(ListQuery $listQuery, SmartTableDefinition $tableDefinition): array
    {
        $fieldMap = $tableDefinition->getMap();
        $selectedNames = $listQuery->select();
        if ($selectedNames === null) {
            return array_keys($fieldMap);
        }

        foreach ($selectedNames as $fieldName) {
            if (!isset($fieldMap[$fieldName])) {
                throw new MapInvalidException('Unknown field name');
            }
        }

        return $selectedNames;
    }

    /**
     * Колонки SQL: без multiple, плюс id если грузим mfv.
     *
     * @param array<int, string> $hydrateNames Поля ответа.
     * @param SmartTableDefinition $tableDefinition Карта.
     *
     * @return array<int, string> Колонки SELECT.
     */
    private function sqlColumns(array $hydrateNames, SmartTableDefinition $tableDefinition): array
    {
        $fieldMap = $tableDefinition->getMap();
        $sqlColumns = [];
        $needsOwnerId = false;
        foreach ($hydrateNames as $fieldName) {
            if ($fieldMap[$fieldName]->settings()->multiple()) {
                $needsOwnerId = true;
                continue;
            }

            $sqlColumns[] = $fieldName;
        }

        if ($needsOwnerId && !in_array('id', $sqlColumns, true)) {
            $sqlColumns[] = 'id';
        }

        return $sqlColumns;
    }

    /**
     * Гидратирует коллекцию драйвера.
     *
     * @param Collection $databaseRows Сырые строки.
     * @param SmartTableDefinition $tableDefinition Определение.
     * @param array<int, string> $hydrateNames Поля ответа.
     *
     * @return array<int, array<string, mixed>> Строки API.
     *
     * @throws SchemaMismatchException Если строка не карта колонок.
     */
    private function hydrateRows(
        Collection $databaseRows,
        SmartTableDefinition $tableDefinition,
        array $hydrateNames,
    ): array {
        $hydratedRows = [];
        $rowMaps = [];
        foreach ($databaseRows as $databaseRow) {
            $rowMaps[] = $this->rowMap($databaseRow);
        }

        $this->attachMultipleLists($rowMaps, $tableDefinition, $hydrateNames);
        foreach ($rowMaps as $rowMap) {
            $hydratedRows[] = $this->rowAssembler->hydrateSelected(
                $rowMap,
                $tableDefinition,
                $hydrateNames,
            );
        }

        return $hydratedRows;
    }

    /**
     * Подставляет сырые mfv list в строки страницы.
     *
     * @param array<int, array<string, mixed>> $rowMaps Строки драйвера.
     * @param SmartTableDefinition $tableDefinition Определение.
     * @param array<int, string> $hydrateNames Поля ответа.
     *
     * @return void
     */
    private function attachMultipleLists(
        array &$rowMaps,
        SmartTableDefinition $tableDefinition,
        array $hydrateNames,
    ): void {
        $ownerIds = [];
        foreach ($rowMaps as $rowMap) {
            if (isset($rowMap['id'])) {
                $ownerIds[] = (int) $rowMap['id'];
            }
        }

        $fieldMap = $tableDefinition->getMap();
        foreach ($hydrateNames as $fieldName) {
            $field = $fieldMap[$fieldName];
            if (!$field->settings()->multiple()) {
                continue;
            }

            $groupedValues = $this->mfvRows->loadByOwners($tableDefinition, $field, $ownerIds);
            foreach ($rowMaps as $rowIndex => $rowMap) {
                $ownerId = (int) ($rowMap['id'] ?? 0);
                $rowMaps[$rowIndex][$fieldName] = $groupedValues[$ownerId] ?? [];
            }
        }
    }

    /**
     * Приводит строку драйвера к массиву колонок.
     *
     * @param mixed $databaseRow Сырая строка.
     *
     * @return array<string, mixed> Карта колонок.
     *
     * @throws SchemaMismatchException Если это не объект/массив.
     */
    private function rowMap(mixed $databaseRow): array
    {
        if (is_object($databaseRow)) {
            $databaseRow = get_object_vars($databaseRow);
        }

        if (!is_array($databaseRow)) {
            throw new SchemaMismatchException('Driver row is not a map of columns');
        }

        return $databaseRow;
    }
}
