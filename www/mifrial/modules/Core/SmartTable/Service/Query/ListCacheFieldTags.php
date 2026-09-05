<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Service\Query;

use Mifrial\Core\SmartTable\Dto\FilterCondition;
use Mifrial\Core\SmartTable\Dto\FilterGroup;
use Mifrial\Core\SmartTable\Dto\ListQuery;
use Mifrial\Core\SmartTable\Dto\OuterColumn;
use Mifrial\Core\SmartTable\Dto\SubqueryValue;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;

/**
 * Теги кэша запроса: стол:поле по сегментам пути и подзапросу.
 */
final class ListCacheFieldTags
{
    /**
     * Создаёт сборщик.
     *
     * @param FieldPathWalker $fieldPathWalker Пути и карты подзапроса.
     *
     * @return void
     */
    public function __construct(
        private readonly FieldPathWalker $fieldPathWalker = new FieldPathWalker(),
    ) {
    }

    /**
     * Возвращает пары стол:поле списка.
     *
     * @param SmartTableDefinition $tableDefinition Своя карта.
     * @param ListQuery $listQuery Запрос.
     *
     * @return array<int, string> table:field.
     */
    public function collect(SmartTableDefinition $tableDefinition, ListQuery $listQuery): array
    {
        $tags = [];
        $keys = $listQuery->select() ?? array_keys($tableDefinition->getMap());
        foreach (array_keys($listQuery->sort()) as $sortField) {
            $keys[] = $sortField;
        }

        foreach (array_unique($keys) as $fieldName) {
            foreach ($this->tagsForKey($tableDefinition, $fieldName) as $fieldTag) {
                $tags[$fieldTag] = true;
            }
        }

        foreach ($this->collectFilter($tableDefinition, $listQuery->filter()) as $fieldTag) {
            $tags[$fieldTag] = true;
        }

        return array_keys($tags);
    }

    /**
     * Теги фильтра, включая подзапрос чужой карты.
     *
     * @param SmartTableDefinition $tableDefinition Своя карта.
     * @param FilterGroup|null $filterGroup Дерево.
     *
     * @return array<int, string> table:field.
     */
    public function collectFilter(SmartTableDefinition $tableDefinition, ?FilterGroup $filterGroup): array
    {
        $tags = [];
        $this->appendFilterTags($tableDefinition, $filterGroup, $tags);

        return array_keys($tags);
    }

    /**
     * Теги store: st:стол и st:стол:поле.
     *
     * @param string $tableName Свой стол.
     * @param array<int, string> $fieldTags Поля или table:field.
     *
     * @return array<int, string> Теги.
     */
    public function storeTags(string $tableName, array $fieldTags): array
    {
        $tagNames = ['st:' . $tableName];
        $seenTables = [$tableName => true];
        foreach ($fieldTags as $fieldTag) {
            $this->appendStoreTag($tagNames, $seenTables, $tableName, $fieldTag);
        }

        return $tagNames;
    }

    /**
     * Обходит дерево фильтра.
     *
     * @param SmartTableDefinition $localTable Локальная карта.
     * @param FilterGroup|null $filterGroup Дерево.
     * @param array<string, true> $tags Набор.
     *
     * @return void
     */
    private function appendFilterTags(
        SmartTableDefinition $localTable,
        ?FilterGroup $filterGroup,
        array &$tags,
    ): void {
        if ($filterGroup === null) {
            return;
        }

        foreach ($filterGroup->children() as $child) {
            if ($child instanceof FilterGroup) {
                $this->appendFilterTags($localTable, $child, $tags);
                continue;
            }

            if ($child instanceof FilterCondition) {
                $this->appendConditionTags($localTable, $child, $tags);
            }
        }
    }

    /**
     * Теги поля условия и операнда.
     *
     * @param SmartTableDefinition $localTable Локальная карта.
     * @param FilterCondition $condition Условие.
     * @param array<string, true> $tags Набор.
     *
     * @return void
     */
    private function appendConditionTags(
        SmartTableDefinition $localTable,
        FilterCondition $condition,
        array &$tags,
    ): void {
        foreach ($this->tagsForKey($localTable, $condition->fieldName()) as $fieldTag) {
            $tags[$fieldTag] = true;
        }

        $this->appendOperandTags($localTable, $condition->operand(), $tags);
    }

    /**
     * Теги операнда: OuterColumn или SubqueryValue.
     *
     * @param SmartTableDefinition $localTable Локальная карта.
     * @param mixed $operand Операнд.
     * @param array<string, true> $tags Набор.
     *
     * @return void
     */
    private function appendOperandTags(SmartTableDefinition $localTable, mixed $operand, array &$tags): void
    {
        if ($operand instanceof OuterColumn) {
            $tags[$localTable->getName() . ':' . $operand->fieldName()] = true;

            return;
        }

        if (!$operand instanceof SubqueryValue) {
            return;
        }

        $innerTable = $this->fieldPathWalker->definitionFor($operand->table());
        $tags[$innerTable->getName() . ':' . $operand->field()] = true;
        $this->appendInnerFilterTags($innerTable, $localTable, $operand->filter(), $tags);
    }

    /**
     * Поля внутреннего WHERE и OuterColumn внешней карты.
     *
     * @param SmartTableDefinition $innerTable Внутренняя карта.
     * @param SmartTableDefinition $outerTable Внешняя FROM.
     * @param FilterGroup $filterGroup Внутренний фильтр.
     * @param array<string, true> $tags Набор.
     *
     * @return void
     */
    private function appendInnerFilterTags(
        SmartTableDefinition $innerTable,
        SmartTableDefinition $outerTable,
        FilterGroup $filterGroup,
        array &$tags,
    ): void {
        foreach ($filterGroup->children() as $child) {
            if ($child instanceof FilterGroup) {
                $this->appendInnerFilterTags($innerTable, $outerTable, $child, $tags);
                continue;
            }

            if ($child instanceof FilterCondition) {
                $tags[$innerTable->getName() . ':' . $child->fieldName()] = true;
                $operand = $child->operand();
                if ($operand instanceof OuterColumn) {
                    $tags[$outerTable->getName() . ':' . $operand->fieldName()] = true;
                }
            }
        }
    }

    /**
     * Теги одного ключа.
     *
     * @param SmartTableDefinition $tableDefinition Карта.
     * @param string $fieldName Поле или путь.
     *
     * @return array<int, string> Теги.
     */
    private function tagsForKey(SmartTableDefinition $tableDefinition, string $fieldName): array
    {
        if (!str_contains($fieldName, '.')) {
            return [$tableDefinition->getName() . ':' . $fieldName];
        }

        $tags = [];
        foreach ($this->fieldPathWalker->resolve($tableDefinition, $fieldName)->cacheSegments() as $segment) {
            $tags[] = $segment['table'] . ':' . $segment['field'];
        }

        return $tags;
    }

    /**
     * Добавляет теги одного ключа.
     *
     * @param array<int, string> $tagNames Уже собранные.
     * @param array<string, true> $seenTables Столы.
     * @param string $tableName Свой стол.
     * @param string $fieldTag Поле или table:field.
     *
     * @return void
     */
    private function appendStoreTag(
        array &$tagNames,
        array &$seenTables,
        string $tableName,
        string $fieldTag,
    ): void {
        $tagTable = $tableName;
        $tagField = $fieldTag;
        if (str_contains($fieldTag, ':')) {
            [$tagTable, $tagField] = explode(':', $fieldTag, 2);
        }

        if (!isset($seenTables[$tagTable])) {
            $tagNames[] = 'st:' . $tagTable;
            $seenTables[$tagTable] = true;
        }

        $tagNames[] = 'st:' . $tagTable . ':' . $tagField;
    }
}
