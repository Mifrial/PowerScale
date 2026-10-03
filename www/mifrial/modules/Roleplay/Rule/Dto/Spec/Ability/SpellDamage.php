<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability;

use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Урон заклинания от опыта.
 */
final class SpellDamage
{
    /**
     * Создаёт урон.
     *
     * @param string $damageTypeCode Тип урона.
     * @param string $experienceKeywordCode Ключ опыта.
     * @param array<int, SpellDamageStep> $steps Ступени.
     * @param int|null $freeIpari Свободные ипари.
     * @param int|null $sizePerExtraIpari Размер на лишний ипари.
     * @param DimensionalNumber|null $min Минимум.
     *
     * @return void
     */
    public function __construct(
        private readonly string $damageTypeCode,
        private readonly string $experienceKeywordCode,
        private readonly array $steps,
        private readonly ?int $freeIpari,
        private readonly ?int $sizePerExtraIpari,
        private readonly ?DimensionalNumber $min,
    ) {
    }

    /**
     * Тип урона.
     *
     * @return string Код.
     */
    public function getDamageTypeCode(): string
    {
        return $this->damageTypeCode;
    }

    /**
     * Ключ опыта.
     *
     * @return string Код.
     */
    public function getExperienceKeywordCode(): string
    {
        return $this->experienceKeywordCode;
    }

    /**
     * Ступени.
     *
     * @return array<int, SpellDamageStep> Список.
     */
    public function getSteps(): array
    {
        return $this->steps;
    }

    /**
     * Свободные ипари.
     *
     * @return int|null Число или null.
     */
    public function getFreeIpari(): ?int
    {
        return $this->freeIpari;
    }

    /**
     * Размер на лишний ипари.
     *
     * @return int|null Число или null.
     */
    public function getSizePerExtraIpari(): ?int
    {
        return $this->sizePerExtraIpari;
    }

    /**
     * Минимум.
     *
     * @return DimensionalNumber|null Число или null.
     */
    public function getMin(): ?DimensionalNumber
    {
        return $this->min;
    }
}
