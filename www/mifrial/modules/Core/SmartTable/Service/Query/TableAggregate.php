<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Service\Query;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Mifrial\Core\SmartTable\Dto\AggregateQuery;
use Mifrial\Core\SmartTable\Dto\AggregateResult;
use Mifrial\Core\SmartTable\Dto\CountField;
use Mifrial\Core\SmartTable\Dto\MaxField;
use Mifrial\Core\SmartTable\Dto\MinField;
use Mifrial\Core\SmartTable\Dto\SumField;
use Mifrial\Core\SmartTable\Exception\Map\MapInvalidException;
use Mifrial\Core\SmartTable\Field\BaseField;
use Mifrial\Core\SmartTable\Field\StringField;
use Mifrial\Core\SmartTable\Service\Connection\IlluminateDatabaseConnection;
use Mifrial\Core\SmartTable\Service\DriverErrorTranslator;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;

/**
 * GROUP BY своей таблицы и гидратация рядов мер.
 */
final class TableAggregate
{
    /**
     * @var array<int, string>
     */
    private const GROUP_TYPES = ['reference', 'int', 'bigint', 'bool', 'datetime', 'string'];

    /**
     * @var array<int, string>
     */
    private const MAX_MIN_TYPES = ['reference', 'int', 'bigint', 'bool', 'datetime', 'string'];

    /**
     * @var array<int, string>
     */
    private const SUM_TYPES = ['reference', 'int', 'bigint'];

    /**
     * Создаёт исполнитель агрегата.
     *
     * @param IlluminateDatabaseConnection $databaseConnection Адаптер.
     * @param DriverErrorTranslator $driverErrors Переводчик SQLSTATE.
     * @param ListQueryCompiler $listQueryCompiler WHERE.
     * @param FieldPathWalker $fieldPathWalker Карты подзапроса.
     *
     * @return void
     */
    public function __construct(
        private readonly IlluminateDatabaseConnection $databaseConnection,
        private readonly DriverErrorTranslator $driverErrors,
        private readonly ListQueryCompiler $listQueryCompiler,
        private readonly FieldPathWalker $fieldPathWalker = new FieldPathWalker(),
    ) {
    }

    /**
     * Выполняет агрегат своей таблицы.
     *
     * @param SmartTableDefinition $tableDefinition Карта.
     * @param AggregateQuery $aggregateQuery Запрос.
     *
     * @return AggregateResult Ряды групп.
     *
     * @throws MapInvalidException Если group/select не сходятся с картой.
     */
    public function aggregate(
        SmartTableDefinition $tableDefinition,
        AggregateQuery $aggregateQuery,
    ): AggregateResult {
        $query = $this->databaseConnection->illuminateConnection()->table($tableDefinition->getName());
        $this->listQueryCompiler->applyFilter($query, $aggregateQuery->filter(), $tableDefinition);
        $this->applySelect($query, $tableDefinition, $aggregateQuery);
        foreach ($aggregateQuery->group() as $groupName) {
            $query->groupBy($groupName);
            $query->orderBy($groupName, 'asc');
        }

        $query->limit($aggregateQuery->limit());
        $databaseRows = $this->driverErrors->run(
            static fn (): Collection => $query->get(),
        );

        return new AggregateResult(
            (new AggregateRowHydrator())->hydrateRows($databaseRows, $tableDefinition, $aggregateQuery),
        );
    }

    /**
     * Теги кэша агрегата: group, filter, аргументы мер, чужой стол подзапроса.
     *
     * @param SmartTableDefinition $tableDefinition Карта.
     * @param AggregateQuery $aggregateQuery Запрос.
     *
     * @return array<int, string> table:field.
     */
    public function cacheFieldTags(SmartTableDefinition $tableDefinition, AggregateQuery $aggregateQuery): array
    {
        $tags = (new ListCacheFieldTags($this->fieldPathWalker))->collectFilter(
            $tableDefinition,
            $aggregateQuery->filter(),
        );
        foreach ($aggregateQuery->group() as $groupName) {
            $tags[] = $tableDefinition->getName() . ':' . $groupName;
        }

        foreach ($this->measureFieldNames($aggregateQuery) as $fieldName) {
            $tags[] = $tableDefinition->getName() . ':' . $fieldName;
        }

        return array_values(array_unique($tags));
    }

    /**
     * SELECT ключей group и SQL мер.
     *
     * @param Builder $query Билдер.
     * @param SmartTableDefinition $tableDefinition Карта.
     * @param AggregateQuery $aggregateQuery Запрос.
     *
     * @return void
     *
     * @throws MapInvalidException Если поле или alias недопустимы.
     */
    private function applySelect(
        Builder $query,
        SmartTableDefinition $tableDefinition,
        AggregateQuery $aggregateQuery,
    ): void {
        $this->assertGroupFields($tableDefinition, $aggregateQuery->group());
        $seenAliases = $aggregateQuery->group();
        foreach ($aggregateQuery->select() as $selectItem) {
            if (is_string($selectItem)) {
                $query->addSelect($selectItem);
                continue;
            }

            $this->assertUniqueAlias($tableDefinition, $selectItem->alias(), $seenAliases);
            $seenAliases[] = $selectItem->alias();
            $this->applyMeasure($query, $tableDefinition, $selectItem);
        }
    }

    /**
     * Проверяет поля GROUP BY.
     *
     * @param SmartTableDefinition $tableDefinition Карта.
     * @param array<int, string> $groupNames Ключи.
     *
     * @return void
     *
     * @throws MapInvalidException Если поле нельзя группировать.
     */
    private function assertGroupFields(SmartTableDefinition $tableDefinition, array $groupNames): void
    {
        foreach ($groupNames as $groupName) {
            $field = $this->ownField($tableDefinition, $groupName);
            if ($field->isMfv() || !in_array($field->type(), self::GROUP_TYPES, true)) {
                throw new MapInvalidException('Aggregate group field is invalid');
            }
        }
    }

    /**
     * SQL одной меры.
     *
     * @param Builder $query Билдер.
     * @param SmartTableDefinition $tableDefinition Карта.
     * @param CountField|MaxField|MinField|SumField $measure Мера.
     *
     * @return void
     *
     * @throws MapInvalidException Если аргумент недопустим.
     */
    private function applyMeasure(
        Builder $query,
        SmartTableDefinition $tableDefinition,
        CountField|MaxField|MinField|SumField $measure,
    ): void {
        $alias = $measure->alias();
        if ($measure instanceof CountField) {
            $query->selectRaw('COUNT(*) as `' . $alias . '`');

            return;
        }

        $argument = $this->ownField($tableDefinition, $measure->fieldName());
        $wrapped = '`' . $measure->fieldName() . '`';
        $aliasSql = '`' . $alias . '`';
        if ($measure instanceof SumField) {
            $this->assertArgumentTypes($argument, self::SUM_TYPES);
            $query->selectRaw('CAST(SUM(' . $wrapped . ') AS SIGNED) as ' . $aliasSql);

            return;
        }

        $this->assertMaxMinArgument($argument);
        $functionName = $measure instanceof MinField ? 'MIN' : 'MAX';
        $query->selectRaw($functionName . '(' . $wrapped . ') as ' . $aliasSql);
    }

    /**
     * Alias не колонка карты и не дубль.
     *
     * @param SmartTableDefinition $tableDefinition Карта.
     * @param string $alias Ключ меры.
     * @param array<int, string> $seenAliases Уже занятые имена.
     *
     * @return void
     *
     * @throws MapInvalidException Если alias занят.
     */
    private function assertUniqueAlias(
        SmartTableDefinition $tableDefinition,
        string $alias,
        array $seenAliases,
    ): void {
        if (isset($tableDefinition->getMap()[$alias]) || in_array($alias, $seenAliases, true)) {
            throw new MapInvalidException('Aggregate alias is invalid');
        }
    }

    /**
     * Поля-аргументы Max/Min/Sum для тегов.
     *
     * @param AggregateQuery $aggregateQuery Запрос.
     *
     * @return array<int, string> Имена.
     */
    private function measureFieldNames(AggregateQuery $aggregateQuery): array
    {
        $fieldNames = [];
        foreach ($aggregateQuery->select() as $selectItem) {
            if ($selectItem instanceof MaxField || $selectItem instanceof MinField || $selectItem instanceof SumField) {
                $fieldNames[] = $selectItem->fieldName();
            }
        }

        return $fieldNames;
    }

    /**
     * Max/Min: тип и string ≤ 255.
     *
     * @param BaseField $field Аргумент.
     *
     * @return void
     *
     * @throws MapInvalidException Если тип недопустим.
     */
    private function assertMaxMinArgument(BaseField $field): void
    {
        $this->assertArgumentTypes($field, self::MAX_MIN_TYPES);
        if ($field instanceof StringField && $field->maxLength() > 255) {
            throw new MapInvalidException('Aggregate string argument is invalid');
        }
    }

    /**
     * Проверяет type() и multiple.
     *
     * @param BaseField $field Поле.
     * @param array<int, string> $allowedTypes Allowlist.
     *
     * @return void
     *
     * @throws MapInvalidException Если тип недопустим.
     */
    private function assertArgumentTypes(BaseField $field, array $allowedTypes): void
    {
        if ($field->isMfv() || !in_array($field->type(), $allowedTypes, true)) {
            throw new MapInvalidException('Aggregate argument field is invalid');
        }
    }

    /**
     * Поле своей карты.
     *
     * @param SmartTableDefinition $tableDefinition Карта.
     * @param string $fieldName Имя.
     *
     * @return BaseField Поле.
     *
     * @throws MapInvalidException Если поля нет.
     */
    private function ownField(SmartTableDefinition $tableDefinition, string $fieldName): BaseField
    {
        $fieldMap = $tableDefinition->getMap();
        if (!isset($fieldMap[$fieldName])) {
            throw new MapInvalidException('Unknown field name');
        }

        return $fieldMap[$fieldName];
    }
}
