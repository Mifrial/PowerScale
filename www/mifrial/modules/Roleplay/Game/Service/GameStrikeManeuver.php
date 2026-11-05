<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Dto\GameBattleRecord;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Dto\GameCheckThrowOverride;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Repository\GameActionRepository;
use Mifrial\Roleplay\Game\Repository\GameBattleRepository;

/**
 * Раскрытие и совместная проверка Обманного манёвра.
 *
 * Атака не бросает попадание: после ответов защитников открывается окно
 * `maneuver_reveal`, каждый защитник выбирает пару {проверка, характеристика},
 * затем все броски публикуются разом. Строго победивший может отказаться от
 * реакции; остальные получают метку. Применение удара после окна — в close.
 */
final class GameStrikeManeuver
{
    /**
     * Создаёт сценарий раскрытия.
     *
     * @param GameActionRepository $actions Строки удара.
     * @param GameBattleRepository $battles Бои.
     * @param GameStrikeSheets $sheets Листы.
     *
     * @return void
     */
    public function __construct(
        private readonly GameActionRepository $actions,
        private readonly GameBattleRepository $battles,
        private readonly GameStrikeSheets $sheets,
    ) {
    }

    /**
     * Открывает окно раскрытия манёвра.
     *
     * @param GameRecord $game Игра.
     * @param GameBattleRecord $battle Бой.
     * @param array<string, mixed> $row Строка удара.
     * @param array{type: string, id: int} $attacker Атакующий.
     * @param array<string, mixed> $choice Защита цели.
     * @param int $expectedBattleVersion Версия боя.
     * @param int $expectedSheetVersion Версия листа цели.
     *
     * @return array{active: bool, result?: array<string, mixed>} Итог.
     *
     * @throws GameInvalidException Если строка или манёвр битые.
     */
    public function begin(
        GameRecord $game,
        GameBattleRecord $battle,
        array $row,
        array $attacker,
        array $choice,
        int $expectedBattleVersion,
        int $expectedSheetVersion,
    ): array {
        $maneuver = $this->maneuverOf($row);
        if ($maneuver === []) {
            return ['active' => false];
        }

        $targets = $this->targetsOf($game, $row, $choice);
        if ($targets === []) {
            return ['active' => false];
        }

        $resolution = [
            'maneuver' => $maneuver,
            'attacker' => $attacker,
            'phase' => 'check',
            'targets' => $targets,
            'awaiting' => $this->awaiting($targets, 'check'),
        ];
        $strikeId = is_int($row['id'] ?? null) ? $row['id'] : 0;
        if ($strikeId < 1) {
            throw new GameInvalidException('Game strike row is invalid');
        }

        $this->actions->answerStrike($strikeId, [
            'reaction' => $choice['reaction'],
            'block_item_inventory_id' => $choice['blockItemInventoryId'] ?? null,
            'block_item_profile_index' => $choice['blockItemProfileIndex'] ?? null,
            'block_item_rule_code' => $choice['blockItemRuleCode'] ?? null,
            'expected_sheet_version' => $expectedSheetVersion,
            'resolution' => $resolution,
        ]);
        $saved = $this->battles->replaceTracked(
            $battle->getId(),
            $expectedBattleVersion,
            null,
            null,
            null,
            null,
        );

        return [
            'active' => true,
            'result' => [
                'battleId' => $battle->getId(),
                'strikeId' => $strikeId,
                'version' => $saved->getStateVersion(),
                'closed' => false,
                'maneuver' => $this->publicManeuver($resolution),
                'awaiting' => $this->awaiting($targets, 'check'),
            ],
        ];
    }

    /**
     * Принимает выбор характеристики или решения об отказе.
     *
     * @param GameRecord $game Игра.
     * @param GameBattleRecord $battle Бой.
     * @param array<string, mixed> $row Строка удара.
     * @param array<string, mixed> $window Решение окна.
     * @param int $expectedBattleVersion Версия боя.
     * @param GameCheckRoll $checkRoll Бросок совместной проверки.
     *
     * @return array{closed: bool, version: int, awaiting?: array<int, array<string, mixed>>,
     *               maneuver?: array<string, mixed>, finish?: array<string, mixed>} Итог.
     *
     * @throws GameInvalidException Если окно или выбор чужие.
     */
    public function resolve(
        GameRecord $game,
        GameBattleRecord $battle,
        array $row,
        array $window,
        int $expectedBattleVersion,
        GameCheckRoll $checkRoll,
    ): array {
        $resolution = $this->resolutionOf($row);
        $targets = is_array($resolution['targets'] ?? null) ? $resolution['targets'] : [];
        $phase = is_string($resolution['phase'] ?? null) ? $resolution['phase'] : '';
        if ($targets === [] || !in_array($phase, ['check', 'decision'], true)) {
            throw new GameInvalidException('Game maneuver window is not open');
        }

        $actor = $this->actorOf($window['actor'] ?? null);
        $key = $actor['type'] . ':' . $actor['id'];
        if (!is_array($targets[$key] ?? null)) {
            throw new GameInvalidException('Game maneuver actor is invalid');
        }

        if ($phase === 'check') {
            $choice = $this->checkChoice($window, $targets[$key]['options'] ?? []);
            $targets[$key] = ['check' => $choice] + $targets[$key];
            if ($this->pendingCheck($targets) !== []) {
                return $this->persist($battle, $row, $resolution, $targets, 'check', $expectedBattleVersion);
            }

            $this->roll($game, $resolution, $targets, $checkRoll);
            $resolution['phase'] = 'decision';
        } else {
            $decision = $window['decision'] ?? null;
            if (!in_array($decision, ['keep', 'refuse'], true)) {
                throw new GameInvalidException('Game maneuver decision is invalid');
            }

            $targets[$key] = ['decision' => $decision] + $targets[$key];
        }

        $resolution['targets'] = $targets;
        if ($this->pendingDecisions($targets) !== []) {
            $resolution['phase'] = 'decision';

            return $this->persist($battle, $row, $resolution, $targets, 'decision', $expectedBattleVersion);
        }

        $resolution['phase'] = 'done';
        $resolution['awaiting'] = [];
        $row = $this->withDecisions($row, $targets);
        $this->save($row, $resolution);
        $saved = $this->battles->replaceTracked(
            $battle->getId(),
            $expectedBattleVersion,
            null,
            null,
            null,
            null,
        );

        return [
            'closed' => false,
            'version' => $saved->getStateVersion(),
            'maneuver' => $this->publicManeuver($resolution),
            'finish' => [
                'subjects' => $this->keepSubjects($targets),
                'marks' => is_array($resolution['maneuver']['marks'] ?? null)
                    ? $resolution['maneuver']['marks']
                    : [],
            ],
        ];
    }

    /**
     * Манёвр из строки удара.
     *
     * @param array<string, mixed> $row Строка.
     *
     * @return array<string, mixed> Описание или пустой список.
     */
    public function maneuverOf(array $row): array
    {
        $raw = $row['maneuver'] ?? null;
        if (is_string($raw)) {
            $raw = json_decode($raw, true);
        }

        return is_array($raw) && !array_is_list($raw) ? $raw : [];
    }

    /**
     * Собирает защитников, которые отреагировали.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $row Строка.
     * @param array<string, mixed> $choice Защита цели.
     *
     * @return array<string, array<string, mixed>> Защитники по ключу.
     *
     * @throws GameInvalidException Если строка битая.
     */
    private function targetsOf(GameRecord $game, array $row, array $choice): array
    {
        $maneuver = $this->maneuverOf($row);
        $options = is_array($maneuver['target'] ?? null) ? $maneuver['target'] : [];
        $targets = [];
        $target = [
            'type' => is_string($row['target_type'] ?? null) ? $row['target_type'] : '',
            'id' => is_int($row['target_id'] ?? null) ? $row['target_id'] : 0,
        ];
        if (
            in_array($target['type'], ['character', 'npc'], true)
            && $target['id'] > 0
            && ($choice['reaction'] ?? 'ignore') !== 'ignore'
        ) {
            $targets[$target['type'] . ':' . $target['id']] = [
                'actor' => $target,
                'reaction' => $choice['reaction'],
                'choice' => $choice,
                'options' => $options,
                'check' => null,
                'roll' => null,
                'won' => null,
                'decision' => null,
            ];
        }

        foreach ($this->jsonRows($row, 'coverers') as $entry) {
            $actor = $entry['actor'] ?? null;
            if (
                !is_array($actor)
                || !is_string($actor['type'] ?? null)
                || !is_int($actor['id'] ?? null)
                || !in_array($actor['type'], ['character', 'npc'], true)
                || ($entry['reaction'] ?? 'ignore') === 'ignore'
            ) {
                continue;
            }

            $targets[$actor['type'] . ':' . $actor['id']] = [
                'actor' => $actor,
                'reaction' => $entry['reaction'],
                'choice' => [
                    'reaction' => $entry['reaction'],
                    'blockItemInventoryId' => $entry['blockItemInventoryId'] ?? null,
                    'blockItemProfileIndex' => $entry['blockItemProfileIndex'] ?? null,
                    'blockItemRuleCode' => $entry['blockItemRuleCode'] ?? null,
                ],
                'options' => $options,
                'check' => null,
                'roll' => null,
                'won' => null,
                'decision' => null,
            ];
        }

        return $targets;
    }

    /**
     * Проверяет выбранную пару.
     *
     * @param array<string, mixed> $window Решение.
     * @param mixed $options Разрешённые пары.
     *
     * @return array{check: string, characteristic: string} Пара.
     *
     * @throws GameInvalidException Если пара чужая.
     */
    private function checkChoice(array $window, mixed $options): array
    {
        $check = $window['check'] ?? null;
        $characteristic = $window['characteristic'] ?? null;
        if (!is_string($check) || !is_string($characteristic) || !is_array($options)) {
            throw new GameInvalidException('Game maneuver check is invalid');
        }

        foreach ($options as $option) {
            if (
                is_array($option)
                && ($option['check'] ?? null) === $check
                && ($option['characteristic'] ?? null) === $characteristic
            ) {
                return ['check' => $check, 'characteristic' => $characteristic];
            }
        }

        throw new GameInvalidException('Game maneuver check is invalid');
    }

    /**
     * Бросает совместные проверки всех защитников.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $resolution Резолюция.
     * @param array<string, array<string, mixed>> $targets Защитники.
     * @param GameCheckRoll $checkRoll Бросок.
     *
     * @return void
     *
     * @throws GameInvalidException Если проверка чужая.
     */
    private function roll(
        GameRecord $game,
        array $resolution,
        array &$targets,
        GameCheckRoll $checkRoll,
    ): void {
        $maneuver = is_array($resolution['maneuver'] ?? null) ? $resolution['maneuver'] : [];
        $attackerChecks = is_array($maneuver['attacker'] ?? null) ? $maneuver['attacker'] : [];
        $attackerCheck = is_array($attackerChecks[0] ?? null) ? $attackerChecks[0] : null;
        $attacker = is_array($resolution['attacker'] ?? null) ? $resolution['attacker'] : null;
        if ($attackerCheck === null || $attacker === null) {
            throw new GameInvalidException('Game maneuver check is invalid');
        }

        $attackerSheet = $this->sheets->getTargetSheet($game, $attacker);
        foreach ($targets as $key => $target) {
            $check = is_array($target['check'] ?? null) ? $target['check'] : null;
            if ($check === null) {
                continue;
            }

            $rolls = $checkRoll->throwJointCheckSides(
                $game->getSpaceId(),
                $game->getRulesRevision(),
                (string) $attackerCheck['check'],
                $attackerSheet,
                new GameCheckThrowOverride((string) $attackerCheck['characteristic']),
                (string) $check['check'],
                $this->sheets->getTargetSheet($game, $target['actor']),
                new GameCheckThrowOverride((string) $check['characteristic']),
            );
            $attackerSuccesses = (int) ($rolls['initiator']['roll']['base'] ?? 0);
            $targetSuccesses = (int) ($rolls['opponent']['roll']['base'] ?? 0);
            $won = $targetSuccesses > $attackerSuccesses;
            $targets[$key] = [
                'roll' => $rolls['opponent'],
                'won' => $won,
                'decision' => $won ? null : 'keep',
            ] + $target;
        }
    }

    /**
     * Пишет промежуточное состояние окна.
     *
     * @param GameBattleRecord $battle Бой.
     * @param array<string, mixed> $row Строка.
     * @param array<string, mixed> $resolution Резолюция.
     * @param array<string, array<string, mixed>> $targets Защитники.
     * @param string $phase Фаза.
     * @param int $expectedBattleVersion Версия.
     *
     * @return array{closed: bool, version: int, awaiting: array<int, array<string, mixed>>,
     *               maneuver: array<string, mixed>} Итог.
     */
    private function persist(
        GameBattleRecord $battle,
        array $row,
        array $resolution,
        array $targets,
        string $phase,
        int $expectedBattleVersion,
    ): array {
        $resolution['targets'] = $targets;
        $resolution['phase'] = $phase;
        $resolution['awaiting'] = $this->awaiting($targets, $phase);
        $this->save($row, $resolution);
        $saved = $this->battles->replaceTracked(
            $battle->getId(),
            $expectedBattleVersion,
            null,
            null,
            null,
            null,
        );

        return [
            'closed' => false,
            'version' => $saved->getStateVersion(),
            'maneuver' => $this->publicManeuver($resolution),
            'awaiting' => $resolution['awaiting'],
        ];
    }

    /**
     * Сохраняет резолюцию в строку.
     *
     * @param array<string, mixed> $row Строка.
     * @param array<string, mixed> $resolution Резолюция.
     *
     * @return void
     *
     * @throws GameInvalidException Если id строки нет.
     */
    private function save(array $row, array $resolution): void
    {
        $strikeId = is_int($row['id'] ?? null) ? $row['id'] : 0;
        if ($strikeId < 1) {
            throw new GameInvalidException('Game strike row is invalid');
        }

        $fields = ['resolution' => $resolution];
        if (array_key_exists('reaction', $row)) {
            $fields['reaction'] = $row['reaction'];
        }

        if (array_key_exists('coverers', $row)) {
            $fields['coverers'] = $row['coverers'];
        }

        $this->actions->answerStrike($strikeId, $fields);
    }

    /**
     * Убирает отказавшиеся реакции из строки.
     *
     * @param array<string, mixed> $row Строка.
     * @param array<string, array<string, mixed>> $targets Защитники.
     *
     * @return array<string, mixed> Строка.
     */
    private function withDecisions(array $row, array $targets): array
    {
        $targetKey = (is_string($row['target_type'] ?? null) ? $row['target_type'] : '')
            . ':' . (is_int($row['target_id'] ?? null) ? $row['target_id'] : 0);
        if (($targets[$targetKey]['decision'] ?? 'keep') === 'refuse') {
            $row['reaction'] = 'ignore';
        }

        $coverers = [];
        foreach ($this->jsonRows($row, 'coverers') as $entry) {
            $actor = $entry['actor'] ?? null;
            if (!is_array($actor) || !is_string($actor['type'] ?? null) || !is_int($actor['id'] ?? null)) {
                continue;
            }

            $key = $actor['type'] . ':' . $actor['id'];
            if (($targets[$key]['decision'] ?? 'keep') === 'refuse') {
                continue;
            }

            $coverers[] = $entry;
        }

        $row['coverers'] = $coverers;

        return $row;
    }

    /**
     * Открытые выборы характеристики.
     *
     * @param array<string, array<string, mixed>> $targets Защитники.
     *
     * @return array<int, array<string, mixed>> Ожидания.
     */
    private function pendingCheck(array $targets): array
    {
        return $this->awaiting($targets, 'check');
    }

    /**
     * Открытые решения победителей.
     *
     * @param array<string, array<string, mixed>> $targets Защитники.
     *
     * @return array<int, array<string, mixed>> Ожидания.
     */
    private function pendingDecisions(array $targets): array
    {
        return $this->awaiting($targets, 'decision');
    }

    /**
     * Ожидания фазы.
     *
     * @param array<string, array<string, mixed>> $targets Защитники.
     * @param string $phase Фаза.
     *
     * @return array<int, array<string, mixed>> Ожидания.
     */
    private function awaiting(array $targets, string $phase): array
    {
        $rows = [];
        foreach ($targets as $target) {
            if ($phase === 'check') {
                if (is_array($target['check'] ?? null)) {
                    continue;
                }

                $rows[] = [
                    'kind' => 'maneuver_reveal',
                    'phase' => 'check',
                    'actor' => $target['actor'],
                    'options' => $target['options'],
                ];
                continue;
            }

            if ($phase === 'decision' && ($target['won'] ?? false) === true && ($target['decision'] ?? null) === null) {
                $rows[] = [
                    'kind' => 'maneuver_reveal',
                    'phase' => 'decision',
                    'actor' => $target['actor'],
                    'roll' => $target['roll'],
                    'options' => [
                        ['key' => 'keep'],
                        ['key' => 'refuse'],
                    ],
                ];
            }
        }

        return $rows;
    }

    /**
     * Публичное описание раскрытого манёвра.
     *
     * @param array<string, mixed> $resolution Резолюция.
     *
     * @return array<string, mixed> Описание.
     */
    private function publicManeuver(array $resolution): array
    {
        $targets = is_array($resolution['targets'] ?? null) ? $resolution['targets'] : [];
        $reveal = ($resolution['phase'] ?? 'check') !== 'check';
        $rows = [];
        foreach ($targets as $key => $target) {
            $rows[$key] = [
                'actor' => $target['actor'] ?? null,
                'check' => $reveal ? ($target['check'] ?? null) : null,
                'roll' => $reveal ? ($target['roll'] ?? null) : null,
                'won' => $reveal ? ($target['won'] ?? null) : null,
                'decision' => $target['decision'] ?? null,
            ];
        }

        return [
            'ruleCode' => is_string($resolution['maneuver']['ruleCode'] ?? null)
                ? $resolution['maneuver']['ruleCode']
                : '',
            'phase' => is_string($resolution['phase'] ?? null) ? $resolution['phase'] : '',
            'attacker' => is_array($resolution['attacker'] ?? null) ? $resolution['attacker'] : null,
            'targets' => $rows,
        ];
    }

    /**
     * Ключи защитников, сохранивших реакцию.
     *
     * @param array<string, array<string, mixed>> $targets Защитники.
     *
     * @return array<int, array{type: string, id: int}> Пары.
     */
    private function keepSubjects(array $targets): array
    {
        $subjects = [];
        foreach ($targets as $target) {
            if (($target['decision'] ?? 'keep') === 'refuse') {
                continue;
            }

            $actor = $target['actor'] ?? null;
            if (is_array($actor) && is_string($actor['type'] ?? null) && is_int($actor['id'] ?? null)) {
                $subjects[] = ['type' => $actor['type'], 'id' => $actor['id']];
            }
        }

        return $subjects;
    }

    /**
     * Резолюция строки.
     *
     * @param array<string, mixed> $row Строка.
     *
     * @return array<string, mixed> Резолюция.
     */
    private function resolutionOf(array $row): array
    {
        $raw = $row['resolution'] ?? null;
        if (is_string($raw)) {
            $raw = json_decode($raw, true);
        }

        return is_array($raw) ? $raw : [];
    }

    /**
     * Список JSON-записей строки.
     *
     * @param array<string, mixed> $row Строка.
     * @param string $key Колонка.
     *
     * @return array<int, array<string, mixed>> Записи.
     */
    private function jsonRows(array $row, string $key): array
    {
        $raw = $row[$key] ?? null;
        if (is_string($raw)) {
            $raw = json_decode($raw, true);
        }

        if (!is_array($raw)) {
            return [];
        }

        return array_values(array_filter($raw, static fn (mixed $entry): bool => is_array($entry)));
    }

    /**
     * Пара актора.
     *
     * @param mixed $raw Значение.
     *
     * @return array{type: string, id: int} Пара.
     *
     * @throws GameInvalidException Если форма чужая.
     */
    private function actorOf(mixed $raw): array
    {
        if (
            !is_array($raw)
            || !is_string($raw['type'] ?? null)
            || !is_int($raw['id'] ?? null)
            || !in_array($raw['type'], ['character', 'npc'], true)
            || $raw['id'] < 1
        ) {
            throw new GameInvalidException('Game maneuver actor is invalid');
        }

        return ['type' => $raw['type'], 'id' => $raw['id']];
    }
}
