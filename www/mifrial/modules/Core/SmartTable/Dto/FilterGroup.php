<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Dto;

/**
 * Группа условий фильтра с AND/OR.
 */
final class FilterGroup
{
    /**
     * Создаёт группу.
     *
     * @param string $logic AND или OR.
     * @param array<int, FilterGroup|FilterCondition> $children Условия и вложенные группы.
     *
     * @return void
     */
    public function __construct(
        private readonly string $logic,
        private readonly array $children,
    ) {
    }

    /**
     * Возвращает связку детей.
     *
     * @return string AND или OR.
     */
    public function logic(): string
    {
        return $this->logic;
    }

    /**
     * Возвращает детей группы.
     *
     * @return array<int, FilterGroup|FilterCondition> Дети.
     */
    public function children(): array
    {
        return $this->children;
    }
}
