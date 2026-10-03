<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Dto;

/**
 * Поля надетого предмета из DEC-086: штраф, потолок ловкости, лимиты.
 */
final class CharacterEquippedModifier
{
    /**
     * Создаёт снимок.
     *
     * @param string $ruleCode Код предмета.
     * @param int|null $strengthPenalty Штраф брони или null.
     * @param int|null $maxAgility Потолок брони или null.
     * @param array<int, CharacterCharacteristicLimit> $characteristicLimits Лимиты брони и щита.
     *
     * @return void
     */
    public function __construct(
        private readonly string $ruleCode,
        private readonly ?int $strengthPenalty,
        private readonly ?int $maxAgility,
        private readonly array $characteristicLimits,
    ) {
    }

    /**
     * Код предмета.
     *
     * @return string Код.
     */
    public function getRuleCode(): string
    {
        return $this->ruleCode;
    }

    /**
     * Штраф брони.
     *
     * @return int|null Число или null.
     */
    public function getStrengthPenalty(): ?int
    {
        return $this->strengthPenalty;
    }

    /**
     * Потолок брони.
     *
     * @return int|null Число или null.
     */
    public function getMaxAgility(): ?int
    {
        return $this->maxAgility;
    }

    /**
     * Лимиты характеристик.
     *
     * @return array<int, CharacterCharacteristicLimit> Список.
     */
    public function getCharacteristicLimits(): array
    {
        return $this->characteristicLimits;
    }
}
