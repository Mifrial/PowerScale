<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability;

/**
 * Узел maneuver_boost: подмена эффекта объявленного манёвра при высоком РУ.
 *
 * Усиление не объявляется отдельной карточкой: движок сам находит купленное
 * улучшение, которое ссылается на манёвр. Требования покупки карточки живут
 * в обычном `requirements` способности, а не в узле.
 */
final class ManeuverBoost
{
    /**
     * Создаёт описание усиления.
     *
     * @param array<int, string> $maneuvers Коды усиливаемых манёвров.
     * @param int $minSuccess Минимальный РУ удара.
     * @param int|null $minWeaponMastery Минимум мастерства оружия удара или null.
     * @param array<int, StrikeManeuverEffectSelector> $replaces Заменяемые эффекты.
     * @param array<int, StrikeManeuverEffect> $effects Новые эффекты.
     *
     * @return void
     */
    public function __construct(
        private readonly array $maneuvers,
        private readonly int $minSuccess,
        private readonly ?int $minWeaponMastery,
        private readonly array $replaces,
        private readonly array $effects,
    ) {
    }

    /**
     * Коды усиливаемых манёвров.
     *
     * @return array<int, string> Коды.
     */
    public function getManeuvers(): array
    {
        return $this->maneuvers;
    }

    /**
     * Минимальный РУ удара.
     *
     * @return int Порог.
     */
    public function getMinSuccess(): int
    {
        return $this->minSuccess;
    }

    /**
     * Минимум мастерства оружия удара.
     *
     * @return int|null Уровень или null.
     */
    public function getMinWeaponMastery(): ?int
    {
        return $this->minWeaponMastery;
    }

    /**
     * Заменяемые эффекты.
     *
     * @return array<int, StrikeManeuverEffectSelector> Селекторы.
     */
    public function getReplaces(): array
    {
        return $this->replaces;
    }

    /**
     * Новые эффекты.
     *
     * @return array<int, StrikeManeuverEffect> Эффекты.
     */
    public function getEffects(): array
    {
        return $this->effects;
    }
}
