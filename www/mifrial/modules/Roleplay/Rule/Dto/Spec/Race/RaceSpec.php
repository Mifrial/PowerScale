<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Race;

use Mifrial\Roleplay\Rule\Dto\Spec\RuleSpec;

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
     *
     * @return void
     */
    public function __construct(
        private readonly ?string $parentRaceCode,
        private readonly int $costOs,
        private readonly array $characteristics,
        private readonly array $abilities,
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
}
