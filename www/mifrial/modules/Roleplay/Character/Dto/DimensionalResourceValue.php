<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Dto;

use Mifrial\Roleplay\Character\Exception\CharacterInvalidException;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Native-размерное текущее значение ресурса.
 */
final class DimensionalResourceValue implements ResourceValue
{
    /**
     * Создаёт неотрицательное размерное значение.
     *
     * @param DimensionalNumber $value Current value.
     *
     * @return void
     *
     * @throws CharacterInvalidException If the base is negative.
     */
    public function __construct(
        private readonly DimensionalNumber $value,
    ) {
        if ($value->getBase() < 0) {
            throw new CharacterInvalidException('Resource dimensional value is negative');
        }
    }

    /**
     * Возвращает размерное число.
     *
     * @return DimensionalNumber Native value object.
     */
    public function getValue(): DimensionalNumber
    {
        return $this->value;
    }

    /**
     * Сериализует размерное значение без flattening.
     *
     * @return array{base: int, size: int} Native pair.
     */
    public function toNative(): array
    {
        return [
            'base' => $this->value->getBase(),
            'size' => $this->value->getSize(),
        ];
    }
}
