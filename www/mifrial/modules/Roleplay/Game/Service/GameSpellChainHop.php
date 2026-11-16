<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\SpellChain;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\SpellDamage;
use Mifrial\Roleplay\Rule\Value\CharacteristicNumber;

/**
 * Прыжок цепи: следующая цель и урон от предыдущего попадания.
 *
 * Всё берётся из формы chain карточки улучшения. Неизвестное значение
 * формы — отказ, а не тихий проход.
 */
final class GameSpellChainHop
{
    /**
     * Создаёт правила прыжка.
     *
     * @param GameSpellFalloff $falloff Снижение по дистанции.
     *
     * @return void
     */
    public function __construct(
        private readonly GameSpellFalloff $falloff = new GameSpellFalloff(),
    ) {
    }

    /**
     * Механика исполняет только документированные значения формы.
     *
     * @param SpellChain $chain Цепь.
     *
     * @return void
     *
     * @throws GameInvalidException Если форма цепи неизвестна.
     */
    public function assertSupported(SpellChain $chain): void
    {
        if ($chain->getRetarget() !== 'from_last_hit' || $chain->getSameTarget() !== 'via_other') {
            throw new GameInvalidException('Game spell chain is not supported');
        }
    }

    /**
     * Можно ли ударить следующую цель: та же цель сразу — нельзя.
     *
     * @param SpellChain $chain Цепь.
     * @param string $nextKey Следующая цель.
     * @param string $lastKey Последняя поражённая цель.
     *
     * @return bool true, если цель допустима.
     *
     * @throws GameInvalidException Если форма цепи неизвестна.
     */
    public function canHit(SpellChain $chain, string $nextKey, string $lastKey): bool
    {
        $this->assertSupported($chain);

        return $nextKey !== $lastKey;
    }

    /**
     * Урон прыжка: снижение по дистанции, затем размеры, не ниже минимума цепи.
     *
     * @param SpellChain $chain Цепь.
     * @param SpellDamage|null $damage Карточка урона; null — только размеры.
     * @param CharacteristicNumber $previous Урон предыдущего попадания.
     * @param int $distanceIpari Дистанция прыжка в ипари.
     *
     * @return CharacteristicNumber|null Урон или null, если цепь оборвалась.
     *
     * @throws GameInvalidException Если форма цепи неизвестна.
     */
    public function nextAmount(
        SpellChain $chain,
        ?SpellDamage $damage,
        CharacteristicNumber $previous,
        int $distanceIpari,
    ): ?CharacteristicNumber {
        $this->assertSupported($chain);
        $afterFalloff = $damage === null
            ? $previous
            : $this->falloff->apply($previous, $damage, $distanceIpari);
        if (!$afterFalloff instanceof CharacteristicNumber) {
            return null;
        }

        $perHop = $chain->getDamageSizePerHop();
        $reduced = $perHop === 0
            ? $afterFalloff
            : $afterFalloff->modify(-CharacteristicNumber::BASE_STEP * $perHop);
        if ($reduced->characteristicDiffTo($chain->getMin()) < 0) {
            return null;
        }

        return $reduced;
    }
}
