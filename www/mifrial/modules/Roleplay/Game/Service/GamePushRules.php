<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\PushSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\StateSpec;
use Mifrial\Roleplay\Rule\Value\CharacteristicNumber;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Толчок: проверка, Устойчивость, Неустойчивость и отброс.
 *
 * Проверка и урон считаются общим боевым потоком удара; здесь только
 * толчковая арифметика.
 */
final class GamePushRules
{
    /**
     * Создаёт правила толчка.
     *
     * @param GameStrikeRules $rules Срез правил.
     *
     * @return void
     */
    public function __construct(
        private readonly GameStrikeRules $rules,
    ) {
    }

    /**
     * Толчок карточки действия или null.
     *
     * @param GameRecord $game Игра.
     * @param string $actionRuleCode Действие.
     *
     * @return PushSpec|null Толчок.
     */
    public function pushOf(GameRecord $game, string $actionRuleCode): ?PushSpec
    {
        return $this->rules->pushOf($game->getSpaceId(), $game->getRulesRevision(), $actionRuleCode);
    }

    /**
     * Код проверки толчка.
     *
     * @param PushSpec $push Толчок.
     *
     * @return string Код проверки.
     */
    public function checkCode(PushSpec $push): string
    {
        return $push->getCheck();
    }

    /**
     * Сдвиг урона профиля в пунктах.
     *
     * @param PushSpec $push Толчок.
     *
     * @return int Пункты.
     */
    public function damageModifier(PushSpec $push): int
    {
        return $push->getDamageModifier();
    }

    /**
     * Код состояния Неустойчивости.
     *
     * @param GameRecord $game Игра.
     *
     * @return string Код.
     *
     * @throws GameInvalidException Если состояния нет или их несколько.
     */
    public function unstableCode(GameRecord $game): string
    {
        $slice = $this->rules->ruleSlice($game->getSpaceId(), $game->getRulesRevision());
        $matched = [];
        foreach ($slice->getLiveRules() as $rule) {
            $spec = $rule->isSpecBroken() ? null : $rule->getSpec();
            if ($spec instanceof StateSpec && $spec->isUnstable()) {
                $matched[] = $rule->getCode();
            }
        }

        if (count($matched) !== 1) {
            throw new GameInvalidException('Game push unstable is invalid');
        }

        return $matched[0];
    }

    /**
     * Минимальная Устойчивость: вес минус один размер.
     *
     * @param array<string, mixed> $sheet Лист цели.
     *
     * @return DimensionalNumber Минимум.
     *
     * @throws GameInvalidException Если веса нет.
     */
    public function stabilityFloor(array $sheet): DimensionalNumber
    {
        $weight = $this->rules->characteristicOf($sheet, 'weight');

        return $this->characteristic($weight)->modify(-3);
    }

    /**
     * Устойчивость защитника для толчка.
     *
     * Игнор и провал уклона — минимум. Блок: провал — Сила минус РУ, успех —
     * Сила плюс РУ (не выше потолка). Ниже минимума не опускается.
     *
     * @param PushSpec $push Толчок.
     * @param string $reaction Реакция.
     * @param int $rating Знаковая маржа проверки.
     * @param DimensionalNumber $defenderStrength Сила защитника.
     * @param DimensionalNumber $floor Минимальная Устойчивость.
     *
     * @return DimensionalNumber Устойчивость.
     */
    public function stability(
        PushSpec $push,
        string $reaction,
        int $rating,
        DimensionalNumber $defenderStrength,
        DimensionalNumber $floor,
    ): DimensionalNumber {
        $minimum = $this->characteristic($floor);
        if ($reaction !== 'block') {
            return $minimum;
        }

        $delta = $rating > 0
            ? -$rating
            : min(-$rating, $push->getStabilityBonusCap());
        $value = $this->characteristic($defenderStrength)->modify($delta);

        return $value->characteristicCompare($minimum) < 0 ? $minimum : $value;
    }

    /**
     * Неустойчивость: разница Сил, не ниже нуля, с делителем типа урона.
     *
     * @param PushSpec $push Толчок.
     * @param DimensionalNumber $attackerStrength Сила атакующего.
     * @param DimensionalNumber $stability Устойчивость защитника.
     * @param string|null $damageTypeCode Тип урона.
     *
     * @return int Неустойчивость.
     */
    public function unstable(
        PushSpec $push,
        DimensionalNumber $attackerStrength,
        DimensionalNumber $stability,
        ?string $damageTypeCode,
    ): int {
        $difference = $this->value($this->characteristic($attackerStrength))
            - $this->value($this->characteristic($stability));
        $unstable = max(0, $difference);
        $divisor = $damageTypeCode === null ? 1 : ($push->getDivisors()[$damageTypeCode] ?? 1);

        return $divisor <= 1 ? $unstable : intdiv($unstable, $divisor);
    }

    /**
     * Отброс: Неустойчивость, делённая на четыре. Меньше четырёх — нет отброса.
     *
     * @param int $unstable Неустойчивость.
     *
     * @return DimensionalNumber|null Отброс или null.
     */
    public function knockback(int $unstable): ?DimensionalNumber
    {
        if ($unstable < 4) {
            return null;
        }

        return new DimensionalNumber(intdiv($unstable, 4), 0);
    }

    /**
     * Приводит значение к шкале характеристик.
     *
     * @param DimensionalNumber $value Значение.
     *
     * @return CharacteristicNumber Число характеристик.
     */
    private function characteristic(DimensionalNumber $value): CharacteristicNumber
    {
        return $value instanceof CharacteristicNumber
            ? $value
            : new CharacteristicNumber($value->getBase(), $value->getSize());
    }

    /**
     * Значение характеристики: база плюс три на размер.
     *
     * @param CharacteristicNumber $value Число.
     *
     * @return int Пункты.
     */
    private function value(CharacteristicNumber $value): int
    {
        return $value->getBase() + CharacteristicNumber::BASE_STEP * $value->getSize();
    }
}
