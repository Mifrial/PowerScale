<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Dto\GameBattleRecord;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Dto\GameStrikeTargetOptions;
use Mifrial\Roleplay\Game\Dto\GameStrikeWriteScope;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Game\Repository\GameActionRepository;
use Mifrial\Roleplay\Game\Repository\GameBattleRepository;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Закрытие широкого удара.
 */
final class GameStrikeWideClose
{
    private readonly GameBattleMarks $marks;

    /**
     * Создаёт закрытие.
     *
     * @param GameStrikeRules $rules Правила.
     * @param GameBattleRepository $battles Бои.
     * @param GameActionRepository $actions Действия и цели.
     * @param GameStrikeAccess $access Права ответа.
     * @param GameStrikeSheets $sheets Листы.
     * @param GameStrikeCloseRows $rows Строки удара.
     * @param GameStrikeTargetClose $targets Расчёт цели.
     * @param GameStrikeTargetChoices $choices Choices целей.
     * @param GameStrikeWideChat $chat Чат.
     * @param GameStrikeSpend $spend Стоимость.
     * @param GameStrikeAmounts $amounts Пары и нули.
     * @param GameStrikeResolution $resolution Расчёт.
     * @param GameStrikeSupport $support Поддержки строки.
     * @param GameStrikeCover $cover Броски прикрытия.
     * @param GameStrikeDeclaredSpend $declaredSpend Списание заявок.
     * @param GameStrikeSheetWrites $sheetWrites Запись листа.
     *
     * @return void
     */
    public function __construct(
        private readonly GameStrikeRules $rules,
        private readonly GameBattleRepository $battles,
        private readonly GameActionRepository $actions,
        private readonly GameStrikeAccess $access,
        private readonly GameStrikeSheets $sheets,
        private readonly GameStrikeCloseRows $rows,
        private readonly GameStrikeTargetClose $targets,
        private readonly GameStrikeTargetChoices $choices,
        private readonly GameStrikeWideChat $chat,
        private readonly GameStrikeSpend $spend,
        private readonly GameStrikeAmounts $amounts,
        private readonly GameStrikeResolution $resolution,
        private readonly GameStrikeSupport $support,
        private readonly GameStrikeCover $cover,
        private readonly GameStrikeDeclaredSpend $declaredSpend,
        private readonly GameStrikeSheetWrites $sheetWrites,
    ) {
        $this->marks = new GameBattleMarks();
    }

    public function close(
        GameRecord $game,
        GameBattleRecord $battle,
        int $expectedBattleVersion,
        array $requestChoices,
        int $actorUserId,
        bool $editAll,
        bool $viewAll,
        array $resume = [],
    ): array {
        $battleId = $battle->getId();
        $open = $this->rows->requireOpen($battleId, 'wide_strike');
        $strikeId = $open['id'];
        $rows = is_array($open['strikes'] ?? null) ? $open['strikes'] : $this->actions->getStrikes($strikeId);
        $indexByTarget = [];
        foreach ($rows as $index => $row) {
            $indexByTarget[$this->rows->targetKey($this->rows->rowTarget($row))] = $index;
        }

        $targetKeys = array_keys($indexByTarget);

        foreach ($requestChoices as $choice) {
            $index = $indexByTarget[$this->rows->targetKey($choice['target'])] ?? null;
            if (!is_int($index)) {
                throw new GameInvalidException('Game wide strike target is invalid');
            }

            $this->access->assertTarget($game, $actorUserId, $editAll, $choice['target']);
            $this->assertTargetSheet(
                $game,
                $battleId,
                $choice['target'],
                $choice,
                $actorUserId,
                $viewAll,
            );
            $targetId = $rows[$index]['id'] ?? null;
            if (!is_int($targetId)) {
                throw new GameInvalidException('Game wide strike target row is invalid');
            }

            $conditions = GameStrikeTargetRow::conditions($rows[$index]);
            $turn = ($choice['turn'] ?? false) === true;
            if ($turn && (($conditions['flank'] ?? false) !== true || $choice['reaction'] === 'ignore')) {
                throw new GameInvalidException('Game wide strike turn is invalid');
            }

            if ($turn) {
                $conditions = (new GameStrikeConditions())->withTurn($conditions, true);
            }

            $this->actions->answerStrike($targetId, [
                'reaction' => $choice['reaction'],
                'block_item_inventory_id' => $choice['blockItemInventoryId'],
                'block_item_profile_index' => $choice['blockItemProfileIndex'],
                'block_item_rule_code' => $choice['blockItemRuleCode'],
                'expected_sheet_version' => $choice['expectedSheetVersion'],
                'spend_concentration' => $choice['spendConcentration'] ?? 0,
                'conditions' => $conditions,
            ]);
            $rows[$index]['reaction'] = $choice['reaction'];
            $rows[$index]['block_item_inventory_id'] = $choice['blockItemInventoryId'];
            $rows[$index]['block_item_profile_index'] = $choice['blockItemProfileIndex'];
            $rows[$index]['block_item_rule_code'] = $choice['blockItemRuleCode'];
            $rows[$index]['expected_sheet_version'] = $choice['expectedSheetVersion'];
            $rows[$index]['spend_concentration'] = $choice['spendConcentration'] ?? 0;
            $rows[$index]['conditions'] = $conditions;
        }

        if ($requestChoices !== []) {
            $pendingTargets = $this->rows->pendingTargets($rows);
            if ($pendingTargets !== []) {
                return [
                    'battleId' => $battleId,
                    'strikeId' => $strikeId,
                    'version' => $expectedBattleVersion,
                    'closed' => false,
                    'pendingTargets' => $pendingTargets,
                    'targetResults' => [],
                ];
            }
        }

        $waiting = [];
        foreach ($rows as $row) {
            $rowId = is_int($row['id'] ?? null) ? $row['id'] : 0;
            if ($rowId < 1 || isset($resume[$rowId])) {
                continue;
            }

            $awaitings = $this->jsonRow($row, 'resolution')['awaiting'] ?? null;
            if (!is_array($awaitings)) {
                continue;
            }

            foreach ($awaitings as $entry) {
                if (is_array($entry)) {
                    $waiting[] = $entry;
                }
            }
        }

        if ($waiting !== []) {
            return [
                'battleId' => $battleId,
                'strikeId' => $strikeId,
                'version' => $expectedBattleVersion,
                'attackerRoll' => [],
                'targetResults' => [],
                'closed' => false,
                'pendingTargets' => [],
                'awaiting' => $waiting,
            ];
        }

        $slice = $this->rules->data->ruleSlice($game->getSpaceId(), $game->getRulesRevision());
        $hitCode = $this->rules->hitCheck->findHitCode($game->getSpaceId(), $game->getRulesRevision());
        $rows = $this->declaredSpend->spend($game, $slice, $rows);
        $attackerRoll = $this->resumeAttackerRoll($rows, $resume);
        if ($attackerRoll === null) {
            $attackerSupports = [];
            foreach ($rows as $row) {
                array_push($attackerSupports, ...$this->support->advantages($row)['attacker']);
            }

            $attackerRoll = $this->targets->rolls->rollOf(
                $game,
                $open,
                $battle,
                $slice,
                count($rows),
                $targetKeys,
                $attackerSupports,
            );
        }

        $choices = [];
        foreach ($rows as $index => $row) {
            $choices[$index] = $this->rows->answerOfRow($row);
        }

        $refusals = $this->assertSheets(
            $game,
            $battleId,
            $rows,
            $choices,
            $actorUserId,
            $viewAll,
        );
        $choices = $this->resolveChoices($game, $rows, $choices, $refusals);

        $this->targets->begin(
            $battle->getPendingActionEffects(),
            $battle->getConcentrationCycle(),
        );

        $prepared = [];
        $resolved = [];
        $covers = [];
        $pauses = [];
        foreach ($rows as $index => $row) {
            $rowId = is_int($row['id'] ?? null) ? $row['id'] : 0;
            if ($rowId < 1) {
                continue;
            }

            if (isset($resume[$rowId])) {
                $state = $this->resumeState($row, $resume[$rowId]);
                $rows[$index] = $state['row'];
                $choices[$index] = $state['choice'];
                $resolved[$rowId] = $state['resolved'];
                if ($state['cover'] !== null) {
                    $covers[$rowId] = $state['cover'];
                }

                $this->persistRow($rowId, $state['row']);
                continue;
            }

            if (($refusals[$index] ?? null) !== null) {
                continue;
            }

            $prepared[$rowId] = $this->targets->prepare(
                $game,
                $this->rows->targetOpen($open, $this->rows->rowTarget($row)),
                $row,
                $choices[$index],
                $hitCode,
                $attackerRoll,
                is_string($row['profile_type'] ?? null) ? $row['profile_type'] : 'strike',
                false,
                null,
                $battle->getMarks(),
            );
            $cover = $prepared[$rowId]['cover'];
            if (!is_array($cover)) {
                continue;
            }

            $primaryKey = $this->rows->targetKey($this->rows->rowTarget($row));
            $winner = is_array($cover['winner'] ?? null) ? $cover['winner'] : null;
            $winnerKey = $winner === null ? null : $this->rows->targetKey($winner['actor']);
            $covers[$rowId] = $this->cover->info($cover['candidates'], $primaryKey, $winnerKey);
            if ($winner !== null) {
                $resolved[$rowId] = $this->resolvedOf($winner);
                $rows[$index] = $this->rewriteRow($row, $winner);
                $choices[$index] = $resolved[$rowId]['choice'];
                $this->persistRow($rowId, $rows[$index]);
                continue;
            }

            $pauses[$rowId] = [
                'candidates' => $cover['candidates'],
                'primaryKey' => $primaryKey,
                'target' => $this->rows->rowTarget($row),
                'choice' => $choices[$index],
                'prepared' => $prepared[$rowId],
            ];
        }

        if ($pauses !== []) {
            return $this->pause(
                $game,
                $battle,
                $expectedBattleVersion,
                $strikeId,
                $attackerRoll,
                $pauses,
                [
                    'type' => is_string($open['attacker_kind'] ?? null) ? $open['attacker_kind'] : '',
                    'id' => is_int($open['attacker_id'] ?? null) ? $open['attacker_id'] : 0,
                ],
            );
        }

        $damage = $this->rules->hitCheck->isHitAutoFail($attackerRoll['roll'])
            ? $this->amounts->zero()
            : $this->resolution->damage->damageOf($game, $open);

        $penetration = null;
        $penetrationFactory = function () use (&$penetration, $game, $open): DimensionalNumber {
            if (!$penetration instanceof DimensionalNumber) {
                $penetration = $this->resolution->defense->penetrationOf($game, $open);
            }

            return $penetration;
        };

        $results = [];
        foreach ($rows as $index => $row) {
            $rowId = is_int($row['id'] ?? null) ? $row['id'] : 0;
            $state = $resolved[$rowId] ?? null;
            $supports = $this->support->advantages($row);
            $results[] = $this->targets->one(
                $game,
                $open,
                $row,
                $choices[$index],
                $refusals[$index] ?? null,
                $damage,
                is_array($state['attackerRoll'] ?? null) ? $state['attackerRoll'] : $attackerRoll,
                $hitCode,
                $penetrationFactory,
                new GameStrikeWriteScope($actorUserId, $viewAll),
                new GameStrikeTargetOptions(
                    $supports['defender'],
                    $state === null ? ($prepared[$rowId] ?? null) : null,
                    $state,
                    $battle->getMarks(),
                ),
            );
        }

        $this->chat->post(
            $game,
            $rows,
            $choices,
            $results,
            $attackerRoll,
            $open,
            $battle,
            $slice,
            $actorUserId,
            $covers,
        );
        $this->storeReactions($rows, $choices, $results);

        $attackerKind = $open['attacker_kind'] ?? null;
        $attackerId = $open['attacker_id'] ?? null;
        if (!is_string($attackerKind) || !is_int($attackerId)) {
            throw new GameInvalidException('Game wide strike attacker is invalid');
        }

        $actionRuleCode = is_string($open['action_rule_code'] ?? null) ? $open['action_rule_code'] : '';
        $pending = (new GamePendingActionCosts())->complete(
            $slice,
            $this->choices->attackerChoices($game, $open),
            $actionRuleCode,
            $attackerKind,
            $attackerId,
            $this->targets->pendingAfter(),
            ['action-points' => $this->spend->getActionAp($slice, $actionRuleCode, true)],
            $targetKeys,
        );

        $hits = [];
        foreach ($results as $index => $record) {
            $target = is_array($record['target'] ?? null) ? $record['target'] : null;
            if ($target === null || !is_string($target['type'] ?? null) || !is_int($target['id'] ?? null)) {
                continue;
            }

            $injury = is_array($record['injury'] ?? null) ? $record['injury'] : null;
            $hits[] = [
                'targetKey' => $target['type'] . ':' . $target['id'],
                'attackSr' => is_int($record['success'] ?? null) ? $record['success'] : 0,
                'reaction' => is_string($rows[$index]['reaction'] ?? null) ? $rows[$index]['reaction'] : 'ignore',
                'damaged' => $injury !== null && (new DimensionalNumber(
                    (int) ($injury['base'] ?? 0),
                    (int) ($injury['size'] ?? 0),
                ))->toInteger() > 0,
            ];
        }

        $lastStrike = new GameLastStrikeSnapshot();
        $pending = $lastStrike->store(
            $pending,
            $attackerKind,
            $attackerId,
            $actionRuleCode,
            $lastStrike->kindOf($slice, $actionRuleCode),
            $hits,
        );

        $this->actions->close($strikeId);
        $markKeys = $this->targets->markKeys();
        $marksAfter = $markKeys === [] ? null : $this->marks->consume($battle->getMarks(), $markKeys);
        if ($this->targets->tokenUsed()) {
            $this->battles->replaceTracked(
                $battleId,
                $expectedBattleVersion,
                $pending,
                null,
                null,
                null,
                $this->targets->cycleAfter(),
                $marksAfter,
            );
        } else {
            $this->battles->replacePending($battleId, $expectedBattleVersion, $pending, $marksAfter);
        }

        return [
            'battleId' => $battleId,
            'strikeId' => $strikeId,
            'version' => $expectedBattleVersion + 1,
            'attackerRoll' => $attackerRoll,
            'targetResults' => $results,
            'closed' => true,
            'pendingTargets' => [],
        ];
    }

    /**
     * Останавливает широкий удар и ждёт выбора фактической цели по строкам.
     *
     * @param GameRecord $game Игра.
     * @param GameBattleRecord $battle Бой.
     * @param int $expectedBattleVersion Версия боя.
     * @param int $strikeId Действие.
     * @param array<string, mixed> $attackerRoll Бросок атаки.
     * @param array<int, array<string, mixed>> $pauses Строки, ждущие решения.
     * @param array{type: string, id: int} $attacker Атакующий.
     *
     * @return array<string, mixed> Итог.
     */
    private function pause(
        GameRecord $game,
        GameBattleRecord $battle,
        int $expectedBattleVersion,
        int $strikeId,
        array $attackerRoll,
        array $pauses,
        array $attacker,
    ): array {
        $awaitings = [];
        foreach ($pauses as $rowId => $pause) {
            $primaryKey = $pause['primaryKey'];
            $target = is_array($pause['target'] ?? null)
                ? $pause['target']
                : ['type' => 'character', 'id' => 0];
            $reaction = is_array($pause['prepared']['reaction'] ?? null) ? $pause['prepared']['reaction'] : [];
            $spends = is_array($reaction['spends'] ?? null) ? $reaction['spends'] : [];
            $choice = is_array($pause['choice'] ?? null) ? $pause['choice'] : [];
            $candidates = $pause['candidates'];
            if ($spends !== []) {
                $expected = is_int($choice['expectedSheetVersion'] ?? null) ? $choice['expectedSheetVersion'] : 0;
                $expected = $this->sheetWrites->writeResources(
                    $game,
                    is_string($target['type'] ?? null) ? $target['type'] : 'character',
                    is_int($target['id'] ?? null) ? $target['id'] : 0,
                    $expected,
                    $spends,
                );
                $candidates[$primaryKey]['expectedSheetVersion'] = $expected;
            }

            $options = [];
            foreach ($candidates as $key => $candidate) {
                $options[] = ['key' => $key, 'actor' => $candidate['actor']];
            }

            $awaiting = [
                'kind' => 'actual_target',
                'actor' => $attacker,
                'strikeId' => $rowId,
                'rowKey' => $primaryKey,
                'options' => $options,
            ];
            $this->actions->answerStrike($rowId, [
                'reaction' => $choice['reaction'] ?? null,
                'block_item_inventory_id' => $choice['blockItemInventoryId'] ?? null,
                'block_item_profile_index' => $choice['blockItemProfileIndex'] ?? null,
                'block_item_rule_code' => $choice['blockItemRuleCode'] ?? null,
                'expected_sheet_version' => $candidates[$primaryKey]['expectedSheetVersion'] ?? null,
                'resolution' => [
                    'attackerRoll' => $attackerRoll,
                    'primaryKey' => $primaryKey,
                    'candidates' => $candidates,
                    'awaiting' => [$awaiting],
                ],
            ]);
            $awaitings[] = $awaiting;
        }

        $saved = $this->battles->replaceTracked($battle->getId(), $expectedBattleVersion, null, null, null, null);

        return [
            'battleId' => $battle->getId(),
            'strikeId' => $strikeId,
            'version' => $saved->getStateVersion(),
            'attackerRoll' => $attackerRoll,
            'targetResults' => [],
            'closed' => false,
            'pendingTargets' => [],
            'awaiting' => $awaitings,
        ];
    }

    /**
     * Восстанавливает бросок атаки из ожидающих строк.
     *
     * @param list<array<string, mixed>> $rows Строки.
     * @param array<int, array<string, mixed>> $resume Решения.
     *
     * @return array<string, mixed>|null Бросок или null.
     */
    private function resumeAttackerRoll(array $rows, array $resume): ?array
    {
        foreach ($rows as $row) {
            $rowId = $row['id'] ?? null;
            if (!is_int($rowId) || !isset($resume[$rowId])) {
                continue;
            }

            $roll = $this->jsonRow($row, 'resolution')['attackerRoll'] ?? null;
            if (is_array($roll)) {
                return $roll;
            }
        }

        return null;
    }

    /**
     * Восстанавливает состояние строки после выбора фактической цели.
     *
     * @param array<string, mixed> $row Строка.
     * @param array<string, mixed> $resume Решение.
     *
     * @return array{row: array<string, mixed>, choice: array<string, mixed>,
     *               resolved: array<string, mixed>, cover: array<string, mixed>|null} Состояние.
     */
    private function resumeState(array $row, array $resume): array
    {
        $resolution = $this->jsonRow($row, 'resolution');
        $candidates = $resolution['candidates'] ?? null;
        $key = $resume['actualTarget'] ?? null;
        if (!is_array($candidates) || !is_string($key) || !is_array($candidates[$key] ?? null)) {
            throw new GameInvalidException('Game strike window is not open');
        }

        $candidate = $candidates[$key];
        $actor = $candidate['actor'] ?? null;
        $choice = $candidate['choice'] ?? null;
        $attackerRoll = $candidate['attackerRoll'] ?? null;
        if (
            !is_array($actor)
            || !is_string($actor['type'] ?? null)
            || !is_int($actor['id'] ?? null)
            || !is_array($choice)
            || !is_array($attackerRoll)
        ) {
            throw new GameInvalidException('Game strike window choice is invalid');
        }

        $row['target_type'] = $actor['type'];
        $row['target_id'] = $actor['id'];
        $row['reaction'] = $choice['reaction'] ?? null;
        $row['block_item_inventory_id'] = $choice['blockItemInventoryId'] ?? null;
        $row['block_item_profile_index'] = $choice['blockItemProfileIndex'] ?? null;
        $row['block_item_rule_code'] = $choice['blockItemRuleCode'] ?? null;
        $row['expected_sheet_version'] = is_int($candidate['expectedSheetVersion'] ?? null)
            ? $candidate['expectedSheetVersion']
            : 0;

        $primaryKey = is_string($resolution['primaryKey'] ?? null) ? $resolution['primaryKey'] : null;

        return [
            'row' => $row,
            'choice' => [
                'reaction' => $choice['reaction'],
                'blockItemInventoryId' => $choice['blockItemInventoryId'] ?? null,
                'blockItemProfileIndex' => $choice['blockItemProfileIndex'] ?? null,
                'blockItemRuleCode' => $choice['blockItemRuleCode'] ?? null,
                'turn' => false,
                'expectedSheetVersion' => $row['expected_sheet_version'],
            ],
            'resolved' => $this->resolvedOf([
                'actor' => $actor,
                'choice' => $choice,
                'attackerRoll' => $attackerRoll,
                'defenderRoll' => $candidate['defenderRoll'] ?? null,
                'reactionRuleCode' => $candidate['reactionRuleCode'] ?? null,
                'expectedSheetVersion' => $row['expected_sheet_version'],
                'markKeys' => $candidate['markKeys'] ?? [],
            ]),
            'cover' => $primaryKey === null
                ? null
                : $this->cover->info($candidates, $primaryKey, $key),
        ];
    }

    /**
     * Готовая защита прикрывающего для применения без переброса.
     *
     * @param array<string, mixed> $candidate Кандидат.
     *
     * @return array<string, mixed> Защита.
     */
    private function resolvedOf(array $candidate): array
    {
        $choice = is_array($candidate['choice'] ?? null) ? $candidate['choice'] : [];

        return [
            'choice' => [
                'reaction' => $choice['reaction'] ?? 'ignore',
                'blockItemInventoryId' => $choice['blockItemInventoryId'] ?? null,
                'blockItemProfileIndex' => $choice['blockItemProfileIndex'] ?? null,
                'blockItemRuleCode' => $choice['blockItemRuleCode'] ?? null,
                'turn' => false,
                'expectedSheetVersion' => is_int($candidate['expectedSheetVersion'] ?? null)
                    ? $candidate['expectedSheetVersion']
                    : 0,
            ],
            'attackerRoll' => is_array($candidate['attackerRoll'] ?? null) ? $candidate['attackerRoll'] : [],
            'defenderRoll' => is_array($candidate['defenderRoll'] ?? null) ? $candidate['defenderRoll'] : null,
            'reactionRuleCode' => is_string($candidate['reactionRuleCode'] ?? null)
                ? $candidate['reactionRuleCode']
                : null,
            'markKeys' => $this->stringList($candidate['markKeys'] ?? null),
        ];
    }

    /**
     * Список строк из недоверенного значения.
     *
     * @param mixed $raw Значение.
     *
     * @return array<int, string> Строки.
     */
    private function stringList(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }

        $rows = [];
        foreach ($raw as $row) {
            if (is_string($row)) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * Переносит цель строки на прикрывающего.
     *
     * @param array<string, mixed> $row Строка.
     * @param array<string, mixed> $winner Победитель.
     *
     * @return array<string, mixed> Строка.
     */
    private function rewriteRow(array $row, array $winner): array
    {
        $actor = $winner['actor'];
        $choice = $winner['choice'];
        $row['target_type'] = $actor['type'];
        $row['target_id'] = $actor['id'];
        $row['reaction'] = $choice['reaction'] ?? null;
        $row['block_item_inventory_id'] = $choice['blockItemInventoryId'] ?? null;
        $row['block_item_profile_index'] = $choice['blockItemProfileIndex'] ?? null;
        $row['block_item_rule_code'] = $choice['blockItemRuleCode'] ?? null;
        $row['expected_sheet_version'] = is_int($winner['expectedSheetVersion'] ?? null)
            ? $winner['expectedSheetVersion']
            : 0;

        return $row;
    }

    /**
     * Сохраняет перенесённую цель строки.
     *
     * @param int $rowId Строка.
     * @param array<string, mixed> $row Данные.
     *
     * @return void
     */
    private function persistRow(int $rowId, array $row): void
    {
        $this->actions->answerStrike($rowId, [
            'target_type' => $row['target_type'] ?? null,
            'target_id' => $row['target_id'] ?? null,
            'reaction' => $row['reaction'] ?? null,
            'block_item_inventory_id' => $row['block_item_inventory_id'] ?? null,
            'block_item_profile_index' => $row['block_item_profile_index'] ?? null,
            'block_item_rule_code' => $row['block_item_rule_code'] ?? null,
            'expected_sheet_version' => $row['expected_sheet_version'] ?? null,
        ]);
    }

    /**
     * JSON строки.
     *
     * @param array<string, mixed> $row Строка.
     * @param string $key Колонка.
     *
     * @return array<string, mixed> Данные.
     */
    private function jsonRow(array $row, string $key): array
    {
        $raw = $row[$key] ?? null;
        if (is_string($raw)) {
            $raw = json_decode($raw, true);
        }

        return is_array($raw) ? $raw : [];
    }

    /**
     * Проверяет листы всех целей и отдаёт non-CAS отказы.
     *
     * @param GameRecord $game Игра.
     * @param int $battleId Бой.
     * @param list<array<string, mixed>> $rows Цели.
     * @param list<array<string, mixed>> $choices Защиты.
     * @param int $actorUserId Актор.
     * @param bool $viewAll Полный просмотр.
     *
     * @return list<string|null> Отказы.
     */
    private function assertSheets(
        GameRecord $game,
        int $battleId,
        array $rows,
        array $choices,
        int $actorUserId,
        bool $viewAll,
    ): array {
        $refusals = [];
        foreach ($rows as $index => $row) {
            $target = $this->rows->rowTarget($row);
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
     * @param array<string, mixed> $choice Защита.
     * @param int $actorUserId Актор.
     * @param bool $viewAll Полный просмотр.
     *
     * @return string|null Non-CAS код отказа.
     */
    private function assertTargetSheet(
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

        $this->sheets->assertTargetSheet(
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
     */
    private function resolveChoices(
        GameRecord $game,
        array $rows,
        array $choices,
        array $refusals,
    ): array {
        foreach ($rows as $index => $row) {
            if (($refusals[$index] ?? null) !== null || ($choices[$index]['reaction'] ?? null) !== 'block') {
                continue;
            }

            $target = $this->rows->rowTarget($row);
            $inventoryId = $choices[$index]['blockItemInventoryId'] ?? null;
            if (!is_int($inventoryId)) {
                throw new GameInvalidException('Game wide strike block item is invalid');
            }

            $choices[$index]['blockItemRuleCode'] = $this->rules->equipment->getBlockItemRuleCode(
                $this->choices->targetChoices($game, $target),
                $inventoryId,
            );
        }

        return $choices;
    }

    /**
     * Код отказа или null.
     *
     * @param GameRecord $game Игра.
     * @param int $battleId Бой.
     * @param array{type: string, id: int} $target Цель.
     * @param array<string, mixed> $choice Защита.
     *
     * @return string|null Код.
     */
    private function refusal(GameRecord $game, int $battleId, array $target, array $choice): ?string
    {
        $code = null;
        try {
            $this->rows->assertDefender($battleId, $target);
            $this->choices->assertReaction($game, $target, $choice);
        } catch (GameNotFoundException) {
            $code = 'GAME_NOT_FOUND';
        } catch (GameInvalidException) {
            $code = 'GAME_INVALID';
        }

        return $code;
    }

    /**
     * Пишет реакции принятых целей.
     *
     * @param list<array<string, mixed>> $rows Цели.
     * @param list<array<string, mixed>> $choices Защиты.
     * @param list<array<string, mixed>> $results Итоги.
     *
     * @return void
     */
    private function storeReactions(array $rows, array $choices, array $results): void
    {
        foreach ($results as $index => $result) {
            if (($result['code'] ?? null) !== null) {
                continue;
            }

            $targetId = $rows[$index]['id'] ?? null;
            if (!is_int($targetId)) {
                throw new GameInvalidException('Game wide strike target row is invalid');
            }

            $this->actions->answerStrike($targetId, [
                'reaction' => $choices[$index]['reaction'],
                'block_item_inventory_id' => $choices[$index]['blockItemInventoryId'],
                'block_item_profile_index' => $choices[$index]['blockItemProfileIndex'],
                'block_item_rule_code' => $choices[$index]['blockItemRuleCode'],
            ]);
        }
    }
}
