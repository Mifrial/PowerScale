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
 * Исполнение Толчка: пол, контест Силы, урон и отброс.
 *
 * Толкающий удар сюда не входит: его пул — урон оружия, а не Сила.
 */
final class GamePushRules
{
    /**
     * Создаёт правила толчка.
     *
     * @param GameStrikeRules $rules Срез правил.
     * @param GameCheckRoll $checks Бросок.
     *
     * @return void
     */
    public function __construct(
        private readonly GameStrikeRules $rules,
        private readonly GameCheckRoll $checks,
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
     * Проверяет, что этот срез умеет исполнять толчок.
     *
     * @param PushSpec $push Толчок.
     *
     * @return void
     *
     * @throws GameInvalidException Если вид толчка ещё не поддержан.
     */
    public function assertSupported(PushSpec $push): void
    {
        if ($push->getPool() !== 'strength' || $push->getDamage() !== 'crush_from_strength') {
            throw new GameInvalidException('Game push damage is not supported');
        }
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
     * Пол толчка: вес минус два размера.
     *
     * @param array<string, mixed> $sheet Лист цели.
     *
     * @return DimensionalNumber Пол.
     *
     * @throws GameInvalidException Если веса нет.
     */
    public function floor(array $sheet): DimensionalNumber
    {
        $weight = $this->rules->characteristicOf($sheet, 'weight');

        return $this->characteristic($weight)->modify(-6);
    }

    /**
     * Урон толчка: сырые успехи Силы минус три.
     *
     * @param DimensionalNumber $successes Сырые успехи.
     *
     * @return DimensionalNumber Урон.
     */
    public function damage(DimensionalNumber $successes): DimensionalNumber
    {
        $value = $this->characteristic($successes)->modify(-3);

        return $value->toInteger() < 0 ? new DimensionalNumber(0, 0) : $value;
    }

    /**
     * Отброс: РУ минус шесть.
     *
     * @param int $success РУ.
     *
     * @return DimensionalNumber Отброс.
     */
    public function knockback(int $success): DimensionalNumber
    {
        return (new CharacteristicNumber(max(0, $success), 0))->modify(-6);
    }

    /**
     * Поднимает успехи защиты до пола.
     *
     * @param DimensionalNumber $rolled Успехи защиты.
     * @param DimensionalNumber $floor Пол.
     *
     * @return DimensionalNumber Не ниже пола.
     */
    public function raise(DimensionalNumber $rolled, DimensionalNumber $floor): DimensionalNumber
    {
        $value = new DimensionalNumber($rolled->getBase(), $rolled->getSize());

        return $value->compare($floor) < 0 ? $floor : $rolled;
    }

    /**
     * Контест Силы: соло против пола или совместная с блоком.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $attackerSheet Лист толкающего.
     * @param array<string, mixed>|null $defenderSheet Лист цели при блоке.
     * @param string $reaction Реакция.
     * @param DimensionalNumber $floor Пол.
     *
     * @return array{
     *     attacker: array<string, mixed>,
     *     defender: array<string, mixed>|null,
     *     success: int,
     *     damage: DimensionalNumber,
     *     knockback: DimensionalNumber|null,
     *     raw: DimensionalNumber
     * } Итог.
     *
     * @throws GameInvalidException Если бросок или лист битые.
     */
    public function contest(
        GameRecord $game,
        array $attackerSheet,
        ?array $defenderSheet,
        string $reaction,
        DimensionalNumber $floor,
    ): array {
        $checkCode = $this->strengthCheck($game);
        if ($reaction === 'block') {
            if ($defenderSheet === null) {
                throw new GameInvalidException('Game push defender sheet is invalid');
            }

            $attacker = $this->throwStrength($game, $checkCode, $attackerSheet, new DimensionalNumber(0, 0));
            $defender = $this->throwStrength($game, $checkCode, $defenderSheet, new DimensionalNumber(0, 0));
            $attackerRaw = $this->pair($attacker['roll']);
            $defenderRaw = $this->pair($defender['roll']);
            $raised = $this->raise($defenderRaw, $floor);
            $defender = $this->checks->rerate($defender, $this->pairArray($attackerRaw));
            $attacker = $this->checks->rerate($attacker, $this->pairArray($raised));
            $success = max(0, is_int($attacker['rating'] ?? null) ? $attacker['rating'] : 0);

            return [
                'attacker' => $attacker,
                'defender' => $defender,
                'success' => $success,
                'damage' => $this->damage($attackerRaw),
                'knockback' => $success > 0 ? $this->knockback($success) : null,
                'raw' => $attackerRaw,
            ];
        }

        $attacker = $this->throwStrength($game, $checkCode, $attackerSheet, $floor);
        $attackerRaw = $this->pair($attacker['roll']);
        $success = max(0, is_int($attacker['rating'] ?? null) ? $attacker['rating'] : 0);

        return [
            'attacker' => $attacker,
            'defender' => null,
            'success' => $success,
            'damage' => $this->damage($attackerRaw),
            'knockback' => $success > 0 ? $this->knockback($success) : null,
            'raw' => $attackerRaw,
        ];
    }

    /**
     * Бросок Силы против заданной сложности.
     *
     * @param GameRecord $game Игра.
     * @param string $checkCode Проверка.
     * @param array<string, mixed> $sheet Лист.
     * @param DimensionalNumber $difficulty Сложность.
     *
     * @return array<string, mixed> Бросок.
     */
    private function throwStrength(
        GameRecord $game,
        string $checkCode,
        array $sheet,
        DimensionalNumber $difficulty,
    ): array {
        return $this->checks->throwWithDifficulty(
            $game->getSpaceId(),
            $game->getRulesRevision(),
            $checkCode,
            $sheet,
            $this->pairArray($difficulty),
        );
    }

    /**
     * Код проверки Силы.
     *
     * @param GameRecord $game Игра.
     *
     * @return string Код.
     */
    private function strengthCheck(GameRecord $game): string
    {
        return $this->rules->checkByCharacteristic(
            $game->getSpaceId(),
            $game->getRulesRevision(),
            'strength',
        );
    }

    /**
     * Размерное число по базе и размеру.
     *
     * @param array<string, mixed> $pair Пара.
     *
     * @return DimensionalNumber Число.
     *
     * @throws GameInvalidException Если пара битая.
     */
    private function pair(array $pair): DimensionalNumber
    {
        if (!is_int($pair['base'] ?? null) || !is_int($pair['size'] ?? null)) {
            throw new GameInvalidException('Game push roll is invalid');
        }

        return new DimensionalNumber($pair['base'], $pair['size']);
    }

    /**
     * Пара base/size.
     *
     * @param DimensionalNumber $value Число.
     *
     * @return array{base: int, size: int} Пара.
     */
    private function pairArray(DimensionalNumber $value): array
    {
        return ['base' => $value->getBase(), 'size' => $value->getSize()];
    }

    /**
     * Приводит значение к шкале характеристик.
     *
     * @param DimensionalNumber $value Число.
     *
     * @return CharacteristicNumber Число характеристик.
     */
    private function characteristic(DimensionalNumber $value): CharacteristicNumber
    {
        return $value instanceof CharacteristicNumber
            ? $value
            : new CharacteristicNumber($value->getBase(), $value->getSize());
    }
}
