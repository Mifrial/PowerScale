<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Service\Query;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Mifrial\Core\SmartTable\Dto\ListQuery;
use Mifrial\Core\SmartTable\Dto\ListResult;
use Mifrial\Core\SmartTable\Exception\Map\MapInvalidException;
use Mifrial\Core\SmartTable\Exception\Schema\SchemaMismatchException;
use Mifrial\Core\SmartTable\Field\BaseField;
use Mifrial\Core\SmartTable\Service\Connection\IlluminateDatabaseConnection;
use Mifrial\Core\SmartTable\Service\DriverErrorTranslator;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;

/**
 * Выборка страницы строк и optional COUNT.
 *
 * Суммарная complexity чуть выше порога: getList — один сценарий (свои
 * колонки, свои mfv, путь). Вынос ради sniff уже смешивал роли.
 */
final class TableList // phpcs:ignore MifrialCodingStandard.Metrics.ClassQuality.ClassComplexityTooHigh
{
    private readonly ListPathSql $pathSql;

    private readonly PathListHydrator $pathHydrator;

    /**
     * Создаёт доступ к списку.
     *
     * @param IlluminateDatabaseConnection $databaseConnection Адаптер.
     * @param RowAssembler $rowAssembler Гидратация выбранных полей.
     * @param DriverErrorTranslator $driverErrors Переводчик SQLSTATE.
     * @param ListQueryCompiler $listQueryCompiler WHERE/ORDER/page.
     * @param MfvRows $mfvRows Догрузка множеств.
     * @param FieldPathWalker $fieldPathWalker Пути reference.
     *
     * @return void
     */
    public function __construct(
        private readonly IlluminateDatabaseConnection $databaseConnection,
        private readonly RowAssembler $rowAssembler,
        private readonly DriverErrorTranslator $driverErrors,
        private readonly ListQueryCompiler $listQueryCompiler,
        private readonly MfvRows $mfvRows,
        private readonly FieldPathWalker $fieldPathWalker = new FieldPathWalker(),
    ) {
        $this->pathSql = new ListPathSql();
        $this->pathHydrator = new PathListHydrator($this->fieldPathWalker, $this->mfvRows);
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
        $filteredQuery = $this->filteredBuilder($tableDefinition, $listQuery);
        $totalCount = $this->optionalTotal($filteredQuery, $listQuery);
        $this->listQueryCompiler->applyOrder($filteredQuery, $listQuery, $tableDefinition);
        $this->listQueryCompiler->applyPage($filteredQuery, $listQuery);
        $this->applySelect($filteredQuery, $hydrateNames, $tableDefinition);
        $databaseRows = $this->driverErrors->run(
            static fn (): Collection => $filteredQuery->get(),
        );

        return new ListResult($this->hydrateRows($databaseRows, $tableDefinition, $hydrateNames), $totalCount);
    }

    /**
     * Теги кэша списка по запросу.
     *
     * @param SmartTableDefinition $tableDefinition Карта.
     * @param ListQuery $listQuery Запрос.
     *
     * @return array<int, string> table:field.
     */
    public function cacheFieldTags(SmartTableDefinition $tableDefinition, ListQuery $listQuery): array
    {
        return (new ListCacheFieldTags($this->fieldPathWalker))->collect($tableDefinition, $listQuery);
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
            if (str_contains($fieldName, '.')) {
                $this->fieldPathWalker->resolve($tableDefinition, $fieldName);
                continue;
            }

            if (!isset($fieldMap[$fieldName])) {
                throw new MapInvalidException('Unknown field name');
            }
        }

        return $selectedNames;
    }

    /**
     * SELECT своих колонок и подзапросов пути.
     *
     * @param Builder $query Билдер.
     * @param array<int, string> $hydrateNames Поля ответа.
     * @param SmartTableDefinition $tableDefinition Карта.
     *
     * @return void
     */
    private function applySelect(
        Builder $query,
        array $hydrateNames,
        SmartTableDefinition $tableDefinition,
    ): void {
        $ownColumns = $this->sqlColumns($hydrateNames, $tableDefinition);
        if ($ownColumns !== []) {
            $query->select($ownColumns);
        }

        $this->applyPathSelect($query, $hydrateNames, $tableDefinition);
    }

    /**
     * Добавляет подзапросы пути в SELECT.
     *
     * @param Builder $query Билдер.
     * @param array<int, string> $hydrateNames Поля ответа.
     * @param SmartTableDefinition $tableDefinition Карта.
     *
     * @return void
     */
    private function applyPathSelect(
        Builder $query,
        array $hydrateNames,
        SmartTableDefinition $tableDefinition,
    ): void {
        $mfvIndex = 0;
        foreach ($hydrateNames as $fieldName) {
            if (!str_contains($fieldName, '.')) {
                continue;
            }

            $query->selectRaw($this->pathSelectRaw($tableDefinition, $fieldName, $mfvIndex));
        }
    }

    /**
     * SQL «выражение as alias» для пути.
     *
     * @param SmartTableDefinition $tableDefinition Карта.
     * @param string $fieldName Путь.
     * @param int $mfvIndex Счётчик mfv-алиасов.
     *
     * @return string RAW.
     */
    private function pathSelectRaw(
        SmartTableDefinition $tableDefinition,
        string $fieldName,
        int &$mfvIndex,
    ): string {
        $resolvedPath = $this->fieldPathWalker->resolve($tableDefinition, $fieldName);
        if ($resolvedPath->leafField()->settings()->multiple()) {
            $aliasName = '__st_m' . $mfvIndex;
            $mfvIndex++;

            return $this->pathSql->leafIdSql($resolvedPath) . ' as `' . $aliasName . '`';
        }

        return $this->pathSql->scalarSql($resolvedPath) . ' as `' . $fieldName . '`';
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
            if (str_contains($fieldName, '.')) {
                continue;
            }

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

        $this->attachOwnMultiple($rowMaps, $tableDefinition, $hydrateNames);
        $this->pathHydrator->attachMultiple($rowMaps, $tableDefinition, $hydrateNames);
        foreach ($rowMaps as $rowMap) {
            $hydratedRows[] = $this->hydrateRow($rowMap, $tableDefinition, $hydrateNames);
        }

        return $hydratedRows;
    }

    /**
     * Догружает свои multiple-поля страницы.
     *
     * @param array<int, array<string, mixed>> $rowMaps Строки.
     * @param SmartTableDefinition $tableDefinition Карта.
     * @param array<int, string> $hydrateNames Поля ответа.
     *
     * @return void
     */
    private function attachOwnMultiple(
        array &$rowMaps,
        SmartTableDefinition $tableDefinition,
        array $hydrateNames,
    ): void {
        $ownerIds = $this->ownerIds($rowMaps);
        $fieldMap = $tableDefinition->getMap();
        foreach ($hydrateNames as $fieldName) {
            if (str_contains($fieldName, '.') || !$fieldMap[$fieldName]->settings()->multiple()) {
                continue;
            }

            $this->writeOwnLists($rowMaps, $tableDefinition, $fieldMap[$fieldName], $fieldName, $ownerIds);
        }
    }

    /**
     * Id строк страницы.
     *
     * @param array<int, array<string, mixed>> $rowMaps Строки.
     *
     * @return array<int, int> Id.
     */
    private function ownerIds(array $rowMaps): array
    {
        $ownerIds = [];
        foreach ($rowMaps as $rowMap) {
            if (isset($rowMap['id'])) {
                $ownerIds[] = (int) $rowMap['id'];
            }
        }

        return $ownerIds;
    }

    /**
     * Пишет list своего multiple в строки.
     *
     * @param array<int, array<string, mixed>> $rowMaps Строки.
     * @param SmartTableDefinition $tableDefinition Карта.
     * @param BaseField $field Поле.
     * @param string $fieldName Имя.
     * @param array<int, int> $ownerIds Id.
     *
     * @return void
     */
    private function writeOwnLists(
        array &$rowMaps,
        SmartTableDefinition $tableDefinition,
        BaseField $field,
        string $fieldName,
        array $ownerIds,
    ): void {
        $groupedValues = $this->mfvRows->loadByOwners($tableDefinition, $field, $ownerIds);
        foreach ($rowMaps as $rowIndex => $rowMap) {
            $ownerId = (int) ($rowMap['id'] ?? 0);
            $rowMaps[$rowIndex][$fieldName] = $groupedValues[$ownerId] ?? [];
        }
    }

    /**
     * Гидратирует одну строку: свои поля и пути.
     *
     * @param array<string, mixed> $rowMap Сырая строка.
     * @param SmartTableDefinition $tableDefinition Карта.
     * @param array<int, string> $hydrateNames Поля ответа.
     *
     * @return array<string, mixed> PHP-строка.
     */
    private function hydrateRow(
        array $rowMap,
        SmartTableDefinition $tableDefinition,
        array $hydrateNames,
    ): array {
        $ownNames = [];
        foreach ($hydrateNames as $fieldName) {
            if (!str_contains($fieldName, '.')) {
                $ownNames[] = $fieldName;
            }
        }

        $hydratedRow = [];
        if ($ownNames !== []) {
            $hydratedRow = $this->rowAssembler->hydrateSelected($rowMap, $tableDefinition, $ownNames);
        }

        return $this->pathHydrator->hydratePaths($rowMap, $hydratedRow, $tableDefinition, $hydrateNames);
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
