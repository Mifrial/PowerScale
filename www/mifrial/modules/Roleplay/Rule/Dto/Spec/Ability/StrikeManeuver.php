<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability;

/**
 * Узел strike_maneuver: манёвр, который объявляют сразу после удара.
 *
 * Карточка остаётся обычным действием: цену платит игрок, когда выбирает
 * манёвр. Узел задаёт условие прошлого удара и типизированные эффекты для
 * его цели.
 */
final class StrikeManeuver
{
    /**
     * Создаёт описание манёвра.
     *
     * @param int $minSuccess Минимальный РУ прошлого удара.
     * @param int|null $minWeaponMastery Минимум мастерства оружия удара или null.
     * @param array<int, StrikeManeuverEffect> $effects Эффекты для цели удара.
     *
     * @return void
     */
    public function __construct(
        private readonly int $minSuccess,
        private readonly ?int $minWeaponMastery,
        private readonly array $effects,
    ) {
    }

    /**
     * Минимальный РУ прошлого удара.
     *
     * @return int Порог.
     */
    public function getMinSuccess(): int
    {
        return $this->minSuccess;
    }

    /**
     * Минимум мастерства оружия прошлого удара.
     *
     * @return int|null Уровень или null.
     */
    public function getMinWeaponMastery(): ?int
    {
        return $this->minWeaponMastery;
    }

    /**
     * Эффекты манёвра.
     *
     * @return array<int, StrikeManeuverEffect> Эффекты.
     */
    public function getEffects(): array
    {
        return $this->effects;
    }
}
