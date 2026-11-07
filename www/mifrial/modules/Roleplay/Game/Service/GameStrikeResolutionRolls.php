<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Dto\GameStrikeBlockRequest;
use Mifrial\Roleplay\Game\Dto\GameStrikeDodgeRequest;
use Mifrial\Roleplay\Game\Dto\GameStrikeHitRequest;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Mechanic\Dto\RollAdvantage;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Броски попадания, уклонения и блока по строке открытого удара.
 */
final class GameStrikeResolutionRolls
{
    /**
     * Создаёт расчёт бросков.
     *
     * @param GameStrikeRules $rules Срез удара.
     * @param GameStrikeSheets $sheets Листы удара.
     * @param GameStrikeResolutionContext $context Строка и участники.
     * @param GameStrikeResolutionActors $actors Снимки участников.
     *
     * @return void
     */
    public function __construct(
        private readonly GameStrikeRules $rules,
        private readonly GameStrikeSheets $sheets,
        private readonly GameStrikeResolutionContext $context,
        private readonly GameStrikeResolutionActors $actors,
    ) {
    }

    /**
     * Бросок атакующего по строке открытого удара.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $open Открытый удар.
     * @param string $hitCode Код hit-check.
     * @param array<string, mixed> $pending Карта ожидания.
     * @param CharacterRuleSlice $slice Срез.
     * @param array<int, RollAdvantage> $extraAdvantages Дополнительные преимущества (широкий удар).
     * @param array<int, string> $targetKeys Цели атаки для хвостов прошлого действия.
     * @param int $extraAccuracy Дополнительная Точность.
     *
     * @return array<string, mixed> Roll.
     *
     * @throws GameInvalidException Если строка битая.
     * @throws \Mifrial\Roleplay\Game\Exception\GameNotFoundException Если листа нет.
     */
    public function rollOf(
        GameRecord $game,
        array $open,
        string $hitCode,
        array $pending,
        CharacterRuleSlice $slice,
        array $extraAdvantages = [],
        array $targetKeys = [],
        int $extraAccuracy = 0,
    ): array {
        $row = $this->context->attackerOf($open);
        $advantages = $this->hitAdvantages($game, $row, $hitCode, $pending, $slice, $extraAdvantages);
        $tail = (new GamePendingActionCosts())->attackAdjustment(
            $pending,
            $row['kind'],
            $row['attackerId'],
            $row['profileType'],
            $targetKeys,
        );
        $roll = $this->rules->rolls->hit(new GameStrikeHitRequest(
            $this->actors->target($game, $row),
            $hitCode,
            $row['actionRuleCode'],
            $advantages,
            $tail['accuracy'] + $extraAccuracy,
            $tail['adjustment'],
        ));
        $roll['actorKey'] = $row['kind'] . ':' . $row['attackerId'];
        return $roll;
    }

    /**
     * Бросок уклонения защитника.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $open Открытый удар.
     * @param string $hitCode Код hit-check.
     * @param array<string, mixed> $attackerRoll Roll атаки.
     * @param array<string, mixed> $pending Карта ожидания.
     * @param string|null $reactionRuleCode Карточка реакции.
     * @param CharacterRuleSlice $slice Срез.
     * @param array<int, RollAdvantage> $extraAdvantages Дополнительные помехи защиты.
     * @param DimensionalNumber|null $shotDistance Дистанция выстрела для штрафа эффективности.
     *
     * @return array<string, mixed> Roll.
     *
     * @throws GameInvalidException Если строка или выбор битые.
     * @throws \Mifrial\Roleplay\Game\Exception\GameNotFoundException Если лист отсутствует.
     */
    public function dodgeRollOf(
        GameRecord $game,
        array $open,
        string $hitCode,
        array $attackerRoll,
        array $pending,
        ?string $reactionRuleCode,
        CharacterRuleSlice $slice,
        array $extraAdvantages = [],
        ?DimensionalNumber $shotDistance = null,
    ): array {
        $defender = $this->context->defenderOf($open);
        $advantages = $this->defenseAdvantages($pending, $defender, $hitCode, $reactionRuleCode, $slice, $extraAdvantages);
        $roll = $this->rules->rolls->dodge($this->dodgeRequest(
            $game,
            $open,
            $hitCode,
            $attackerRoll,
            $defender,
            $advantages,
            $shotDistance,
        ));
        $roll['actorKey'] = $defender['kind'] . ':' . $defender['id'];
        $roll['label'] = 'Уклонение';

        return $roll;
    }

    /**
     * Бросок блока защитника.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $open Открытый удар.
     * @param array<string, mixed> $choice Защита.
     * @param string $hitCode Код hit-check.
     * @param array<string, mixed> $attackerRoll Roll атаки.
     * @param array<string, mixed> $pending Карта ожидания.
     * @param string|null $reactionRuleCode Карточка реакции.
     * @param CharacterRuleSlice $slice Срез.
     * @param array<int, RollAdvantage> $extraAdvantages Дополнительные помехи защиты.
     *
     * @return array<string, mixed> Roll.
     *
     * @throws GameInvalidException Если строка или выбор битые.
     * @throws \Mifrial\Roleplay\Game\Exception\GameNotFoundException Если лист отсутствует.
     */
    public function blockRollOf(
        GameRecord $game,
        array $open,
        array $choice,
        string $hitCode,
        array $attackerRoll,
        array $pending,
        ?string $reactionRuleCode,
        CharacterRuleSlice $slice,
        array $extraAdvantages = [],
    ): array {
        $defender = $this->context->defenderOf($open);
        $inventoryId = $choice['blockItemInventoryId'] ?? null;
        $profileIndex = $choice['blockItemProfileIndex'] ?? null;
        if (!is_int($inventoryId)) {
            throw new GameInvalidException('Game strike block item is invalid');
        }

        if (!is_int($profileIndex)) {
            throw new GameInvalidException('Game strike block item is invalid');
        }

        $advantages = $this->defenseAdvantages($pending, $defender, $hitCode, $reactionRuleCode, $slice, $extraAdvantages);
        $roll = $this->rules->rolls->block($this->blockRequest(
            $game, $open, $hitCode, $attackerRoll, $defender, $inventoryId, $profileIndex, $advantages,
        ));
        $roll['actorKey'] = $defender['kind'] . ':' . $defender['id'];
        $roll['label'] = 'Блок';
        return $roll;
    }

    /**
     * Запрос броска уклонения.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $open Открытый удар.
     * @param string $hitCode Код hit-check.
     * @param array<string, mixed> $attackerRoll Roll атаки.
     * @param array{kind: string, id: int, profileType: string} $defender Защитник.
     * @param array<int, RollAdvantage> $advantages Помехи.
     * @param DimensionalNumber|null $shotDistance Дистанция выстрела.
     *
     * @return GameStrikeDodgeRequest Запрос.
     *
     * @throws GameInvalidException Если лист битый.
     * @throws \Mifrial\Roleplay\Game\Exception\GameNotFoundException Если листа нет.
     */
    private function dodgeRequest(
        GameRecord $game,
        array $open,
        string $hitCode,
        array $attackerRoll,
        array $defender,
        array $advantages,
        ?DimensionalNumber $shotDistance,
    ): GameStrikeDodgeRequest {
        return new GameStrikeDodgeRequest(
            $game->getSpaceId(),
            $game->getRulesRevision(),
            $hitCode,
            $this->sheets->getDefenderSheet($game, $open),
            $this->context->participantChoices($game, $defender['kind'], $defender['id']),
            $attackerRoll,
            $this->context->participantKey($game, $defender['kind'], $defender['id']),
            $advantages,
            $shotDistance,
            $defender['profileType'],
        );
    }

    /**
     * Запрос броска блока.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $open Открытый удар.
     * @param string $hitCode Код hit-check.
     * @param array<string, mixed> $attackerRoll Roll атаки.
     * @param array{kind: string, id: int, profileType: string} $defender Защитник.
     * @param int $inventoryId Строка блока.
     * @param int $profileIndex Индекс профиля блока.
     * @param array<int, RollAdvantage> $advantages Помехи.
     *
     * @return GameStrikeBlockRequest Запрос.
     *
     * @throws GameInvalidException Если лист битый.
     * @throws \Mifrial\Roleplay\Game\Exception\GameNotFoundException Если листа нет.
     */
    private function blockRequest(
        GameRecord $game,
        array $open,
        string $hitCode,
        array $attackerRoll,
        array $defender,
        int $inventoryId,
        int $profileIndex,
        array $advantages,
    ): GameStrikeBlockRequest {
        return new GameStrikeBlockRequest(
            $game->getSpaceId(),
            $game->getRulesRevision(),
            $hitCode,
            $this->sheets->getDefenderSheet($game, $open),
            $this->context->participantChoices($game, $defender['kind'], $defender['id']),
            $inventoryId,
            $profileIndex,
            $attackerRoll,
            $this->context->participantKey($game, $defender['kind'], $defender['id']),
            $advantages,
        );
    }

    /**
     * Преимущества броска атакующего.
     *
     * @param GameRecord $game Игра.
     * @param array{kind: string, attackerId: int, itemRuleCode: string, profileType: string, profileIndex: int, inventoryId: int, actionRuleCode: string} $row Поля атакующего.
     * @param string $hitCode Код hit-check.
     * @param array<string, mixed> $pending Карта ожидания.
     * @param CharacterRuleSlice $slice Срез.
     * @param array<int, RollAdvantage> $extraAdvantages Дополнительные преимущества.
     *
     * @return array<int, RollAdvantage> Преимущества.
     *
     * @throws GameInvalidException Если лист битый.
     * @throws \Mifrial\Roleplay\Game\Exception\GameNotFoundException Если листа нет.
     */
    private function hitAdvantages(
        GameRecord $game,
        array $row,
        string $hitCode,
        array $pending,
        CharacterRuleSlice $slice,
        array $extraAdvantages,
    ): array {
        $advantages = $this->rules->checks->actionCheckAdvantages($slice, $row['actionRuleCode'], $hitCode);
        array_push($advantages, ...$this->rules->modifiers->passiveCheckAdvantages(
            $slice,
            $this->context->abilityLevels($game, $row['kind'], $row['attackerId']),
            $hitCode,
        ));
        array_push($advantages, ...(new GamePendingActionCosts())->checkAdvantages(
            $pending,
            $row['kind'],
            $row['attackerId'],
            $hitCode,
            'attacker',
        ));
        array_push($advantages, ...$extraAdvantages);

        return $advantages;
    }

    /**
     * Помехи броска защиты.
     *
     * @param array<string, mixed> $pending Карта ожидания.
     * @param array{kind: string, id: int, profileType: string} $defender Защитник.
     * @param string $hitCode Код hit-check.
     * @param string|null $reactionRuleCode Карточка реакции.
     * @param CharacterRuleSlice $slice Срез.
     * @param array<int, RollAdvantage> $extraAdvantages Дополнительные помехи.
     *
     * @return array<int, RollAdvantage> Помехи.
     *
     * @throws GameInvalidException Если карточка реакции битая.
     */
    private function defenseAdvantages(
        array $pending,
        array $defender,
        string $hitCode,
        ?string $reactionRuleCode,
        CharacterRuleSlice $slice,
        array $extraAdvantages,
    ): array {
        $advantages = (new GamePendingActionCosts())->checkAdvantages(
            $pending,
            $defender['kind'],
            $defender['id'],
            $hitCode,
            'defender',
        );
        if ($reactionRuleCode !== null) {
            array_push($advantages, ...$this->rules->checks->actionCheckAdvantages($slice, $reactionRuleCode, $hitCode));
        }

        array_push($advantages, ...$extraAdvantages);

        return $advantages;
    }
}
