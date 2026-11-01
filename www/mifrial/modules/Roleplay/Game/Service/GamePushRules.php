<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Dto\GameCheckThrowOverride;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\PushSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\StateSpec;
use Mifrial\Roleplay\Rule\Value\CharacteristicNumber;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Исполнение Толчка: пол, контест пула, урон и отброс.
 *
 * Пул — Сила или урон оружия. Толкающий удар отличается только пулом и базой
 * урона, поэтому считается здесь же.
 */
final class GamePushRules
{
    /**
     * Подпись проверки, когда пул — урон оружия.
     */
    private const WEAPON_POOL_LABEL = 'Урон (толчок)';

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
        $supported = match ($push->getPool()) {
            'strength' => $push->getDamage() === 'crush_from_strength',
            'weapon_damage' => $push->getDamage() === 'weapon_times_sr',
            default => false,
        };
        if (!$supported) {
            throw new GameInvalidException('Game push damage is not supported');
        }
    }

    /**
     * Пул толчка — урон оружия.
     *
     * @param PushSpec $push Толчок.
     *
     * @return bool true, если пул — урон оружия.
     */
    public function isWeaponDamagePool(PushSpec $push): bool
    {
        return $push->getPool() === 'weapon_damage';
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
     * Пол толчка: вес минус один размер.
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

        return $this->characteristic($weight)->modify(-3);
    }

    /**
     * Урон толчка: целое значение сырых успехов минус три.
     *
     * @param DimensionalNumber $successes Сырые успехи.
     *
     * @return DimensionalNumber Урон.
     */
    public function damage(DimensionalNumber $successes): DimensionalNumber
    {
        return new DimensionalNumber(max(0, $successes->toInteger() - 3), 0);
    }

    /**
     * Рейтинг позы: РУ, урезанный делителем типа урона.
     *
     * @param PushSpec $push Толчок.
     * @param string|null $damageTypeCode Тип урона профиля.
     * @param int $success РУ контеста.
     *
     * @return int Рейтинг позы.
     */
    public function getPostureRating(PushSpec $push, ?string $damageTypeCode, int $success): int
    {
        if ($success <= 0) {
            return 0;
        }

        $divisor = $damageTypeCode === null ? 1 : ($push->getDivisors()[$damageTypeCode] ?? 1);

        return $divisor <= 1 ? $success : intdiv($success, $divisor);
    }

    /**
     * Отброс: рейтинг позы минус шесть. Неположительный — нет отброса.
     *
     * @param int $posture Рейтинг позы.
     *
     * @return DimensionalNumber|null Отброс или null.
     */
    public function knockback(int $posture): ?DimensionalNumber
    {
        $value = $posture - 6;

        return $value > 0 ? new DimensionalNumber($value, 0) : null;
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
     * Контест толчка: соло против пола или совместная проверка с блоком.
     *
     * Успешный блок оставляет контакт: РУ 1, если у атакующего были успехи;
     * защита блока при этом входит в сопротивление.
     *
     * @param GameRecord $game Игра.
     * @param PushSpec $push Толчок.
     * @param array<string, mixed> $attackerSheet Лист толкающего.
     * @param array<string, mixed>|null $defenderSheet Лист цели при блоке.
     * @param string $reaction Реакция.
     * @param DimensionalNumber $floor Пол.
     * @param DimensionalNumber|null $weaponDamage Урон оружия для пула weapon_damage.
     * @param string|null $damageTypeCode Тип урона профиля.
     *
     * @return array{
     *     attacker: array<string, mixed>,
     *     defender: array<string, mixed>|null,
     *     success: int,
     *     posture: int,
     *     damage: DimensionalNumber,
     *     knockback: DimensionalNumber|null,
     *     raw: DimensionalNumber
     * } Итог.
     *
     * @throws GameInvalidException Если бросок или лист битые.
     */
    public function contest(
        GameRecord $game,
        PushSpec $push,
        array $attackerSheet,
        ?array $defenderSheet,
        string $reaction,
        DimensionalNumber $floor,
        ?DimensionalNumber $weaponDamage = null,
        ?string $damageTypeCode = null,
    ): array {
        $checkCode = $this->strengthCheck($game);
        $attackerPool = $this->isWeaponDamagePool($push) ? $weaponDamage : null;
        if ($this->isWeaponDamagePool($push) && $attackerPool === null) {
            throw new GameInvalidException('Game push weapon damage is invalid');
        }

        if ($reaction === 'block') {
            if ($defenderSheet === null) {
                throw new GameInvalidException('Game push defender sheet is invalid');
            }

            $attacker = $this->throwPool($game, $checkCode, $attackerSheet, new DimensionalNumber(0, 0), $attackerPool);
            $defender = $this->throwPool($game, $checkCode, $defenderSheet, new DimensionalNumber(0, 0));
            $attackerRaw = $this->pair($attacker['roll']);
            $defenderRaw = $this->pair($defender['roll']);
            $raised = $this->raise($defenderRaw, $floor);
            $defender = $this->checks->rerate($defender, $this->pairArray($attackerRaw));
            $attacker = $this->checks->rerate($attacker, $this->pairArray($raised));
            $rating = is_int($attacker['rating'] ?? null) ? $attacker['rating'] : 0;
            $success = $rating > 0 ? $rating : ($attackerRaw->getBase() > 0 ? 1 : 0);
            $posture = $this->getPostureRating($push, $damageTypeCode, $success);

            return [
                'attacker' => $attacker,
                'defender' => $defender,
                'success' => $success,
                'posture' => $posture,
                'damage' => $attackerPool ?? $this->damage($attackerRaw),
                'knockback' => $this->knockback($posture),
                'raw' => $attackerRaw,
            ];
        }

        $attacker = $this->throwPool($game, $checkCode, $attackerSheet, $floor, $attackerPool);
        $attackerRaw = $this->pair($attacker['roll']);
        $success = max(0, is_int($attacker['rating'] ?? null) ? $attacker['rating'] : 0);
        $posture = $this->getPostureRating($push, $damageTypeCode, $success);

        return [
            'attacker' => $attacker,
            'defender' => null,
            'success' => $success,
            'posture' => $posture,
            'damage' => $attackerPool ?? $this->damage($attackerRaw),
            'knockback' => $this->knockback($posture),
            'raw' => $attackerRaw,
        ];
    }

    /**
     * Бросок пула против заданной сложности.
     *
     * @param GameRecord $game Игра.
     * @param string $checkCode Проверка.
     * @param array<string, mixed> $sheet Лист.
     * @param DimensionalNumber $difficulty Сложность.
     * @param DimensionalNumber|null $pool Пул урона оружия или null.
     *
     * @return array<string, mixed> Бросок.
     */
    private function throwPool(
        GameRecord $game,
        string $checkCode,
        array $sheet,
        DimensionalNumber $difficulty,
        ?DimensionalNumber $pool = null,
    ): array {
        $override = $pool === null ? null : new GameCheckThrowOverride(
            diceCount: max(1, $pool->getBase()),
            size: $pool->getSize(),
        );
        $roll = $this->checks->throwWithDifficulty(
            $game->getSpaceId(),
            $game->getRulesRevision(),
            $checkCode,
            $sheet,
            $this->pairArray($difficulty),
            $override,
        );
        if ($pool !== null) {
            $roll['label'] = self::WEAPON_POOL_LABEL;
        }

        return $roll;
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
