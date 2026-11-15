<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\SpellDuration;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Читает форму spell.duration карточки: поддержанные виды, цена и лимит.
 */
final class GameSpellDurationRules
{
    private const INSTANT = 'instant';

    private const SUSTAINED = 'sustained';

    /**
     * Проверяет, что вид длительности поддержан рантаймом M6.
     *
     * @param SpellDuration $duration Длительность карточки.
     *
     * @return void
     *
     * @throws GameInvalidException Если вид ещё не поддержан.
     */
    public function assertSupported(SpellDuration $duration): void
    {
        if ($this->isInstant($duration) || $this->isSustained($duration)) {
            return;
        }

        throw new GameInvalidException('Game spell duration is not supported');
    }

    /**
     * Мгновенное заклинание.
     *
     * @param SpellDuration $duration Длительность карточки.
     *
     * @return bool true, если instant.
     */
    public function isInstant(SpellDuration $duration): bool
    {
        return $duration->getType() === self::INSTANT;
    }

    /**
     * Поддерживаемое заклинание.
     *
     * @param SpellDuration $duration Длительность карточки.
     *
     * @return bool true, если sustained.
     */
    public function isSustained(SpellDuration $duration): bool
    {
        return $duration->getType() === self::SUSTAINED;
    }

    /**
     * Цена поддержания или обновления за ход.
     *
     * @param SpellDuration $duration Длительность карточки.
     *
     * @return int Неотрицательное число ОД.
     */
    public function actionCost(SpellDuration $duration): int
    {
        $cost = $duration->getActionCost();
        if ($cost instanceof DimensionalNumber) {
            return max(0, $cost->toInteger());
        }

        return is_int($cost) ? max(0, $cost) : 0;
    }
}
