<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Dto;

use Mifrial\Roleplay\Character\Exception\CharacterInvalidException;

/**
 * Native-скаляр текущего значения ресурса.
 */
final class ScalarResourceValue implements ResourceValue
{
    /**
     * Создаёт неотрицательное скалярное значение.
     *
     * @param int $value Current value.
     *
     * @return void
     *
     * @throws CharacterInvalidException If the value is negative.
     */
    public function __construct(
        private readonly int $value,
    ) {
        if ($value < 0) {
            throw new CharacterInvalidException('Resource scalar value is negative');
        }
    }

    /**
     * Возвращает скаляр.
     *
     * @return int Native integer.
     */
    public function getValue(): int
    {
        return $this->value;
    }

    /**
     * Сериализует скаляр без преобразования.
     *
     * @return int Native integer.
     */
    public function toNative(): int
    {
        return $this->value;
    }
}
