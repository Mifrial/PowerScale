<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Item\Op;

/**
 * Ветка characteristic_limit.
 */
final class CharacteristicLimitOp implements ItemModifierOp
{
    /**
     * Создаёт ветку.
     *
     * @param string $characteristicCode Характеристика.
     * @param ?int $delta Сдвиг базы.
     * @param ?int $addSize Сдвиг размера.
     *
     * @return void
     */
    public function __construct(
        private readonly string $characteristicCode,
        private readonly ?int $delta,
        private readonly ?int $addSize,
    ) {
    }

    /**
     * Дискриминатор.
     *
     * @return string Код.
     */
    public function getType(): string
    {
        return 'characteristic_limit';
    }

    /**
     * Характеристика.
     *
     * @return string Код.
     */
    public function getCharacteristicCode(): string
    {
        return $this->characteristicCode;
    }

    /**
     * Сдвиг.
     *
     * @return ?int Значение.
     */
    public function getDelta(): ?int
    {
        return $this->delta;
    }

    /**
     * Размер.
     *
     * @return ?int Значение.
     */
    public function getAddSize(): ?int
    {
        return $this->addSize;
    }
}
