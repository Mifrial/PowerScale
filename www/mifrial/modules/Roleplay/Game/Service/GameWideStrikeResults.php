<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Closure;
use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Character\Dto\ResourceSpend;
use Mifrial\Roleplay\Character\Dto\ScalarResourceValue;
use Mifrial\Roleplay\Character\Exception\CharacterConflictException;
use Mifrial\Roleplay\Character\Exception\CharacterNotFoundException;
use Mifrial\Roleplay\Game\Dto\GameBattleRecord;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Exception\GameBattleConflictException;
use Mifrial\Roleplay\Game\Exception\GameEconomyConflictException;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Game\Repository\GameBattleRepository;
use Mifrial\Roleplay\Game\Repository\GameCharacterRepository;
use Mifrial\Roleplay\Game\Repository\GameMemberRepository;
use Mifrial\Roleplay\Game\Repository\GameNpcRepository;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityActionSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Component\ResourceComponent;
use Mifrial\Roleplay\Rule\Dto\Spec\ResourceSpec;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Запись цели широкого удара. Число принятой цели приходит снаружи.
 */
final class GameWideStrikeResults
{
    private readonly GameStrikeAmounts $amounts;

    /**
     * Создаёт расчёт отказа.
     *
     * @param GameStrikeRules $rules Срез и версия персонажа.
     * @param GameBattleRepository $battles Состав.
     * @param GameCharacterRepository $characters Участники Character.
     * @param GameMemberRepository $members Участники игры.
     * @param GameNpcRepository $npcs NPC.
     * @param GameStrikeSheetWrites $sheetWrites Запись деления.
     * @param ConflictSheetProjection $conflictProjection Проекция конфликтов.
     * @param GameStrikeConsequences $consequences Последствия попадания.
     *
     * @return void
     */
    public function __construct(
        private readonly GameStrikeRules $rules,
        private readonly GameBattleRepository $battles,
        private readonly GameCharacterRepository $characters,
        private readonly GameMemberRepository $members,
        private readonly GameNpcRepository $npcs,
        private readonly GameStrikeSheetWrites $sheetWrites,
        private readonly ConflictSheetProjection $conflictProjection,
        private readonly GameStrikeConsequences $consequences,
    ) {
        $this->amounts = new GameStrikeAmounts();
    }

    /**
     * Карта ожидания после реакций целей.
     *
     * @var array<string, mixed>
     */
    private array $pendingAfter = [];

    /**
     * Карта ожидания после последнего build.
     *
     * @return array<string, mixed> Карта.
     */
    public function pendingAfter(): array
    {
        return $this->pendingAfter;
    }

    /**
     * Записи целей. Отказ одной не выкидывает остальные.
     *
     * @param GameRecord $game Игра.
     * @param list<array<string, mixed>> $rows Цели.
     * @param list<array{reaction: string, blockItemRuleCode: string|null, expectedSheetVersion: int}> $choices Защиты.
     * @param list<string|null> $refusals Non-CAS отказы по целям.
     * @param DimensionalNumber $damage Пара формулы атакующего.
     * @param array<string, mixed> $attackerRoll Бросок атакующего.
     * @param string $itemRuleCode Предмет удара.
     * @param string $profileType Вид профиля.
     * @param int $profileIndex Смещение.
     * @param int $actorUserId Актор.
     * @param bool $viewAll Полный просмотр.
     * @param Closure|null $penetrationFactory Lazy penetration resolver.
     * @param array<string, mixed> $open Открытый удар.
     * @param GameBattleRecord|null $battle Бой для привязки треда атаки к ходу.
     *
     * @return list<array<string, mixed>> Итоги.
     *
     * @throws GameInvalidException Если строка цели или надетый код битые.
     * @throws GameNotFoundException Если ревизии или листа нет.
     */
    public function build(
        GameRecord $game,
        array $rows,
        array $choices,
        array $refusals,
        DimensionalNumber $damage,
        array $attackerRoll,
        string $itemRuleCode,
        string $profileType,
        int $profileIndex,
        int $actorUserId,
        bool $viewAll,
        ?Closure $penetrationFactory = null,
        array $open = [],
        ?GameBattleRecord $battle = null,
    ): array {
        $this->pendingAfter = $battle?->getPendingActionEffects() ?? [];
        $results = [];
        $sharedPenetration = null;
        $penetrationEvaluated = false;
        $sharedPenetrationFactory = $penetrationFactory === null
            ? null
            : function () use (&$sharedPenetration, &$penetrationEvaluated, $penetrationFactory): DimensionalNumber {
                if (!$penetrationEvaluated) {
                    $sharedPenetration = $penetrationFactory();
                    $penetrationEvaluated = true;
                }

                if (!$sharedPenetration instanceof DimensionalNumber) {
                    throw new GameInvalidException('Game wide strike penetration is invalid');
                }

                return $sharedPenetration;
            };
        foreach ($rows as $index => $row) {
            $results[] = $this->one(
                $game,
                $row,
                $choices[$index],
                $refusals[$index] ?? null,
                $damage,
                $attackerRoll,
                $itemRuleCode,
                $profileType,
                $profileIndex,
                $actorUserId,
                $viewAll,
                $sharedPenetrationFactory,
            );
        }

        $this->postWide(
            $game,
            $rows,
            $choices,
            $results,
            $attackerRoll,
            $itemRuleCode,
            $profileType,
            $profileIndex,
            $actorUserId,
            $open,
            $battle,
        );

        return $results;
    }

    /**
     * Stale CAS любой цели прерывает close до roll.
     *
     * @param GameRecord $game Игра.
     * @param int $battleId Бой.
     * @param list<array<string, mixed>> $rows Цели.
     * @param list<array{reaction: string, blockItemRuleCode: string|null, expectedSheetVersion: int}> $choices Защиты.
     * @param int $actorUserId Актор.
     * @param bool $viewAll Полный просмотр.
     *
     * @return list<string|null> Non-CAS коды отказа по целям.
     *
     * @throws GameBattleConflictException Если версия листа другая.
     * @throws GameInvalidException Если строка цели битая.
     * @throws GameNotFoundException Если листа нет.
     */
    public function assertSheets(
        GameRecord $game,
        int $battleId,
        array $rows,
        array $choices,
        int $actorUserId,
        bool $viewAll,
    ): array {
        $refusals = [];
        foreach ($rows as $index => $row) {
            $target = $this->defender($row);
            $refusals[$index] = $this->assertTargetSheet(
                $game,
                $battleId,
                $target,
                $choices[$index],
                $actorUserId,
                $viewAll,
            );
        }

        return $refusals;
    }

    /**
     * Проверяет лист одной цели.
     *
     * @param GameRecord $game Игра.
     * @param int $battleId Бой.
     * @param array{type: string, id: int} $target Цель.
     * @param array{reaction: string, blockItemInventoryId: int|null, blockItemProfileIndex: int|null, blockItemRuleCode: string|null, expectedSheetVersion: int} $choice Защита.
     * @param int $actorUserId Актор.
     * @param bool $viewAll Полный просмотр.
     *
     * @return string|null Non-CAS код отказа.
     *
     * @throws GameBattleConflictException Если версия листа другая.
     * @throws GameInvalidException Если строка цели битая.
     * @throws GameNotFoundException Если листа нет.
     */
    public function assertTargetSheet(
        GameRecord $game,
        int $battleId,
        array $target,
        array $choice,
        int $actorUserId,
        bool $viewAll,
    ): ?string {
        $code = $this->refusal($game, $battleId, $target, $choice);
        if ($code !== null) {
            return $code;
        }

        $this->assertSheet(
            $game,
            $target,
            $choice['expectedSheetVersion'],
            $actorUserId,
            $viewAll,
        );

        return null;
    }

    /**
     * Разрешает коды block из authoritative inventory целей.
     *
     * @param GameRecord $game Игра.
     * @param list<array<string, mixed>> $rows Цели.
     * @param list<array<string, mixed>> $choices Защиты.
     * @param list<string|null> $refusals Отказы.
     *
     * @return list<array<string, mixed>> Authoritative защиты.
     *
     * @throws GameInvalidException Если документ цели или выбор битые.
     * @throws GameNotFoundException Если цель отсутствует.
     */
    public function resolveChoices(
        GameRecord $game,
        array $rows,
        array $choices,
        array $refusals,
    ): array {
        foreach ($rows as $index => $row) {
            if (($refusals[$index] ?? null) !== null || ($choices[$index]['reaction'] ?? null) !== 'block') {
                continue;
            }

            $target = $this->defender($row);
            $inventoryId = $choices[$index]['blockItemInventoryId'] ?? null;
            if (!is_int($inventoryId)) {
                throw new GameInvalidException('Game wide strike block item is invalid');
            }

            $choices[$index]['blockItemRuleCode'] = $this->rules->getBlockItemRuleCode(
                $this->targetChoices($game, $target),
                $inventoryId,
            );
        }

        return $choices;
    }

    /**
     * Одна цель.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $row Цель.
     * @param array{type: string, id: int} $target Цель.
     * @param array{reaction: string, blockItemInventoryId: int|null, blockItemProfileIndex: int|null, blockItemRuleCode: string|null, expectedSheetVersion: int} $choice Защита.
     * @param string|null $refusalCode Non-CAS код отказа.
     * @param DimensionalNumber $damage Пара принятой цели.
     * @param int $rating Рейтинг попадания.
     * @param string $itemRuleCode Предмет удара.
     * @param string $profileType Вид профиля.
     * @param int $profileIndex Смещение.
     * @param int $actorUserId Актор.
     * @param bool $viewAll Полный просмотр.
     * @param Closure|null $penetrationFactory Shared lazy penetration resolver.
     *
     * @return array<string, mixed> Запись.
     *
     * @throws GameInvalidException Если колонки или надетый код битые.
     * @throws GameNotFoundException Если ревизии или листа нет.
     */
    private function one(
        GameRecord $game,
        array $row,
        array $choice,
        ?string $refusalCode,
        DimensionalNumber $damage,
        array $attackerRoll,
        string $itemRuleCode,
        string $profileType,
        int $profileIndex,
        int $actorUserId,
        bool $viewAll,
        ?Closure $penetrationFactory,
    ): array {
        $target = $this->defender($row);
        if ($refusalCode !== null) {
            return $this->row($target, $refusalCode, null, null, null);
        }

        $spends = $this->resourceSpends($game, $target, $choice);
        $slice = null;
        $reactionRuleCode = null;
        $pending = $this->pendingAfter;
        if ($choice['reaction'] !== 'ignore') {
            $slice = $this->rules->ruleSlice($game->getSpaceId(), $game->getRulesRevision());
            $reactionRuleCode = $this->rules->reactionRuleCode(
                $game->getSpaceId(),
                $game->getRulesRevision(),
                $choice['reaction'],
            );
            if ($reactionRuleCode !== null) {
                $declared = (new GamePendingActionCosts())->declare(
                    $slice,
                    $this->targetChoices($game, $target),
                    $reactionRuleCode,
                    $target['type'],
                    $target['id'],
                    $pending,
                    $this->spendAmounts($spends),
                    false,
                );
                $spends = $this->spendRows($declared['amounts']);
                $pending = $declared['pending'];
            }
        }

        if (
            $spends !== [] && !$this->rules->hasSufficientResources(
                $game->getSpaceId(),
                $game->getRulesRevision(),
                $this->targetSheet($game, $target),
                $spends,
            )
        ) {
            $zero = $this->amounts->view($this->amounts->zero());

            return $this->row($target, null, null, $zero, $zero, null, $zero);
        }

        $hitCode = $this->rules->findHitCode($game->getSpaceId(), $game->getRulesRevision());
        $advantages = (new GamePendingActionCosts())->checkAdvantages(
            $pending,
            $target['type'],
            $target['id'],
            $hitCode,
            'defender',
        );
        if ($reactionRuleCode !== null && $slice !== null) {
            array_push($advantages, ...$this->rules->actionCheckAdvantages($slice, $reactionRuleCode, $hitCode));
        }

        $defenderRoll = null;
        $ratedRoll = $attackerRoll;
        if ($choice['reaction'] === 'dodge') {
            $defenderRoll = $this->rules->rollDodge(
                $game->getSpaceId(),
                $game->getRulesRevision(),
                $hitCode,
                $this->targetSheet($game, $target),
                $this->targetChoices($game, $target),
                $profileType,
                $attackerRoll,
                $this->participantKey($game, $target),
                $advantages,
            );
            $ratedRoll = $this->rules->rerateHit($attackerRoll, $defenderRoll);
        }

        if ($choice['reaction'] === 'block') {
            $defenderRoll = $this->rules->rollBlock(
                $game->getSpaceId(),
                $game->getRulesRevision(),
                $hitCode,
                $this->targetSheet($game, $target),
                $this->targetChoices($game, $target),
                $choice['blockItemInventoryId'],
                $choice['blockItemProfileIndex'],
                $attackerRoll,
                $this->participantKey($game, $target),
                $advantages,
            );
        }

        $rating = $ratedRoll['rating'];
        $autoFail = $this->rules->isHitAutoFail($attackerRoll['roll']);
        $defenderWon = $defenderRoll !== null && ($defenderRoll['success'] ?? false) === true;
        $success = $choice['reaction'] === 'dodge'
            ? (($autoFail || $defenderWon) ? 0 : max(0, $rating))
            : ($autoFail || $rating <= 0 ? 0 : ($defenderWon ? 1 : $rating));
        $effectiveDamage = $success === 0 ? $this->amounts->zero() : $damage;
        $resistance = $success === 0
            ? $this->amounts->zero()
            : $this->resistanceOf(
                $game,
                $target,
                $itemRuleCode,
                $profileType,
                $profileIndex,
                $this->resistanceChoice($choice, $defenderRoll),
                $success,
                $penetrationFactory === null ? null : $penetrationFactory(),
            );
        $soak = $choice['reaction'] === 'dodge'
            ? $this->soakOf($game, $target, $itemRuleCode, $profileType, $profileIndex)
            : null;
        $injury = $this->amounts->injury($effectiveDamage, $resistance, $success, $soak);
        $record = $this->row(
            $target,
            null,
            $success,
            $this->amounts->view($effectiveDamage),
            $this->amounts->view($resistance),
            $soak === null ? null : $this->amounts->view($soak),
            $this->amounts->view($injury),
            $defenderRoll,
        );
        if (!$autoFail || $spends !== []) {
            $record['sheetVersion'] = $autoFail
                ? $this->sheetWrites->writeResources(
                    $game,
                    $target['type'],
                    $target['id'],
                    $choice['expectedSheetVersion'],
                    $spends,
                    ['type' => $target['type'], 'id' => $target['id']],
                    fn (CharacterConflictException|GameEconomyConflictException $exception): array => $this->projectWriteConflict(
                        $game,
                        $target,
                        $exception,
                        $actorUserId,
                        $viewAll,
                    ),
                )
                : $this->writeTarget(
                    $game,
                    $target,
                    $choice,
                    $itemRuleCode,
                    $profileType,
                    $profileIndex,
                    $injury,
                    $success,
                    $spends,
                    $actorUserId,
                    $viewAll,
                    $record,
                );
        }

        if ($reactionRuleCode !== null && $slice !== null) {
            $pending = (new GamePendingActionCosts())->complete(
                $slice,
                $this->targetChoices($game, $target),
                $reactionRuleCode,
                $target['type'],
                $target['id'],
                $pending,
                $this->spendAmounts($spends),
            );
            $this->pendingAfter = $pending;
        }

        $record['chat'] = true;

        return $record;
    }

    /**
     * @param array{type: string, id: int} $target Цель.
     * @param array<string, mixed> $choice Защита.
     * @param array<string, mixed> $record Итог цели, дополняется бросками.
     */
    private function writeTarget(
        GameRecord $game,
        array $target,
        array $choice,
        string $itemRuleCode,
        string $profileType,
        int $profileIndex,
        DimensionalNumber $injury,
        int $success,
        array $spends,
        int $actorUserId,
        bool $viewAll,
        array &$record,
    ): int {
        $sheet = $this->targetSheet($game, $target);
        $choices = $target['type'] === 'npc' ? $this->targetChoices($game, $target) : null;
        $split = $this->rules->splitInjury(
            $game->getSpaceId(),
            $game->getRulesRevision(),
            $target['type'],
            $target['id'],
            $target['type'] === 'npc' ? $sheet : null,
            $injury,
            $choices,
            $target['type'] === 'npc' ? $this->npcSheetVersion($game->getId(), $target['id']) : null,
        );
        $endurance = $this->rules->enduranceOf(
            $game->getSpaceId(),
            $game->getRulesRevision(),
            $target['type'],
            $target['id'],
            $target['type'] === 'npc' ? $sheet : null,
            $choices,
            $target['type'] === 'npc' ? $this->npcSheetVersion($game->getId(), $target['id']) : null,
        );
        $prepared = $this->consequences->effects(
            $game,
            $sheet,
            $injury,
            $success,
            $split,
            $this->rules->damageTypeCode($game->getSpaceId(), $game->getRulesRevision(), $itemRuleCode, $profileType, $profileIndex),
            true,
            $endurance,
        );
        $record = array_merge($record, $prepared['consequence']);
        $version = $this->sheetWrites->write(
            $game,
            $target['type'],
            $target['id'],
            $choice['expectedSheetVersion'],
            $prepared['operations'],
            ['type' => $target['type'], 'id' => $target['id']],
            fn (CharacterConflictException|GameEconomyConflictException $exception): array => $this->projectWriteConflict(
                $game,
                $target,
                $exception,
                $actorUserId,
                $viewAll,
            ),
            $spends,
        );

        return $version;
    }

    /**
     * @param list<array<string, mixed>> $rows Цели.
     * @param list<array<string, mixed>> $choices Защиты.
     * @param list<array<string, mixed>> $results Итоги.
     * @param array<string, mixed> $attackerRoll Бросок атаки.
     * @param GameBattleRecord|null $battle Бой для привязки треда атаки к ходу.
     */
    private function postWide(
        GameRecord $game,
        array $rows,
        array $choices,
        array $results,
        array $attackerRoll,
        string $itemRuleCode,
        string $profileType,
        int $profileIndex,
        int $actorUserId,
        array $open,
        ?GameBattleRecord $battle,
    ): void {
        $shown = [];
        foreach ($results as $index => $record) {
            if (($record['chat'] ?? false) !== true) {
                continue;
            }

            $shown[] = ['row' => $rows[$index], 'choice' => $choices[$index], 'record' => $record];
        }

        if ($shown === [] || !isset($rows[0])) {
            return;
        }

        $row = $open !== [] ? $open : $rows[0];
        $slice = $this->rules->ruleSlice($game->getSpaceId(), $game->getRulesRevision());
        $attacker = $this->entityToken($row['attacker_kind'] ?? 'character', $row['attacker_id'] ?? 0);
        $attackerName = $this->entityName(
            is_string($row['attacker_kind'] ?? null) ? $row['attacker_kind'] : 'character',
            is_int($row['attacker_id'] ?? null) ? $row['attacker_id'] : 0,
        );
        $actionCode = is_string($row['action_rule_code'] ?? null) ? $row['action_rule_code'] : '';
        $action = $actionCode !== '' ? '[[rule:' . $actionCode . ']]' : 'действие';
        $kind = match ($profileType) {
            'throw' => 'бросок',
            'shoot' => 'выстрел',
            default => 'удар',
        };
        $typeBit = $this->damageTypeBit($game, $itemRuleCode, $profileType, $profileIndex, $slice);
        $targets = [];
        $reactions = [];
        foreach ($shown as $item) {
            $target = $item['record']['target'] ?? $this->defender($item['row']);
            $name = $this->entityToken($target['type'], $target['id']);
            $targets[] = $name;
            $reactions[] = $this->reactionBit($slice, $name, $item['choice']['reaction'] ?? 'ignore');
        }

        $narrative = $attacker . ' наносит ' . $typeBit . ' ' . $kind . ' оружием [[rule:' . $itemRuleCode . ']] по '
            . $this->joinNames($targets) . '. ' . implode(', ', $reactions) . '!';
        $threadId = 'attack-wide-' . ($row['id'] ?? 0);
        $parentId = $battle === null ? null : GameCombatThread::buildAttack($battle, $threadId)['parentId'];
        $speaker = $this->speakerOf($row['attacker_kind'] ?? 'character', $row['attacker_id'] ?? 0);
        $attackCard = $attackerRoll;
        $attackCard['label'] = 'Проверка на попадание · ' . $attackerName;
        $attackCard['min_success_size'] = -1;
        $rolls = [$attackCard];
        foreach ($shown as $item) {
            if (!is_array($item['record']['defenderRoll'] ?? null)) {
                continue;
            }

            $target = is_array($item['record']['target'] ?? null) ? $item['record']['target'] : ['type' => 'character', 'id' => 0];
            $defenderName = $this->entityName(
                is_string($target['type'] ?? null) ? $target['type'] : 'character',
                is_int($target['id'] ?? null) ? $target['id'] : 0,
            );
            $reaction = $item['choice']['reaction'] ?? 'ignore';
            $card = $item['record']['defenderRoll'];
            $card['label'] = ($reaction === 'block' ? 'Блок' : 'Уклонение') . ' · ' . $defenderName . ' против ' . $attackerName;
            $card['min_success_size'] = -1;
            $rolls[] = $card;
        }

        $this->consequences->postLines($game, $actorUserId, $threadId, [
            $attacker . ' совершает действие ' . $action . ' за ' . $this->actionAp($slice, $actionCode) . 'ОД.',
            $narrative,
        ], $speaker, $parentId);
        $this->consequences->postRolls($game, $actorUserId, $threadId, $rolls, $speaker, $parentId);
        foreach ($shown as $item) {
            $this->postWideOutcome(
                $game,
                $actorUserId,
                $threadId,
                $attacker,
                $item['record'],
                $typeBit,
                $speaker,
                $parentId,
            );
        }
    }

    /**
     * @param array<string, mixed> $record Итог цели.
     * @param array{kind: string, characterId?: int, characterName?: string, npcId?: int, npcName?: string} $speaker Спикер.
     * @param string|null $parentId Родительский ход.
     */
    private function postWideOutcome(
        GameRecord $game,
        int $actorUserId,
        string $threadId,
        string $attacker,
        array $record,
        string $typeBit,
        array $speaker,
        ?string $parentId = null,
    ): void {
        $target = is_array($record['target'] ?? null) ? $record['target'] : ['type' => 'character', 'id' => 0];
        $defender = $this->entityToken($target['type'] ?? 'character', $target['id'] ?? 0);
        $success = is_int($record['success'] ?? null) ? $record['success'] : 0;
        $exhaustion = $record['exhaustionDelta'] ?? 0;
        $wound = $record['wound'] ?? 0;
        $will = isset($record['will']) ? [
            'roll' => $record['will'],
            'text' => $this->exhaustionText($defender, $record['will']['rating'] ?? 0, $record['willOutcome'] ?? 'clear'),
        ] : null;
        $maim = isset($record['maim']) ? $this->maimPayload($defender, $record['maim'], $typeBit) : null;
        $bits = [$exhaustion . ' истощения'];
        if ($wound > 0) {
            $bits[] = $wound . ' рану';
        }

        $this->consequences->postOutcome(
            $game,
            $actorUserId,
            $threadId,
            $success > 0
                ? $attacker . ' попадает по ' . $defender . ' с ' . $success . ' РУ и наносит ' . implode(' и ', $bits) . '!'
                : $attacker . ' промахивается по ' . $defender . '!',
            [
                'raw' => is_array($record['injury'] ?? null) ? ($record['injury']['base'] ?? 0) : 0,
                'damage' => is_array($record['damage'] ?? null) ? $record['damage'] : ['base' => 0, 'size' => 0],
                'damageTypeName' => $typeBit,
                'resistance' => is_array($record['resistance'] ?? null) ? ($record['resistance']['base'] ?? 0) : 0,
                'endurance' => is_array($record['endurance'] ?? null) ? $record['endurance'] : ['base' => 0, 'size' => 0],
                'defenseIgnored' => false,
                'attackSrLabel' => (string) $success,
                'appliedSr' => $success,
                'srCap' => null,
                'heading' => null,
                'stun' => null,
                'shock' => null,
                'exhaustion' => $exhaustion,
                'remainingHpDamage' => 0,
                'wound' => $wound,
                'knockout' => ($record['unconscious'] ?? false) === true,
                'cuttingWound' => null,
                'layers' => [],
                'injury' => $record['injury'] ?? null,
            ],
            $will,
            $maim,
            $speaker,
            $parentId,
        );
    }

    /**
     * Текст и вложение проверки на увечье.
     *
     * @param string $target Цель.
     * @param array<string, mixed> $roll Бросок проверки.
     * @param string $damageTypeName Вид урона.
     *
     * @return array{roll: array<string, mixed>, text: string, details: array<string, mixed>}
     */
    private function maimPayload(string $target, array $roll, string $damageTypeName): array
    {
        $rating = is_int($roll['rating'] ?? null) ? $roll['rating'] : 0;

        return [
            'roll' => $roll,
            'text' => $this->maimText($target, $rating),
            'details' => [
                'injury' => [
                    'strength' => $rating < 0 ? -$rating : 0,
                    'permanent' => false,
                    'temporary' => true,
                    'lethal' => false,
                    'disfiguring' => false,
                    'rating' => $rating,
                    'difficulty' => is_array($roll['difficulty'] ?? null)
                        ? ($roll['difficulty']['base'] ?? null)
                        : null,
                ],
                'damageTypeName' => $damageTypeName,
            ],
        ];
    }

    private function maimText(string $target, int $rating): string
    {
        if ($rating >= 0) {
            return $target . ' не получает увечье.';
        }

        return $target . ' получает временное увечье с силой ' . (-$rating) . '.';
    }

    /**
     * @param list<string> $names Имена.
     */
    private function joinNames(array $names): string
    {
        if (count($names) < 2) {
            return $names[0] ?? '';
        }

        $last = array_pop($names);

        return implode(', ', $names) . ' и ' . $last;
    }

    private function reactionBit(CharacterRuleSlice $slice, string $name, string $reaction): string
    {
        if ($reaction === 'ignore') {
            return $name . ' не реагирует';
        }

        $code = $this->reactionRuleCode($slice, $reaction);
        $token = $code !== null ? '[[rule:' . $code . ']]' : $reaction;

        return $name . ' пытается совершить ' . $token . ' за ' . $this->actionAp($slice, $code ?? '') . 'ОД';
    }

    private function entityToken(mixed $kind, mixed $id): string
    {
        $prefix = $kind === 'npc' ? 'npc' : 'character';
        $numericId = is_int($id) ? $id : 0;
        $name = $this->entityName(is_string($kind) ? $kind : 'character', $numericId);

        return '[[' . $prefix . ':' . $numericId . ',' . $name . ']]';
    }

    /**
     * @return array{kind: string, characterId?: int, characterName?: string, npcId?: int, npcName?: string}
     */
    private function speakerOf(mixed $kind, mixed $id): array
    {
        $numericId = is_int($id) ? $id : 0;
        $name = $this->entityName(is_string($kind) ? $kind : 'character', $numericId);
        if ($kind === 'npc') {
            return ['kind' => 'npc', 'npcId' => $numericId, 'npcName' => $name];
        }

        return ['kind' => 'character', 'characterId' => $numericId, 'characterName' => $name];
    }

    private function entityName(string $kind, int $id): string
    {
        if ($id <= 0) {
            return '';
        }

        try {
            if ($kind === 'npc') {
                return $this->npcs->getById($id)->getName();
            }

            return $this->rules->characterName($id);
        } catch (GameNotFoundException | CharacterNotFoundException) {
            return (string) $id;
        }
    }

    private function actionAp(CharacterRuleSlice $slice, string $ruleCode): int
    {
        if ($ruleCode === '') {
            return 0;
        }

        $spec = $slice->findLive($ruleCode)?->getSpec();
        if (!$spec instanceof AbilityActionSpec) {
            return 0;
        }

        $turnCode = null;
        foreach ($slice->getLiveRules() as $rule) {
            $resource = $rule->getSpec();
            if ($resource instanceof ResourceSpec && $resource->isTurnRefill()) {
                $turnCode = $rule->getCode();
            }
        }

        $sum = 0;
        foreach ($spec->getComponents() as $component) {
            if (!$component instanceof ResourceComponent) {
                continue;
            }

            if ($turnCode !== null && $component->getResourceCode() !== $turnCode) {
                continue;
            }

            $amount = $component->getAmount();
            if (is_int($amount)) {
                $sum += $amount;
            } elseif ($amount instanceof DimensionalNumber) {
                $sum += $amount->getBase();
            }
        }

        return $sum;
    }

    private function reactionRuleCode(CharacterRuleSlice $slice, string $reaction): ?string
    {
        foreach ($slice->getLiveRules() as $rule) {
            $spec = $rule->getSpec();
            if ($spec instanceof AbilityActionSpec && $spec->getBase()->getCombatAction() === $reaction) {
                return $rule->getCode();
            }
        }

        return null;
    }

    private function damageTypeBit(
        GameRecord $game,
        string $itemRuleCode,
        string $profileType,
        int $profileIndex,
        CharacterRuleSlice $slice,
    ): string {
        $code = $this->rules->damageTypeCode(
            $game->getSpaceId(),
            $game->getRulesRevision(),
            $itemRuleCode,
            $profileType,
            $profileIndex,
        );
        $name = $code === null ? null : $slice->findLive($code)?->getName();

        return is_string($name) && $name !== '' ? mb_strtolower($name) : 'безымянный';
    }

    private function exhaustionText(string $target, int $rating, string $outcome): string
    {
        $label = match ($outcome) {
            'weakness' => 'Слабость',
            'disabled' => 'Обессилен',
            'unconscious' => 'Потеря сознания',
            default => 'без Упадка сил',
        };
        if ($outcome === 'clear') {
            $shown = ($rating >= 0 ? '+' : '') . $rating;

            return $target . ' выдерживает истощение (РУ ' . $shown . ') — ' . $label . '.';
        }

        return $target . ' не выдерживает истощение (РУ ' . $rating . ') — ' . $label . '.';
    }

    /**
     * Убирает block layers после проигранного defender roll.
     *
     * @param array<string, mixed> $choice Реакция.
     * @param array<string, mixed>|null $defenderRoll Roll защитника.
     *
     * @return array<string, mixed> Выбор для projection.
     */
    private function resistanceChoice(array $choice, ?array $defenderRoll): array
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
     * Разрешает resource spends accepted target.
     *
     * @param GameRecord $game Игра.
     * @param array{type: string, id: int} $target Цель.
     * @param array{reaction: string, blockItemRuleCode: string|null, expectedSheetVersion: int} $choice Reaction.
     *
     * @return array<int, ResourceSpend> Native spends.
     *
     * @throws GameInvalidException If action or resource is invalid.
     * @throws GameNotFoundException If revision or target is absent.
     */
    private function resourceSpends(GameRecord $game, array $target, array $choice): array
    {
        if ($choice['reaction'] === 'ignore') {
            return [];
        }

        $blockRuleCode = $choice['reaction'] === 'block'
            ? $this->rules->getBlockItemRuleCode(
                $this->targetChoices($game, $target),
                $choice['blockItemInventoryId'],
            )
            : ($choice['blockItemRuleCode'] ?? '');

        return $this->rules->resolveReactionResourceSpends(
            $game->getSpaceId(),
            $game->getRulesRevision(),
            $choice['reaction'],
            $choice['reaction'] === 'block' ? $choice['blockItemInventoryId'] : null,
            $blockRuleCode,
            $this->targetChoices($game, $target),
            $this->targetSheet($game, $target),
        );
    }

    /**
     * Целые траты в карту ресурсов.
     *
     * @param array<int, ResourceSpend> $spends Траты.
     *
     * @return array<string, int> Суммы.
     */
    private function spendAmounts(array $spends): array
    {
        $amounts = [];
        foreach ($spends as $spend) {
            $native = $spend->getAmount()->toNative();
            if (!is_int($native)) {
                continue;
            }

            $amounts[$spend->getResourceCode()] = ($amounts[$spend->getResourceCode()] ?? 0) + $native;
        }

        return $amounts;
    }

    /**
     * Карта ресурсов в траты.
     *
     * @param array<string, int> $amounts Суммы.
     *
     * @return array<int, ResourceSpend> Траты.
     */
    private function spendRows(array $amounts): array
    {
        $rows = [];
        foreach ($amounts as $code => $amount) {
            $rows[] = new ResourceSpend($code, new ScalarResourceValue($amount));
        }

        return $rows;
    }

    /**
     * Возвращает authoritative sheet цели.
     *
     * @param GameRecord $game Игра.
     * @param array{type: string, id: int} $target Цель.
     *
     * @return array<string, mixed> Sheet.
     *
     * @throws GameInvalidException If target document is invalid.
     * @throws GameNotFoundException If target is absent.
     */
    private function targetSheet(GameRecord $game, array $target): array
    {
        if ($target['type'] === 'character') {
            return $this->rules->characterSnapshot($target['id'])['sheet'];
        }

        return $this->defenderSheet($game->getId(), $target['id']);
    }

    /**
     * Возвращает authoritative choices цели.
     *
     * @param GameRecord $game Игра.
     * @param array{type: string, id: int} $target Цель.
     *
     * @return array<string, mixed> Choices.
     *
     * @throws GameInvalidException If NPC document is invalid.
     * @throws GameNotFoundException If target is absent.
     */
    private function targetChoices(GameRecord $game, array $target): array
    {
        if ($target['type'] === 'character') {
            return $this->rules->characterSnapshot($target['id'])['choices'];
        }

        return $this->defenderChoices($game->getId(), $target['id']);
    }

    /**
     * Проецирует race-конфликт записи цели.
     *
     * @param GameRecord $game Игра.
     * @param array{type: string, id: int} $target Цель.
     * @param CharacterConflictException|GameEconomyConflictException $exception Исходный конфликт.
     * @param int $actorUserId Актор.
     * @param bool $viewAll Полный просмотр.
     *
     * @return array{choices: array<string, mixed>, sheet: array<string, mixed>} Проекция.
     *
     * @throws GameInvalidException Если снимок листа битый.
     * @throws GameNotFoundException Если цель или membership отсутствуют.
     */
    private function projectWriteConflict(
        GameRecord $game,
        array $target,
        CharacterConflictException|GameEconomyConflictException $exception,
        int $actorUserId,
        bool $viewAll,
    ): array {
        $current = $exception->getCurrentSheet();
        if ($current === null) {
            throw new GameInvalidException('Game wide strike conflict sheet is missing');
        }

        return $this->projectTargetSheet($game, $target, $current, $actorUserId, $viewAll);
    }

    /**
     * Код отказа или null.
     *
     * @param GameRecord $game Игра.
     * @param int $battleId Бой.
     * @param array{type: string, id: int} $target Цель.
     * @param array{reaction: string, blockItemInventoryId: int|null, blockItemProfileIndex: int|null, blockItemRuleCode: string|null, expectedSheetVersion: int} $choice Защита.
     *
     * @return string|null Код.
     */
    private function refusal(GameRecord $game, int $battleId, array $target, array $choice): ?string
    {
        $code = null;
        try {
            $this->assertDefender($battleId, $target);
            $this->assertReaction($game, $target, $choice);
        } catch (GameNotFoundException) {
            $code = 'GAME_NOT_FOUND';
        } catch (GameInvalidException) {
            $code = 'GAME_INVALID';
        }

        return $code;
    }

    /**
     * Сопротивление брони цели. Вызов снаружи refusal: отказ среза не становится code цели.
     *
     * @param GameRecord $game Игра.
     * @param array{type: string, id: int} $target Цель.
     * @param string $itemRuleCode Предмет удара.
     * @param string $profileType Вид профиля.
     * @param int $profileIndex Смещение.
     * @param array{reaction: string, blockItemRuleCode: string|null, expectedSheetVersion: int} $choice Защита.
     * @param int $rating Рейтинг попадания.
     * @param DimensionalNumber|null $penetration Проникновение.
     *
     * @return DimensionalNumber Сумма слоёв или ноль.
     *
     * @throws GameInvalidException Если профиль или лист битые.
     * @throws GameNotFoundException Если ревизии или листа нет.
     */
    private function resistanceOf(
        GameRecord $game,
        array $target,
        string $itemRuleCode,
        string $profileType,
        int $profileIndex,
        array $choice,
        int $rating,
        ?DimensionalNumber $penetration,
    ): DimensionalNumber {
        return $this->rules->evaluateResistance(
            $game->getSpaceId(),
            $game->getRulesRevision(),
            $itemRuleCode,
            $profileType,
            $profileIndex,
            $target['type'],
            $target['id'],
            $target['type'] === 'npc' ? $this->defenderSheet($game->getId(), $target['id']) : null,
            $target['type'] === 'npc' ? $this->defenderChoices($game->getId(), $target['id']) : null,
            $choice['reaction'],
            $choice['blockItemInventoryId'] ?? null,
            $rating,
            $penetration,
        );
    }

    /**
     * Снимок sheet NPC этой игры.
     *
     * @param int $gameId Игра.
     * @param int $npcId NPC.
     *
     * @return array<string, mixed> Документ sheet.
     *
     * @throws GameInvalidException Если строка чужая или снимка нет.
     * @throws GameNotFoundException Если строки нет.
     */
    private function defenderSheet(int $gameId, int $npcId): array
    {
        $npc = $this->npcs->getById($npcId);
        $sheet = $npc->getGameId() === $gameId ? ($npc->getVersion()['sheet'] ?? null) : null;
        if (!is_array($sheet)) {
            throw new GameInvalidException('Game wide strike npc sheet is missing');
        }

        return $sheet;
    }

    /**
     * Версия листа NPC этой игры.
     *
     * @param int $gameId Игра.
     * @param int $npcId NPC.
     *
     * @return int actualVersion.
     *
     * @throws GameInvalidException Если строка чужая или снимка нет.
     * @throws GameNotFoundException Если строки нет.
     */
    private function npcSheetVersion(int $gameId, int $npcId): int
    {
        $npc = $this->npcs->getById($npcId);
        if ($npc->getGameId() !== $gameId) {
            throw new GameInvalidException('Game wide strike npc sheet is missing');
        }

        return $npc->getActualVersion();
    }

    /**
     * Ключ кэша листа цели.
     *
     * @param GameRecord $game Игра.
     * @param array{type: string, id: int} $target Цель.
     *
     * @return string Ключ.
     *
     * @throws GameInvalidException Если NPC чужой.
     * @throws GameNotFoundException Если цели нет.
     */
    private function participantKey(GameRecord $game, array $target): string
    {
        if ($target['type'] === 'character') {
            return $this->rules->makeParticipantKey(
                'character',
                $target['id'],
                $this->rules->characterSnapshot($target['id'])['actualVersion'],
            );
        }

        return $this->rules->makeParticipantKey(
            'npc',
            $target['id'],
            $this->npcSheetVersion($game->getId(), $target['id']),
        );
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
     * @throws GameNotFoundException Если строки нет.
     */
    private function defenderChoices(int $gameId, int $npcId): array
    {
        $npc = $this->npcs->getById($npcId);
        $choices = $npc->getGameId() === $gameId ? ($npc->getVersion()['choices'] ?? null) : null;
        if (!is_array($choices)) {
            throw new GameInvalidException('Game wide strike npc choices are missing');
        }

        return $choices;
    }

    /**
     * Смягчение уклонения цели. Вызов снаружи refusal.
     *
     * @param GameRecord $game Игра.
     * @param array{type: string, id: int} $target Цель.
     * @param string $itemRuleCode Предмет удара.
     * @param string $profileType Вид профиля.
     * @param int $profileIndex Смещение.
     *
     * @return DimensionalNumber Пара.
     *
     * @throws GameInvalidException Если карточка или закупка битые.
     * @throws GameNotFoundException Если ревизии или листа нет.
     */
    private function soakOf(
        GameRecord $game,
        array $target,
        string $itemRuleCode,
        string $profileType,
        int $profileIndex,
    ): DimensionalNumber {
        return $this->rules->evaluateSoak(
            $game->getSpaceId(),
            $game->getRulesRevision(),
            $itemRuleCode,
            $profileType,
            $profileIndex,
            $target['type'],
            $target['id'],
            $target['type'] === 'npc' ? $this->defenderSheet($game->getId(), $target['id']) : null,
            $target['type'] === 'npc' ? $this->defenderChoices($game->getId(), $target['id']) : null,
            $target['type'] === 'npc' ? $this->npcSheetVersion($game->getId(), $target['id']) : null,
        );
    }

    /**
     * Защитник всё ещё в составе.
     *
     * @param int $battleId Бой.
     * @param array{type: string, id: int} $target Цель.
     *
     * @return void
     *
     * @throws GameNotFoundException Если пары нет.
     * @throws GameInvalidException Если вид чужой.
     */
    private function assertDefender(int $battleId, array $target): void
    {
        $this->assertKind($target['type']);
        if (!in_array($target, $this->battles->findParticipants($battleId), true)) {
            throw new GameNotFoundException('Game battle participant was not found');
        }
    }

    /**
     * Реакция и предмет блока этой цели.
     *
     * @param GameRecord $game Игра.
     * @param array{type: string, id: int} $target Цель.
     * @param array{reaction: string, blockItemInventoryId: int|null, blockItemProfileIndex: int|null, blockItemRuleCode: string|null} $choice Защита.
     *
     * @return void
     *
     * @throws GameInvalidException Если реакция чужая.
     * @throws GameNotFoundException Если ревизии нет.
     */
    private function assertReaction(GameRecord $game, array $target, array $choice): void
    {
        if (!in_array($choice['reaction'], ['ignore', 'dodge', 'block'], true)) {
            throw new GameInvalidException('Game wide strike reaction is invalid');
        }

        if (
            $choice['reaction'] === 'block'
            && (!is_int($choice['blockItemInventoryId'])
                || !is_int($choice['blockItemProfileIndex']))
        ) {
            throw new GameInvalidException('Game wide strike block item is invalid');
        }

        if ($choice['reaction'] === 'block') {
            $this->rules->assertBlockSelection(
                $game->getSpaceId(),
                $game->getRulesRevision(),
                $this->targetChoices($game, $target),
                $choice['blockItemInventoryId'],
                $choice['blockItemProfileIndex'],
                $choice['blockItemRuleCode'],
            );
        }
    }

    /**
     * Версия листа цели.
     *
     * @param GameRecord $game Игра.
     * @param array{type: string, id: int} $target Цель.
     * @param int $expectedSheetVersion Ожидание.
     * @param int $actorUserId Актор.
     * @param bool $viewAll Полный просмотр.
     *
     * @return void
     *
     * @throws GameBattleConflictException Если версия другая.
     * @throws GameNotFoundException Если листа нет.
     */
    private function assertSheet(
        GameRecord $game,
        array $target,
        int $expectedSheetVersion,
        int $actorUserId,
        bool $viewAll,
    ): void {
        if ($target['type'] === 'character') {
            $snapshot = $this->rules->characterSnapshot($target['id']);
            $current = $snapshot['actualVersion'];
            $currentSheet = [
                'choices' => $snapshot['choices'],
                'sheet' => $snapshot['sheet'],
            ];
        } else {
            $npc = $this->npcs->getById($target['id']);
            if ($npc->getGameId() !== $game->getId()) {
                throw new GameNotFoundException('Game wide strike npc was not found');
            }

            $current = $npc->getActualVersion();
            $currentSheet = [
                'choices' => $npc->getVersion()['choices'] ?? [],
                'sheet' => $npc->getVersion()['sheet'] ?? [],
            ];
        }

        $sheet = $this->projectTargetSheet($game, $target, $currentSheet, $actorUserId, $viewAll);

        if ($current !== $expectedSheetVersion) {
            throw new GameBattleConflictException(
                $current,
                currentSheet: $sheet,
                target: ['type' => $target['type'], 'id' => $target['id']],
            );
        }
    }

    /**
     * Проецирует authoritative snapshot цели по правилам Game conflict.
     *
     * @param GameRecord $game Игра.
     * @param array{type: string, id: int} $target Цель.
     * @param array<string, mixed> $currentSheet Снимок листа.
     * @param int $actorUserId Актор.
     * @param bool $viewAll Полный просмотр.
     *
     * @return array{choices: array<string, mixed>, sheet: array<string, mixed>} Проекция.
     *
     * @throws GameInvalidException Если снимок листа битый.
     * @throws GameNotFoundException Если цель или membership отсутствуют.
     */
    private function projectTargetSheet(
        GameRecord $game,
        array $target,
        array $currentSheet,
        int $actorUserId,
        bool $viewAll,
    ): array {
        $choices = $currentSheet['choices'] ?? null;
        $sheet = $currentSheet['sheet'] ?? null;
        if (!is_array($choices) || !is_array($sheet)) {
            throw new GameInvalidException('Game wide strike sheet is invalid');
        }

        if ($target['type'] === 'character') {
            $membership = $this->characters->getByPair($game->getId(), $target['id']);
            $full = $viewAll
                || $game->getOwnerId() === $actorUserId
                || $membership->getCharacterOwnerId() === $actorUserId
                || $this->isGm($game->getId(), $actorUserId);

            return $this->conflictProjection->projectGameCharacter(
                $choices,
                $sheet,
                $membership->getSectionVisibility(),
                $full,
            );
        }

        $npc = $this->npcs->getById($target['id']);
        if ($npc->getGameId() !== $game->getId()) {
            throw new GameNotFoundException('Game wide strike npc was not found');
        }

        $full = $viewAll
            || $game->getOwnerId() === $actorUserId
            || $this->isGm($game->getId(), $actorUserId);
        $version = $this->conflictProjection->projectNpc(
            ['choices' => $choices, 'sheet' => $sheet],
            $npc->getVisibility(),
            $full,
        );

        return [
            'choices' => is_array($version['choices'] ?? null) ? $version['choices'] : [],
            'sheet' => is_array($version['sheet'] ?? null) ? $version['sheet'] : [],
        ];
    }

    /**
     * Проверяет роль GM.
     *
     * @param int $gameId Игра.
     * @param int $actorUserId Актор.
     *
     * @return bool true для GM.
     */
    private function isGm(int $gameId, int $actorUserId): bool
    {
        try {
            return $this->members->getByPair($gameId, $actorUserId)->getRole() === 'gm';
        } catch (GameNotFoundException) {
            return false;
        }
    }

    /**
     * Пара защитника из строки.
     *
     * @param array<string, mixed> $row Цель.
     *
     * @return array{type: string, id: int} Пара.
     *
     * @throws GameInvalidException Если колонки битые.
     */
    private function defender(array $row): array
    {
        $kind = $row['target_type'] ?? null;
        $defenderId = $row['target_id'] ?? null;
        if (!is_string($kind) || !is_int($defenderId)) {
            throw new GameInvalidException('Game wide strike target row is invalid');
        }

        return ['type' => $kind, 'id' => $defenderId];
    }

    /**
     * Вид участника.
     *
     * @param string $kind Вид.
     *
     * @return void
     *
     * @throws GameInvalidException Если вид чужой.
     */
    private function assertKind(string $kind): void
    {
        if ($kind !== 'character' && $kind !== 'npc') {
            throw new GameInvalidException('Game wide strike participant is invalid');
        }
    }

    /**
     * Запись цели.
     *
     * @param array{type: string, id: int} $target Цель.
     * @param string|null $code Код отказа или null.
     * @param int|null $rating Рейтинг принятой цели или null.
     * @param array{base: int, size: int}|null $damage Пара принятой цели или null.
     * @param array{base: int, size: int}|null $resistance Сопротивление цели или null.
     * @param array{base: int, size: int}|null $soak Смягчение dodge или null, если ключ не пишется.
     * @param array{base: int, size: int}|null $injury Повреждение принятой цели или null.
     * @param array<string, mixed>|null $defenderRoll Roll защитника.
     *
     * @return array<string, mixed> Запись.
     */
    private function row(
        array $target,
        ?string $code,
        ?int $rating,
        ?array $damage,
        ?array $resistance,
        ?array $soak = null,
        ?array $injury = null,
        ?array $defenderRoll = null,
    ): array {
        $record = [
            'target' => $target,
            'success' => $rating,
            'damage' => $damage,
            'resistance' => $resistance,
            'injury' => $injury,
            'sheetVersion' => null,
            'code' => $code,
        ];
        if ($defenderRoll !== null) {
            $record['defenderRoll'] = $defenderRoll;
        }

        if ($soak !== null) {
            $record['S'] = $soak;
        }

        return $record;
    }
}
