<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec;

/**
 * Эффект ступени возраста.
 */
final class AgeEffect
{
    /**
     * Создаёт эффект.
     *
     * @param string $characteristicCode Код характеристики.
     * @param int $delta Смещение.
     * @param string|null $scope Условность.
     *
     * @return void
     */
    public function __construct(
        private readonly string $characteristicCode,
        private readonly int $delta,
        private readonly ?string $scope,
    ) {
    }

    /**
     * Код характеристики.
     *
     * @return string Код.
     */
    public function getCharacteristicCode(): string
    {
        return $this->characteristicCode;
    }

    /**
     * Смещение.
     *
     * @return int Число.
     */
    public function getDelta(): int
    {
        return $this->delta;
    }

    /**
     * Условность.
     *
     * @return string|null Текст или null.
     */
    public function getScope(): ?string
    {
        return $this->scope;
    }
}
