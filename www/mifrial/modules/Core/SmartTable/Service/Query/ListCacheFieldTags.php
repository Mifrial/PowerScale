<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Service\Query;

use Mifrial\Core\SmartTable\Dto\FilterCondition;
use Mifrial\Core\SmartTable\Dto\FilterGroup;
use Mifrial\Core\SmartTable\Dto\ListQuery;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;

/**
 * Теги кэша getList: стол:поле по сегментам пути.
 */
final class ListCacheFieldTags
{
    /**
     * Создаёт сборщик.
     *
     * @param FieldPathWalker $fieldPathWalker Пути.
     *
     * @return void
     */
    public function __construct(
        private readonly FieldPathWalker $fieldPathWalker = new FieldPathWalker(),
    ) {
    }

    /**
     * Возвращает пары стол:поле.
     *
     * @param SmartTableDefinition $tableDefinition Своя карта.
     * @param ListQuery $listQuery Запрос.
     *
     * @return array<int, string> table:field.
     */
    public function collect(SmartTableDefinition $tableDefinition, ListQuery $listQuery): array
    {
        $keys = $listQuery->select() ?? array_keys($tableDefinition->getMap());
        foreach (array_keys($listQuery->sort()) as $sortField) {
            $keys[] = $sortField;
        }

        $keys = array_merge($keys, $this->filterKeys($listQuery->filter()));
        $tags = [];
        foreach (array_unique($keys) as $fieldName) {
            foreach ($this->tagsForKey($tableDefinition, $fieldName) as $fieldTag) {
                $tags[$fieldTag] = true;
            }
        }

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
     * Ключи фильтра.
     *
     * @param FilterGroup|null $filterGroup Дерево.
     *
     * @return array<int, string> Ключи.
     */
    private function filterKeys(?FilterGroup $filterGroup): array
    {
        if ($filterGroup === null) {
            return [];
        }

        $fieldNames = [];
        foreach ($filterGroup->children() as $child) {
            if ($child instanceof FilterCondition) {
                $fieldNames[] = $child->fieldName();
                continue;
            }

            array_push($fieldNames, ...$this->filterKeys($child));
        }

        return $fieldNames;
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
