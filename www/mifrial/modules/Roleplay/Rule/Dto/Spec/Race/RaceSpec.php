<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Race;

use Mifrial\Roleplay\Rule\Dto\Spec\RuleSpec;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Spec расы: родитель, цена ОС, характеристики и способности.
 */
final class RaceSpec implements RuleSpec
{
    /**
     * Создаёт spec.
     *
     * @param string|null $parentRaceCode Предок.
     * @param int $costOs Цена ОС.
     * @param array<int, RaceCharacteristic> $characteristics Характеристики.
     * @param array<int, RaceAbilityRef> $abilities Способности.
     * @param DimensionalNumber|null $movementStep Шаг расы.
     *
     * @return void
     */
    public function __construct(
        private readonly ?string $parentRaceCode,
        private readonly int $costOs,
        private readonly array $characteristics,
        private readonly array $abilities,
        private readonly ?DimensionalNumber $movementStep = null,
    ) {
    }

    /**
     * Тип правила.
     *
     * @return string Код.
     */
    public function getRuleType(): string
    {
        return 'race';
    }

    /**
     * Код предка.
     *
     * @return string|null Код или null.
     */
    public function getParentRaceCode(): ?string
    {
        return $this->parentRaceCode;
    }

    /**
     * Цена в очках создания.
     *
     * @return int Цена.
     */
    public function getCostOs(): int
    {
        return $this->costOs;
    }

    /**
     * Стартовые характеристики.
     *
     * @return array<int, RaceCharacteristic> Список.
     */
    public function getCharacteristics(): array
    {
        return $this->characteristics;
    }

    /**
     * Ссылки на способности.
     *
     * @return array<int, RaceAbilityRef> Список.
     */
    public function getAbilities(): array
    {
        return $this->abilities;
    }

    /**
     * Шаг расы. Нет поля — 1 ипари.
     *
     * @return DimensionalNumber Шаг.
     */
    public function getMovementStep(): DimensionalNumber
    {
        return $this->movementStep ?? new DimensionalNumber(1, 0);
    }
}
