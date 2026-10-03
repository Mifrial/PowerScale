<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec;

use Mifrial\Roleplay\Rule\Value\CharacteristicNumber;

/**
 * Spec характеристики.
 */
final class CharacteristicSpec implements RuleSpec
{
    /**
     * Создаёт значение.
     *
     * @param ?string $formula Производная min или max.
     * @param ?string $group Группа.
     * @param bool|CharacteristicNumber $automatic Автополучение.
     * @param bool $damageEndurance Выносливость к урону.
     * @param bool $willpower Воля.
     * @param bool $concentrationThreshold Порог концентрации.
     * @param bool $concentrationToken Жетон концентрации.
     * @param bool $unstableRoll Бросок неустойчивости.
     * @param bool $initiative Инициатива.
     * @param bool $dodgeSoak Поглощение уклонения.
     * @param CharacteristicBaseFrom|null $baseFrom База из другой характеристики.
     * @param array<int, string> $weaponMasteryProfiles Профили мастерства.
     *
     * @return void
     */
    public function __construct(
        private readonly ?string $formula,
        private readonly ?string $group,
        private readonly bool|CharacteristicNumber $automatic,
        private readonly bool $damageEndurance,
        private readonly bool $willpower,
        private readonly bool $concentrationThreshold,
        private readonly bool $concentrationToken,
        private readonly bool $unstableRoll,
        private readonly bool $initiative,
        private readonly bool $dodgeSoak,
        private readonly ?CharacteristicBaseFrom $baseFrom,
        private readonly array $weaponMasteryProfiles,
    ) {
    }

    /**
     * Тип правила.
     *
     * @return string Код.
     */
    public function getRuleType(): string
    {
        return 'characteristic';
    }

    /**
     * Производная min или max.
     *
     * @return ?string Значение.
     */
    public function getFormula(): ?string
    {
        return $this->formula;
    }
    /**
     * Группа.
     *
     * @return ?string Значение.
     */
    public function getGroup(): ?string
    {
        return $this->group;
    }
    /**
     * Автополучение.
     *
     * @return bool|CharacteristicNumber Значение.
     */
    public function getAutomatic(): bool|CharacteristicNumber
    {
        return $this->automatic;
    }
    /**
     * Выносливость к урону.
     *
     * @return bool Значение.
     */
    public function isDamageEndurance(): bool
    {
        return $this->damageEndurance;
    }
    /**
     * Воля.
     *
     * @return bool Значение.
     */
    public function isWillpower(): bool
    {
        return $this->willpower;
    }
    /**
     * Порог концентрации.
     *
     * @return bool Значение.
     */
    public function isConcentrationThreshold(): bool
    {
        return $this->concentrationThreshold;
    }
    /**
     * Жетон концентрации.
     *
     * @return bool Значение.
     */
    public function isConcentrationToken(): bool
    {
        return $this->concentrationToken;
    }
    /**
     * Бросок неустойчивости.
     *
     * @return bool Значение.
     */
    public function isUnstableRoll(): bool
    {
        return $this->unstableRoll;
    }
    /**
     * Инициатива.
     *
     * @return bool Значение.
     */
    public function isInitiative(): bool
    {
        return $this->initiative;
    }
    /**
     * Поглощение уклонения.
     *
     * @return bool Значение.
     */
    public function isDodgeSoak(): bool
    {
        return $this->dodgeSoak;
    }

    /**
     * База из другой характеристики.
     *
     * @return CharacteristicBaseFrom|null Ссылка или null.
     */
    public function getBaseFrom(): ?CharacteristicBaseFrom
    {
        return $this->baseFrom;
    }

    /**
     * Профили мастерства оружия.
     *
     * @return array<int, string> Профили.
     */
    public function getWeaponMasteryProfiles(): array
    {
        return $this->weaponMasteryProfiles;
    }
}
