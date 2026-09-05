<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Dto;

use Mifrial\Core\SmartTable\Exception\Map\MapInvalidException;

/**
 * Агрегат MAX колонки в select, не колонка карты.
 */
final class MaxField
{
    /**
     * Создаёт MAX поля с alias ряда.
     *
     * @param string $fieldName Колонка карты.
     * @param string $alias Имя ключа в результате.
     *
     * @return void
     *
     * @throws MapInvalidException Если имя или alias не шаблон.
     */
    public function __construct(
        private readonly string $fieldName,
        private readonly string $alias,
    ) {
        if (preg_match('/^[a-z][a-z0-9_]*$/', $fieldName) !== 1) {
            throw new MapInvalidException('Aggregate field name is invalid');
        }

        if (preg_match('/^[a-z][a-z0-9_]*$/', $alias) !== 1) {
            throw new MapInvalidException('Aggregate alias is invalid');
        }
    }

    /**
     * Возвращает имя колонки.
     *
     * @return string Поле карты.
     */
    public function fieldName(): string
    {
        return $this->fieldName;
    }

    /**
     * Возвращает alias ряда.
     *
     * @return string Ключ результата.
     */
    public function alias(): string
    {
        return $this->alias;
    }
}
