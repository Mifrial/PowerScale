<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Repository\GameNpcRepository;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Расчёт бросков, урона и последствий удара. Без записи.
 */
final class GameStrikeResolution
{
    /**
     * Создаёт расчёт.
     *
     * @param GameStrikeRules $rules Срез и версия персонажа.
     * @param GameStrikeSheets $sheets Листы и конфликты.
     * @param GameNpcRepository $npcs NPC.
     * @param GameStrikeConsequences $consequences Последствия попадания.
     * @param GameStrikeAmounts $amounts Пары и нули.
     *
     * @return void
     */
    public function __construct(
        private readonly GameStrikeRules $rules,
        private readonly GameStrikeSheets $sheets,
        private readonly GameNpcRepository $npcs,
        private readonly GameStrikeConsequences $consequences,
        private readonly GameStrikeAmounts $amounts,
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
     * @param list<array<string, mixed>> $extraAdvantages Дополнительные преимущества (широкий удар).
     * @param array<int, string> $targetKeys Цели атаки для хвостов прошлого действия.
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
    ): array {
        $kind = $open['attacker_kind'] ?? null;
        $attackerId = $open['attacker_id'] ?? null;
        $itemRuleCode = $open['item_rule_code'] ?? null;
        $profileType = $open['profile_type'] ?? null;
        $profileIndex = $open['profile_index'] ?? null;
        $itemInventoryId = $open['item_inventory_id'] ?? null;
        if (
            !is_string($kind)
            || !is_int($attackerId)
            || !is_string($itemRuleCode)
            || !is_string($profileType)
            || !is_int($profileIndex)
            || !is_int($itemInventoryId)
        ) {
            throw new GameInvalidException('Game strike row is invalid');
        }

        $actionRuleCode = is_string($open['action_rule_code'] ?? null) ? $open['action_rule_code'] : '';
        $advantages = $this->rules->actionCheckAdvantages($slice, $actionRuleCode, $hitCode);
        array_push($advantages, ...(new GamePendingActionCosts())->checkAdvantages(
            $pending,
            $kind,
            $attackerId,
            $hitCode,
            'attacker',
        ));
        array_push($advantages, ...$extraAdvantages);
        $tail = (new GamePendingActionCosts())->attackAdjustment(
            $pending,
            $kind,
            $attackerId,
            $profileType,
            $targetKeys,
        );

        return $this->rules->rollHit(
            $game->getSpaceId(),
            $game->getRulesRevision(),
            $hitCode,
            $itemRuleCode,
            $profileType,
            $profileIndex,
            $itemInventoryId,
            $kind,
            $attackerId,
            $kind === 'npc' ? $this->sheets->getNpcSheet($game->getId(), $attackerId) : null,
            $kind === 'npc' ? $this->npcChoices($game->getId(), $attackerId) : null,
            $actionRuleCode,
            $kind === 'npc' ? $this->sheets->getNpcVersion($game->getId(), $attackerId) : null,
            $advantages,
            $tail['accuracy'],
            $tail['adjustment'],
        );
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
    ): array {
        $kind = $open['defender_kind'] ?? null;
        $id = $open['defender_id'] ?? null;
        $profileType = $open['profile_type'] ?? null;
        if (!is_string($kind) || !is_int($id) || !is_string($profileType)) {
            throw new GameInvalidException('Game strike defender is invalid');
        }

        $advantages = (new GamePendingActionCosts())->checkAdvantages(
            $pending,
            $kind,
            $id,
            $hitCode,
            'defender',
        );
        if ($reactionRuleCode !== null) {
            array_push($advantages, ...$this->rules->actionCheckAdvantages($slice, $reactionRuleCode, $hitCode));
        }

        return $this->rules->rollDodge(
            $game->getSpaceId(),
            $game->getRulesRevision(),
            $hitCode,
            $this->sheets->getDefenderSheet($game, $open),
            $this->defenderChoices($game, $kind, $id),
            $profileType,
            $attackerRoll,
            $this->participantKey($game, $kind, $id),
            $advantages,
        );
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
    ): array {
        $kind = $open['defender_kind'] ?? null;
        $id = $open['defender_id'] ?? null;
        $inventoryId = $choice['blockItemInventoryId'] ?? null;
        $profileIndex = $choice['blockItemProfileIndex'] ?? null;
        if (!is_string($kind) || !is_int($id) || !is_int($inventoryId) || !is_int($profileIndex)) {
            throw new GameInvalidException('Game strike block item is invalid');
        }

        $advantages = (new GamePendingActionCosts())->checkAdvantages(
            $pending,
            $kind,
            $id,
            $hitCode,
            'defender',
        );
        if ($reactionRuleCode !== null) {
            array_push($advantages, ...$this->rules->actionCheckAdvantages($slice, $reactionRuleCode, $hitCode));
        }

        return $this->rules->rollBlock(
            $game->getSpaceId(),
            $game->getRulesRevision(),
            $hitCode,
            $this->sheets->getDefenderSheet($game, $open),
            $this->defenderChoices($game, $kind, $id),
            $inventoryId,
            $profileIndex,
            $attackerRoll,
            $this->participantKey($game, $kind, $id),
            $advantages,
        );
    }

    /**
     * Число успеха по броску атакующего и ответу защитника.
     *
     * @param array<string, mixed> $attackerRoll Roll атаки.
     * @param array<string, mixed>|null $defenderRoll Roll защиты.
     * @param string $reaction Реакция.
     *
     * @return int Успех.
     *
     * @throws GameInvalidException Если рейтинг атаки невалиден.
     */
    public function successOf(array $attackerRoll, ?array $defenderRoll, string $reaction): int
    {
        $rating = $attackerRoll['rating'] ?? null;
        if (!is_int($rating)) {
            throw new GameInvalidException('Game strike attacker roll is invalid');
        }

        $autoFail = $this->rules->isHitAutoFail($attackerRoll['roll']);
        $defenderWon = $defenderRoll !== null && ($defenderRoll['success'] ?? false) === true;

        return $reaction === 'dodge'
            ? (($autoFail || $defenderWon) ? 0 : max(0, $rating))
            : ($autoFail || $rating <= 0 ? 0 : ($defenderWon ? 1 : $rating));
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
     *
     * @return DimensionalNumber Проникновение.
     *
     * @throws GameInvalidException Если строка или формула битые.
     * @throws \Mifrial\Roleplay\Game\Exception\GameNotFoundException Если атакующий отсутствует.
     */
    public function penetrationOf(GameRecord $game, array $open): DimensionalNumber
    {
        $kind = $open['attacker_kind'] ?? null;
        $attackerId = $open['attacker_id'] ?? null;
        $itemRuleCode = $open['item_rule_code'] ?? null;
        $profileType = $open['profile_type'] ?? null;
        $profileIndex = $open['profile_index'] ?? null;
        $inventoryId = $open['item_inventory_id'] ?? null;
        if (
            !is_string($kind)
            || !is_int($attackerId)
            || !is_string($itemRuleCode)
            || !is_string($profileType)
            || !is_int($profileIndex)
            || !is_int($inventoryId)
        ) {
            throw new GameInvalidException('Game strike row is invalid');
        }

        return $this->rules->evaluatePenetration(
            $game->getSpaceId(),
            $game->getRulesRevision(),
            $itemRuleCode,
            $profileType,
            $profileIndex,
            $inventoryId,
            $kind,
            $attackerId,
            $kind === 'npc' ? $this->sheets->getNpcSheet($game->getId(), $attackerId) : null,
            $kind === 'npc' ? $this->npcChoices($game->getId(), $attackerId) : null,
            $kind === 'npc' ? $this->sheets->getNpcVersion($game->getId(), $attackerId) : null,
        );
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
        $kind = $open['defender_kind'] ?? null;
        $defenderId = $open['defender_id'] ?? null;
        $itemRuleCode = $open['item_rule_code'] ?? null;
        $profileType = $open['profile_type'] ?? null;
        $profileIndex = $open['profile_index'] ?? null;
        if (!$this->isResistanceRow($kind, $defenderId, $itemRuleCode, $profileType, $profileIndex)) {
            throw new GameInvalidException('Game strike row is invalid');
        }

        return $this->rules->evaluateResistance(
            $game->getSpaceId(),
            $game->getRulesRevision(),
            $itemRuleCode,
            $profileType,
            $profileIndex,
            $kind,
            $defenderId,
            $kind === 'npc' ? $this->sheets->getNpcSheet($game->getId(), $defenderId) : null,
            $kind === 'npc' ? $this->npcChoices($game->getId(), $defenderId) : null,
            $choice['reaction'],
            $choice['blockItemInventoryId'],
            $rating,
            $penetration,
            $durabilityShave,
        );
    }

    /**
     * Смягчение выбранной реакции защитника по профилю удара.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $open Строка удара.
     * @param string $reaction Выбранная реакция защиты.
     * @param int $success Успех попадания.
     * @param array<string, mixed> $pending Карта ожидания атакующего.
     *
     * @return DimensionalNumber|null Пара или null, если у реакции нет dodge_soak.
     *
     * @throws GameInvalidException Если строка, карточка или закупка битые.
     * @throws \Mifrial\Roleplay\Game\Exception\GameNotFoundException Если ревизии или листа нет.
     */
    public function soakOf(
        GameRecord $game,
        array $open,
        string $reaction,
        int $success,
        array $pending = [],
    ): ?DimensionalNumber {
        $kind = $open['defender_kind'] ?? null;
        $defenderId = $open['defender_id'] ?? null;
        $itemRuleCode = $open['item_rule_code'] ?? null;
        $profileType = $open['profile_type'] ?? null;
        $profileIndex = $open['profile_index'] ?? null;
        $attackerKind = $open['attacker_kind'] ?? null;
        $attackerId = $open['attacker_id'] ?? null;
        if (!is_string($kind) || !is_int($defenderId) || !is_string($itemRuleCode)) {
            throw new GameInvalidException('Game strike row is invalid');
        }

        if (!is_string($profileType) || !is_int($profileIndex)) {
            throw new GameInvalidException('Game strike row is invalid');
        }

        if (!is_string($attackerKind) || !is_int($attackerId)) {
            throw new GameInvalidException('Game strike attacker is invalid');
        }

        $reduceBy = $this->rules->dodgeSoakReduction(
            $game->getSpaceId(),
            $game->getRulesRevision(),
            $pending,
            $attackerKind,
            $attackerId,
            $profileType,
            1,
            $attackerKind === 'npc' ? $this->sheets->getNpcSheet($game->getId(), $attackerId) : null,
            $attackerKind === 'npc' ? $this->npcChoices($game->getId(), $attackerId) : null,
            $attackerKind === 'npc' ? $this->sheets->getNpcVersion($game->getId(), $attackerId) : null,
        );

        return $this->rules->evaluateSoak(
            $game->getSpaceId(),
            $game->getRulesRevision(),
            $itemRuleCode,
            $profileType,
            $profileIndex,
            $kind,
            $defenderId,
            $kind === 'npc' ? $this->sheets->getNpcSheet($game->getId(), $defenderId) : null,
            $kind === 'npc' ? $this->npcChoices($game->getId(), $defenderId) : null,
            $kind === 'npc' ? $this->sheets->getNpcVersion($game->getId(), $defenderId) : null,
            $reaction,
            is_string($open['action_rule_code'] ?? null) ? $open['action_rule_code'] : '',
            $success,
            $reduceBy,
        );
    }

    /**
     * Число формулы урона по строке открытого удара.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $open Строка удара.
     *
     * @return DimensionalNumber Пара.
     *
     * @throws GameInvalidException Если строка или формула битые.
     * @throws \Mifrial\Roleplay\Game\Exception\GameNotFoundException Если ревизии нет.
     */
    public function damageOf(GameRecord $game, array $open): DimensionalNumber
    {
        $kind = $open['attacker_kind'] ?? null;
        $attackerId = $open['attacker_id'] ?? null;
        $itemRuleCode = $open['item_rule_code'] ?? null;
        $profileType = $open['profile_type'] ?? null;
        $profileIndex = $open['profile_index'] ?? null;
        $itemInventoryId = $open['item_inventory_id'] ?? null;
        if (
            !is_string($kind)
            || !is_int($attackerId)
            || !is_string($itemRuleCode)
            || !is_string($profileType)
            || !is_int($profileIndex)
            || !is_int($itemInventoryId)
        ) {
            throw new GameInvalidException('Game strike row is invalid');
        }

        $actionRuleCode = is_string($open['action_rule_code'] ?? null) ? $open['action_rule_code'] : '';
        $characteristicModifier = $this->rules->actionCharacteristicModifier(
            $this->rules->ruleSlice($game->getSpaceId(), $game->getRulesRevision()),
            $actionRuleCode,
            $profileType,
            1,
        );

        return $this->rules->evaluateDamage(
            $game->getSpaceId(),
            $game->getRulesRevision(),
            $itemRuleCode,
            $profileType,
            $profileIndex,
            $itemInventoryId,
            $kind,
            $attackerId,
            $kind === 'npc' ? $this->sheets->getNpcSheet($game->getId(), $attackerId) : null,
            $kind === 'npc' ? $this->npcChoices($game->getId(), $attackerId) : null,
            $kind === 'npc' ? $this->sheets->getNpcVersion($game->getId(), $attackerId) : null,
            $characteristicModifier,
        );
    }

    /**
     * Готовит операции листа и последствия попадания.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $open Строка удара.
     * @param DimensionalNumber $injury Повреждение.
     * @param int $success Успех.
     *
     * @return array{operations: list<array<string, mixed>>, consequence: array<string, mixed>}
     *
     * @throws GameInvalidException Если строка, стойкость или лист битые.
     * @throws \Mifrial\Roleplay\Game\Exception\GameNotFoundException Если листа нет.
     */
    public function prepareSheet(GameRecord $game, array $open, DimensionalNumber $injury, int $success): array
    {
        $kind = $open['defender_kind'] ?? null;
        $defenderId = $open['defender_id'] ?? null;
        $itemRuleCode = $open['item_rule_code'] ?? null;
        $profileType = $open['profile_type'] ?? null;
        $profileIndex = $open['profile_index'] ?? null;
        if (
            ($kind !== 'character' && $kind !== 'npc')
            || !is_int($defenderId)
            || !is_string($itemRuleCode)
            || !is_string($profileType)
            || !is_int($profileIndex)
        ) {
            throw new GameInvalidException('Game strike row is invalid');
        }

        $npcSnapshot = $kind === 'npc' ? $this->sheets->getNpcSnapshot($game->getId(), $defenderId) : null;
        $sheet = $npcSnapshot !== null
            ? $npcSnapshot['sheet']['sheet']
            : $this->rules->characterSnapshot($defenderId)['sheet'];
        $npcChoices = $npcSnapshot !== null ? $npcSnapshot['sheet']['choices'] : null;
        $sheetVersion = $npcSnapshot['actualVersion'] ?? null;
        $split = $this->rules->splitInjury(
            $game->getSpaceId(),
            $game->getRulesRevision(),
            $kind,
            $defenderId,
            $kind === 'npc' ? $sheet : null,
            $injury,
            is_array($npcChoices) ? $npcChoices : null,
            is_int($sheetVersion) ? $sheetVersion : null,
        );

        return $this->consequences->effects(
            $game,
            $sheet,
            $injury,
            $success,
            $split,
            $this->rules->damageTypeCode(
                $game->getSpaceId(),
                $game->getRulesRevision(),
                $itemRuleCode,
                $profileType,
                $profileIndex,
            ),
            true,
            $this->rules->enduranceOf(
                $game->getSpaceId(),
                $game->getRulesRevision(),
                $kind,
                $defenderId,
                $kind === 'npc' ? $sheet : null,
                is_array($npcChoices) ? $npcChoices : null,
                is_int($sheetVersion) ? $sheetVersion : null,
            ),
        );
    }

    /**
     * Проверяет поля строки сопротивления.
     *
     * @param mixed $kind Вид защитника.
     * @param mixed $defenderId Идентификатор защитника.
     * @param mixed $itemRuleCode Код предмета.
     * @param mixed $profileType Вид профиля.
     * @param mixed $profileIndex Индекс профиля.
     *
     * @return bool true, если строка корректна.
     */
    private function isResistanceRow(
        mixed $kind,
        mixed $defenderId,
        mixed $itemRuleCode,
        mixed $profileType,
        mixed $profileIndex,
    ): bool {
        return is_string($kind)
            && is_int($defenderId)
            && is_string($itemRuleCode)
            && is_string($profileType)
            && is_int($profileIndex);
    }

    /**
     * Авторитетные choices персонажа или NPC.
     *
     * @param GameRecord $game Игра.
     * @param string $kind Вид.
     * @param int $id Идентификатор.
     *
     * @return array<string, mixed> Choices.
     *
     * @throws GameInvalidException Если документ NPC битый.
     * @throws \Mifrial\Roleplay\Game\Exception\GameNotFoundException Если цели нет.
     */
    private function defenderChoices(GameRecord $game, string $kind, int $id): array
    {
        if ($kind === 'character') {
            return $this->rules->characterSnapshot($id)['choices'];
        }

        return $this->npcChoices($game->getId(), $id);
    }

    /**
     * Документ choices NPC этой игры.
     *
     * @param int $gameId Игра.
     * @param int $npcId NPC.
     *
     * @return array<string, mixed> Документ.
     *
     * @throws GameInvalidException Если строка чужая или документа нет.
     * @throws \Mifrial\Roleplay\Game\Exception\GameNotFoundException Если строки нет.
     */
    private function npcChoices(int $gameId, int $npcId): array
    {
        $npc = $this->npcs->getById($npcId);
        $choices = $npc->getGameId() === $gameId ? ($npc->getVersion()['choices'] ?? null) : null;
        if (!is_array($choices)) {
            throw new GameInvalidException('Game strike npc choices are missing');
        }

        return $choices;
    }

    /**
     * Ключ кэша листа участника.
     *
     * @param GameRecord $game Игра.
     * @param string $kind Вид.
     * @param int $id Идентификатор.
     *
     * @return string Ключ.
     *
     * @throws GameInvalidException Если NPC чужой.
     * @throws \Mifrial\Roleplay\Game\Exception\GameNotFoundException Если участника нет.
     */
    private function participantKey(GameRecord $game, string $kind, int $id): string
    {
        if ($kind === 'character') {
            return $this->rules->makeParticipantKey(
                'character',
                $id,
                $this->rules->characterSnapshot($id)['actualVersion'],
            );
        }

        return $this->rules->makeParticipantKey(
            'npc',
            $id,
            $this->sheets->getNpcVersion($game->getId(), $id),
        );
    }
}
