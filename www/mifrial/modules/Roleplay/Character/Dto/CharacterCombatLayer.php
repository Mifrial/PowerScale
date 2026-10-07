<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Dto;

use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Слой защиты или сопротивления для расчёта удара. В лист не пишется.
 */
final class CharacterCombatLayer
{
    /**
     * Создаёт слой.
     *
     * @param string $kind Вид: defense или resistance.
     * @param DimensionalNumber $value Пара значения.
     * @param int|null $durability Порог или абсолютная применимость.
     * @param string $sourceCode Источник для сложения.
     * @param string|null $damageTypeCode Тип урона или его отсутствие.
     *
     * @return void
     */
    public function __construct(
        private readonly string $kind,
        private readonly DimensionalNumber $value,
        private readonly ?int $durability,
        private readonly string $sourceCode,
        private readonly ?string $damageTypeCode,
    ) {
    }

    /**
     * Вид слоя.
     *
     * @return string defense или resistance.
     */
    public function getKind(): string
    {
        return $this->kind;
    }

    /**
     * Пара значения.
     *
     * @return DimensionalNumber База и размер.
     */
    public function getValue(): DimensionalNumber
    {
        return $this->value;
    }

    /**
     * Порог применимости.
     *
     * @return int|null Число или null.
     */
    public function getDurability(): ?int
    {
        return $this->durability;
    }

    /**
     * Источник сложения.
     *
     * @return string Код.
     */
    public function getSourceCode(): string
    {
        return $this->sourceCode;
    }

    /**
     * Тип урона.
     *
     * @return string|null Код или null.
     */
    public function getDamageTypeCode(): ?string
    {
        return $this->damageTypeCode;
    }
}
