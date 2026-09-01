<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Service;

use JsonException;
use Mifrial\Core\SmartTable\Dto\FilterCondition;
use Mifrial\Core\SmartTable\Dto\FilterGroup;
use Mifrial\Core\SmartTable\Dto\ListQuery;
use Mifrial\Core\SmartTable\Exception\Cache\CacheConfigInvalidException;

/**
 * Стабильный ключ getList: стол плюс json запроса.
 */
final class ListCacheKey
{
    /**
     * Собирает ключ списка.
     *
     * @param string $tableName Физическое имя.
     * @param ListQuery $listQuery Запрос.
     *
     * @return string Ключ.
     *
     * @throws CacheConfigInvalidException Если json не собрался.
     */
    public function make(string $tableName, ListQuery $listQuery): string
    {
        try {
            return json_encode(
                [
                    'table' => $tableName,
                    'filter' => $this->filterTree($listQuery->filter()),
                    'sort' => $listQuery->sort(),
                    'limit' => $listQuery->limit(),
                    'offset' => $listQuery->offset(),
                    'countTotal' => $listQuery->countTotal(),
                    'select' => $listQuery->select(),
                ],
                JSON_THROW_ON_ERROR,
            );
        } catch (JsonException) {
            throw new CacheConfigInvalidException('Cache list key is invalid');
        }
    }

    /**
     * Разворачивает фильтр в массив для ключа.
     *
     * @param FilterGroup|null $filterGroup Дерево.
     *
     * @return array<string, mixed>|null Узел.
     */
    private function filterTree(?FilterGroup $filterGroup): ?array
    {
        if ($filterGroup === null) {
            return null;
        }

        $children = [];
        foreach ($filterGroup->children() as $child) {
            if ($child instanceof FilterGroup) {
                $children[] = $this->filterTree($child);
                continue;
            }

            if ($child instanceof FilterCondition) {
                $children[] = [
                    'field' => $child->fieldName(),
                    'operator' => $child->operator(),
                    'operand' => $child->operand(),
                ];
            }
        }

        return ['logic' => $filterGroup->logic(), 'children' => $children];
    }
}
