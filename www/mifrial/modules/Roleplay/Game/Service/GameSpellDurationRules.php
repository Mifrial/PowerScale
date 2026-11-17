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

    private const LINGERING = 'lingering';

    /**
     * Проверяет, что вид длительности поддержан рантаймом.
     *
     * @param SpellDuration $duration Длительность карточки.
     *
     * @return void
     *
     * @throws GameInvalidException Если вид ещё не поддержан.
     */
    public function assertSupported(SpellDuration $duration): void
    {
        if ($this->isInstant($duration) || $this->isSustained($duration) || $this->isLingering($duration)) {
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
     * Длительное заклинание с таймером.
     *
     * @param SpellDuration $duration Длительность карточки.
     *
     * @return bool true, если lingering.
     */
    public function isLingering(SpellDuration $duration): bool
    {
        return $duration->getType() === self::LINGERING;
    }

    /**
     * Срок lingering в собственных ходах цели.
     *
     * «1 минута» = 10 ходов, «1 час» = 600. Это соглашение карточек и M16:
     * единица времени переводится в ходы, а не в реальные секунды.
     *
     * @param SpellDuration $duration Длительность карточки.
     *
     * @return int Положительный срок в тиках.
     *
     * @throws GameInvalidException Если лимита нет или единица неизвестна.
     */
    public function lingerTicks(SpellDuration $duration): int
    {
        if (!$this->isLingering($duration)) {
            throw new GameInvalidException('Game spell duration is not lingering');
        }

        $value = $duration->getLimit();
        $ticks = $value instanceof DimensionalNumber ? $value->toInteger() : $value;
        if (!is_int($ticks) || $ticks < 1) {
            throw new GameInvalidException('Game spell lingering limit is invalid');
        }

        $multiplier = match ($duration->getUnit()) {
            'turn' => 1,
            'minute' => 10,
            'hour' => 600,
            default => throw new GameInvalidException('Game spell lingering unit is invalid'),
        };

        return $ticks * $multiplier;
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
