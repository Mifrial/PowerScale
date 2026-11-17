<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability;

/**
 * Модификатор сотворения.
 */
final class SpellUpgrade
{
    /**
     * Создаёт модификатор.
     *
     * @param int $actionPointDelta Дельта ОД.
     * @param SpellChargeCap|null $chargeCap Потолок зарядов.
     * @param int|null $checkAdvantage Помеха сотворения.
     * @param bool $anyPath Любой путь.
     * @param int|null $resistancePenetrationPerStep Пробитие на шаг.
     * @param SpellChain|null $chain Цепь.
     * @param array<int, string> $keywordCodes Признаки, которые обязано иметь заклинание.
     * @param string|null $checkAdvantageSource Источник преимущества; одинаковый — одно преимущество.
     * @param SpellUpgradeRange|null $range Замена касания дальностью.
     * @param bool $statePermanence Состояние остаётся после срока заклинания.
     *
     * @return void
     */
    public function __construct(
        private readonly int $actionPointDelta,
        private readonly ?SpellChargeCap $chargeCap,
        private readonly ?int $checkAdvantage,
        private readonly bool $anyPath,
        private readonly ?int $resistancePenetrationPerStep,
        private readonly ?SpellChain $chain,
        private readonly array $keywordCodes = [],
        private readonly ?string $checkAdvantageSource = null,
        private readonly ?SpellUpgradeRange $range = null,
        private readonly bool $statePermanence = false,
    ) {
    }

    /**
     * Дельта ОД.
     *
     * @return int Число.
     */
    public function getActionPointDelta(): int
    {
        return $this->actionPointDelta;
    }

    /**
     * Потолок зарядов.
     *
     * @return SpellChargeCap|null Потолок или null.
     */
    public function getChargeCap(): ?SpellChargeCap
    {
        return $this->chargeCap;
    }

    /**
     * Помеха сотворения.
     *
     * @return int|null Число или null.
     */
    public function getCheckAdvantage(): ?int
    {
        return $this->checkAdvantage;
    }

    /**
     * Любой путь.
     *
     * @return bool true, если any_path.
     */
    public function isAnyPath(): bool
    {
        return $this->anyPath;
    }

    /**
     * Пробитие на шаг.
     *
     * @return int|null Число или null.
     */
    public function getResistancePenetrationPerStep(): ?int
    {
        return $this->resistancePenetrationPerStep;
    }

    /**
     * Цепь.
     *
     * @return SpellChain|null Цепь или null.
     */
    public function getChain(): ?SpellChain
    {
        return $this->chain;
    }

    /**
     * Признаки заклинания.
     *
     * @return array<int, string> Коды.
     */
    public function getKeywordCodes(): array
    {
        return $this->keywordCodes;
    }

    /**
     * Источник преимущества.
     *
     * @return string|null Код или null.
     */
    public function getCheckAdvantageSource(): ?string
    {
        return $this->checkAdvantageSource;
    }

    /**
     * Дальность вместо касания.
     *
     * @return SpellUpgradeRange|null Форма или null.
     */
    public function getRange(): ?SpellUpgradeRange
    {
        return $this->range;
    }

    /**
     * Состояние остаётся после срока.
     *
     * @return bool true, если состояние постоянное.
     */
    public function isStatePermanence(): bool
    {
        return $this->statePermanence;
    }
}
