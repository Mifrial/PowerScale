<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Защита, дальний пересчёт и проникновение удара.
 */
final class GameStrikeResolutionDefense
{
    /**
     * Создаёт расчёт защиты.
     *
     * @param GameStrikeRules $rules Срез удара.
     * @param GameStrikeResolutionContext $context Строка и участники.
     * @param GameStrikeResolutionActors $actors Снимки участников.
     *
     * @return void
     */
    public function __construct(
        private readonly GameStrikeRules $rules,
        private readonly GameStrikeResolutionContext $context,
        private readonly GameStrikeResolutionActors $actors,
    ) {
    }

    /**
     * Пересчитывает атаку дальнего боя против успехов защиты, укрытия и полосы.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $open Открытый удар.
     * @param array<string, mixed> $conditions Условия цели.
     * @param array<string, mixed> $attackerRoll Бросок атаки.
     * @param array<string, mixed>|null $defenderRoll Бросок защиты.
     * @param string $reaction Реакция.
     *
     * @return array<string, mixed> Пересчитанный бросок.
     *
     * @throws GameInvalidException Если строка или профиль битые.
     */
    public function rerateRangedHit(
        GameRecord $game,
        array $open,
        array $conditions,
        array $attackerRoll,
        ?array $defenderRoll,
        string $reaction,
    ): array {
        $row = $this->context->attackerOf($open);
        $slice = $this->rules->data->ruleSlice($game->getSpaceId(), $game->getRulesRevision());
        $target = $this->actors->target(
            $game,
            $row,
            $this->context->actionCharacteristicModifier($game, $row, $slice),
        );
        $metrics = $this->rules->distance->metrics(
            $this->rules->damage->attack($target),
            $conditions,
            $row['actionRuleCode'],
        );
        $frame = $this->rangedFrame($defenderRoll, $conditions, $metrics);
        $attackerRoll['ranged_hit'] = $this->rangedPayload($frame, $metrics, $reaction);

        return $this->rules->hitCheck->rerate($attackerRoll, $this->pairOf($frame['difficulty']));
    }

    /**
     * Убирает block layers после проигранного defender roll.
     *
     * @param array<string, mixed> $choice Реакция.
     * @param array<string, mixed>|null $defenderRoll Roll защитника.
     *
     * @return array<string, mixed> Выбор для projection.
     */
    public function resistanceChoice(array $choice, ?array $defenderRoll): array
    {
        if (
            $choice['reaction'] === 'block'
            && ($defenderRoll === null || ($defenderRoll['success'] ?? false) !== true)
        ) {
            $choice['blockItemInventoryId'] = null;
        }

        return $choice;
    }

    /**
     * Проникновение принятого удара по документам атакующего.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $open Открытый удар.
     * @param int|null $success Рейтинг успеха или null, если бонус от РУ не применяется.
     * @param int $hitNumber Номер попадания с единицы.
     * @param int $strengthDrop Штраф силы броска в размерах.
     *
     * @return DimensionalNumber Проникновение.
     *
     * @throws GameInvalidException Если строка или формула битые.
     * @throws \Mifrial\Roleplay\Game\Exception\GameNotFoundException Если атакующий отсутствует.
     */
    public function penetrationOf(
        GameRecord $game,
        array $open,
        ?int $success = null,
        int $hitNumber = 1,
        int $strengthDrop = 0,
    ): DimensionalNumber {
        $row = $this->context->attackerOf($open);
        $slice = $this->rules->data->ruleSlice($game->getSpaceId(), $game->getRulesRevision());
        $attackContext = $this->context->attackFilterContext($game, $row);
        $characteristicModifier = $this->context->attackCharacteristicModifier(
            $game,
            $row,
            $slice,
            $hitNumber,
            $success,
        );

        return $this->rules->damage->evaluatePenetration($this->actors->target(
            $game,
            $row,
            $characteristicModifier,
            $strengthDrop,
            $this->rules->equipment->gripBonus($attackContext['occupyHands']),
        ));
    }

    /**
     * Сопротивление брони защитника по профилю удара.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $open Строка удара.
     * @param array{reaction: string, blockItemInventoryId: int|null, blockItemRuleCode: string|null} $choice Защита.
     * @param int $rating Рейтинг.
     * @param DimensionalNumber $penetration Проникновение.
     * @param int $durabilityShave Срез надёжности с единиц удара.
     *
     * @return DimensionalNumber Сумма слоёв или ноль.
     *
     * @throws GameInvalidException Если строка, профиль или лист битые.
     * @throws \Mifrial\Roleplay\Game\Exception\GameNotFoundException Если ревизии или листа нет.
     */
    public function resistanceOf(
        GameRecord $game,
        array $open,
        array $choice,
        int $rating,
        DimensionalNumber $penetration,
        int $durabilityShave = 0,
    ): DimensionalNumber {
        $defender = $this->context->defenderOf($open);
        $itemRuleCode = $open['item_rule_code'] ?? null;
        $profileIndex = $open['profile_index'] ?? null;
        if (!is_string($itemRuleCode)) {
            throw new GameInvalidException('Game strike row is invalid');
        }

        if (!is_int($profileIndex)) {
            throw new GameInvalidException('Game strike row is invalid');
        }

        return $this->rules->defense->resistance($this->actors->defenseTarget(
            $game, $defender['kind'], $defender['id'], $itemRuleCode, $defender['profileType'], $profileIndex,
            $choice['reaction'], $choice['blockItemInventoryId'], $rating, $penetration, $durabilityShave,
        ));
    }

    /**
     * Защита, укрытие и полоса дальнего броска.
     *
     * @param array<string, mixed>|null $defenderRoll Бросок защиты.
     * @param array<string, mixed> $conditions Условия цели.
     * @param array{distance: DimensionalNumber, min: DimensionalNumber, max: DimensionalNumber|null, falloff: DimensionalNumber|null, rangeSize: int, drop: int} $metrics Метрики дистанции.
     *
     * @return array{
     *     difficulty: DimensionalNumber,
     *     defense: DimensionalNumber,
     *     minimum: DimensionalNumber,
     *     cover: DimensionalNumber,
     *     base: DimensionalNumber
     * }
     */
    private function rangedFrame(?array $defenderRoll, array $conditions, array $metrics): array
    {
        $defense = $defenderRoll !== null
            ? new DimensionalNumber(
                (int) ($defenderRoll['roll']['base'] ?? 0),
                (int) ($defenderRoll['roll']['size'] ?? 0),
            )
            : new DimensionalNumber(0, 0);
        $defense = $this->raiseToMinSize($defense, -1);

        $coverValue = $conditions['cover'] ?? ['base' => 0, 'size' => 0];
        $cover = new DimensionalNumber((int) $coverValue['base'], (int) $coverValue['size']);
        $minimum = new DimensionalNumber(1, -1);
        $base = $defense->add($minimum)->add($cover);
        $difficulty = $base->withSize($base->getSize() + $metrics['rangeSize'] - 1);

        return [
            'difficulty' => $difficulty,
            'defense' => $defense,
            'minimum' => $minimum,
            'cover' => $cover,
            'base' => $base,
        ];
    }

    /**
     * Payload дальнего броска для карточки.
     *
     * @param array{difficulty: DimensionalNumber, defense: DimensionalNumber, minimum: DimensionalNumber, cover: DimensionalNumber, base: DimensionalNumber} $frame Рамка броска.
     * @param array{distance: DimensionalNumber, min: DimensionalNumber, max: DimensionalNumber|null, falloff: DimensionalNumber|null, rangeSize: int, drop: int} $metrics Метрики дистанции.
     * @param string $reaction Реакция.
     *
     * @return array<string, mixed> Payload.
     */
    private function rangedPayload(array $frame, array $metrics, string $reaction): array
    {
        return [
            'reaction' => $reaction,
            'defense_value' => $this->pairOf($frame['defense']),
            'defense_result' => $this->exactOf($frame['defense']),
            'minimum_value' => $this->pairOf($frame['minimum']),
            'cover' => $this->exactOf($frame['cover']),
            'cover_value' => $this->pairOf($frame['cover']),
            'base_value' => $this->pairOf($frame['base']),
            'range_size' => $metrics['rangeSize'],
            'falloff_value' => $metrics['falloff'] instanceof DimensionalNumber
                ? $this->pairOf($metrics['falloff'])
                : null,
            'distance_ipari' => $this->exactOf($metrics['distance']),
            'distance_value' => $this->pairOf($metrics['distance']),
            'difficulty' => $this->pairOf($frame['difficulty']),
        ];
    }

    /**
     * Пара base/size для payload броска.
     *
     * @param DimensionalNumber $value Значение.
     *
     * @return array{base: int, size: int} Пара.
     */
    private function pairOf(DimensionalNumber $value): array
    {
        return ['base' => $value->getBase(), 'size' => $value->getSize()];
    }

    /**
     * Точное значение размерного числа.
     *
     * @param DimensionalNumber $value Значение.
     *
     * @return float Число.
     */
    private function exactOf(DimensionalNumber $value): float
    {
        return (float) ($value->getBase() * (2 ** $value->getSize()));
    }

    /**
     * Поднимает размер к нижней границе проверки, сохраняя значение.
     *
     * @param DimensionalNumber $value Значение.
     * @param int $minSize Нижняя граница размера.
     *
     * @return DimensionalNumber Значение в размере не мельче границы.
     */
    private function raiseToMinSize(DimensionalNumber $value, int $minSize): DimensionalNumber
    {
        $base = $value->getBase();
        $size = $value->getSize();
        while ($size < $minSize) {
            $base = intdiv($base, 2);
            ++$size;
        }

        return new DimensionalNumber($base, $size);
    }
}
