<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Dto;

/**
 * Одно условие фильтра списка.
 */
final class FilterCondition
{
    /**
     * Создаёт условие.
     *
     * @param string $fieldName Имя поля карты.
     * @param string $operator Префикс (=, !=, @, …).
     * @param mixed $operand Значение из массива фильтра.
     *
     * @return void
     */
    public function __construct(
        private readonly string $fieldName,
        private readonly string $operator,
        private readonly mixed $operand,
    ) {
    }

    /**
     * Возвращает имя поля.
     *
     * @return string Имя.
     */
    public function fieldName(): string
    {
        return $this->fieldName;
    }

    /**
     * Возвращает оператор.
     *
     * @return string Префикс.
     */
    public function operator(): string
    {
        return $this->operator;
    }

    /**
     * Возвращает операнд.
     *
     * @return mixed Значение фильтра.
     */
    public function operand(): mixed
    {
        return $this->operand;
    }
}
