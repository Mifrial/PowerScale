<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Dto;

use Mifrial\Core\SmartTable\Exception\Map\MapInvalidException;

/**
 * Скалярный подзапрос другой карты в операнде фильтра.
 */
final class SubqueryValue
{
    private readonly FilterGroup $filter;

    /**
     * Создаёт подзапрос: стол, поле, filter диалекта, optional coalesce.
     *
     * @param string $table Class-string definition или физ. имя словаря.
     * @param string $field Имя select внутренней карты.
     * @param array<string|int, mixed> $filter Диалект фильтра той карты.
     * @param mixed $coalesce Скаляр COALESCE или null.
     *
     * @return void
     *
     * @throws MapInvalidException Если стол, поле или filter некорректны.
     */
    public function __construct(
        private readonly string $table,
        private readonly string $field,
        array $filter,
        private readonly mixed $coalesce = null,
    ) {
        if ($this->table === '') {
            throw new MapInvalidException('Subquery table is invalid');
        }

        if (preg_match('/^[a-z][a-z0-9_]*$/', $this->field) !== 1) {
            throw new MapInvalidException('Subquery field name is invalid');
        }

        $this->filter = (new FilterTreeParser())->parseGroup($filter);
    }

    /**
     * Возвращает class-string или физ. имя.
     *
     * @return string Как написал сосед.
     */
    public function table(): string
    {
        return $this->table;
    }

    /**
     * Возвращает поле select подзапроса.
     *
     * @return string Имя колонки внутренней карты.
     */
    public function field(): string
    {
        return $this->field;
    }

    /**
     * Возвращает внутренний фильтр.
     *
     * @return FilterGroup Дерево WHERE подзапроса.
     */
    public function filter(): FilterGroup
    {
        return $this->filter;
    }

    /**
     * Возвращает COALESCE или null.
     *
     * @return mixed Скаляр или null.
     */
    public function coalesce(): mixed
    {
        return $this->coalesce;
    }
}
