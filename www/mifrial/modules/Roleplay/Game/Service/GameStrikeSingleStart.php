<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Dto\GameStrikeSingleRun;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;

/**
 * Защита удара и старт манёвра.
 */
final class GameStrikeSingleStart
{
    /**
     * Создаёт старт удара.
     *
     * @param GameStrikeManeuver $maneuver Манёвр.
     * @param GameStrikeTargetChoices $choices Choices целей.
     * @param GameStrikeRules $rules Правила.
     *
     * @return void
     */
    public function __construct(
        private readonly GameStrikeManeuver $maneuver,
        private readonly GameStrikeTargetChoices $choices,
        private readonly GameStrikeRules $rules,
    ) {
    }

    /**
     * Выбирает защиту и запускает манёвр.
     *
     * @param GameStrikeSingleRun $run Прогон.
     *
     * @return array<string, mixed>|null Ранний итог окна манёвра.
     *
     * @throws GameInvalidException Если защита, поворот или толчок битые.
     */
    public function start(GameStrikeSingleRun $run): ?array
    {
        $run->choice = $this->choice($run);
        $early = $this->maneuver($run);
        if ($early !== null) {
            return $early;
        }

        $this->guard($run);

        return null;
    }

    /**
     * Защита строки.
     *
     * @param GameStrikeSingleRun $run Прогон.
     *
     * @return array<string, mixed> Выбор.
     *
     * @throws GameInvalidException Если блок без предмета.
     */
    private function choice(GameStrikeSingleRun $run): array
    {
        if ($run->maneuverResume !== null) {
            return $this->rowChoice($run->row);
        }

        if ($run->resumeState === null) {
            $this->choices->assertReaction($run->input->game, $run->target, $run->input->requestChoice);

            return $this->authoritativeChoice($run);
        }

        $choice = $run->resumeState['choice'] ?? null;

        return is_array($choice) ? $choice : [];
    }

    /**
     * Защита из строки после раскрытия манёвра.
     *
     * @param array<string, mixed> $row Строка удара.
     *
     * @return array<string, mixed> Выбор.
     */
    private function rowChoice(array $row): array
    {
        return [
            'reaction' => is_string($row['reaction'] ?? null) ? $row['reaction'] : 'ignore',
            'blockItemInventoryId' => is_int($row['block_item_inventory_id'] ?? null)
                ? $row['block_item_inventory_id']
                : null,
            'blockItemProfileIndex' => is_int($row['block_item_profile_index'] ?? null)
                ? $row['block_item_profile_index']
                : null,
            'blockItemRuleCode' => is_string($row['block_item_rule_code'] ?? null)
                ? $row['block_item_rule_code']
                : null,
            'turn' => false,
            'expectedSheetVersion' => is_int($row['expected_sheet_version'] ?? null)
                ? $row['expected_sheet_version']
                : 0,
        ];
    }

    /**
     * Подменяет код блока кодом выбранного inventory instance.
     *
     * @param GameStrikeSingleRun $run Прогон.
     *
     * @return array<string, mixed> Authoritative выбор.
     *
     * @throws GameInvalidException Если предмет блока битый.
     */
    private function authoritativeChoice(GameStrikeSingleRun $run): array
    {
        $choice = $run->input->requestChoice;
        if ($choice['reaction'] !== 'block') {
            return $choice;
        }

        $inventoryId = $choice['blockItemInventoryId'] ?? null;
        if (!is_int($inventoryId)) {
            throw new GameInvalidException('Game strike block item is invalid');
        }

        $choice['blockItemRuleCode'] = $this->rules->equipment->getBlockItemRuleCode(
            $this->choices->targetChoices($run->input->game, $run->target),
            $inventoryId,
        );

        return $choice;
    }

    /**
     * Раскрытие манёвра и его ранний итог.
     *
     * @param GameStrikeSingleRun $run Прогон.
     *
     * @return array<string, mixed>|null Ранний итог.
     */
    private function maneuver(GameStrikeSingleRun $run): ?array
    {
        $maneuver = $this->maneuver->maneuverOf($run->row);
        $done = ($this->jsonRow($run->row, 'resolution')['phase'] ?? null) === 'done';
        $run->maneuver = $maneuver;
        $run->maneuverDone = $done;
        $run->maneuverFail = $run->maneuverResume !== null || $done;
        $run->maneuverMarks = $this->subjects($run);
        if ($run->maneuverResume !== null || $done || $run->resumeState !== null || $maneuver === []) {
            return null;
        }

        $begin = $this->maneuver->begin(
            $run->input->game,
            $run->battle,
            $run->row,
            ['type' => $run->attackerKind, 'id' => $run->attackerId],
            $run->choice,
            $run->input->expectedBattleVersion,
            $run->input->expectedSheetVersion,
        );
        if ($begin['active'] === true && is_array($begin['result'] ?? null)) {
            return $begin['result'];
        }

        $run->maneuverFail = true;

        return null;
    }

    /**
     * Защитники, которым манёвр ставит метки.
     *
     * @param GameStrikeSingleRun $run Прогон.
     *
     * @return list<array{type: string, id: int}> Защитники.
     */
    private function subjects(GameStrikeSingleRun $run): array
    {
        $subjects = $run->maneuverResume['subjects'] ?? null;
        if (!is_array($subjects)) {
            return [];
        }

        return array_values(array_filter(
            $subjects,
            static fn (mixed $subject): bool => is_array($subject)
                && is_string($subject['type'] ?? null)
                && is_int($subject['id'] ?? null),
        ));
    }

    /**
     * Проверяет поворот и толчок, обновляет условия.
     *
     * @param GameStrikeSingleRun $run Прогон.
     *
     * @return void
     *
     * @throws GameInvalidException Если поворот или толчок битые.
     */
    private function guard(GameStrikeSingleRun $run): void
    {
        $turn = ($run->choice['turn'] ?? false) === true;
        $conditions = is_array($run->open['conditions'] ?? null) ? $run->open['conditions'] : [];
        if ($turn && (($conditions['flank'] ?? false) !== true || $run->choice['reaction'] === 'ignore')) {
            throw new GameInvalidException('Game strike turn is invalid');
        }

        $this->assertPush($run, $turn);
        if ($turn) {
            $conditions = (new GameStrikeConditions())->withTurn($conditions, true);
        }

        $run->turn = $turn;
        $run->conditions = $conditions;
    }

    /**
     * Проверяет реакцию толчка.
     *
     * @param GameStrikeSingleRun $run Прогон.
     * @param bool $turn Поворот.
     *
     * @return void
     *
     * @throws GameInvalidException Если реакция толчка запрещена.
     */
    private function assertPush(GameStrikeSingleRun $run, bool $turn): void
    {
        if ($run->isPush && ($turn || $run->sequential || $run->packets > 1)) {
            throw new GameInvalidException('Game push reaction is invalid');
        }

        if (!$run->isPush || $run->choice['reaction'] !== 'block') {
            return;
        }

        $blockItem = is_string($run->choice['blockItemRuleCode'] ?? null) ? $run->choice['blockItemRuleCode'] : '';
        $game = $run->input->game;
        $this->rules->checks->assertPushBlock(
            $game->getSpaceId(),
            $game->getRulesRevision(),
            $run->actionRuleCode,
            $blockItem,
        );
    }

    /**
     * JSON-объект строки удара.
     *
     * @param array<string, mixed> $row Строка удара.
     * @param string $key Колонка.
     *
     * @return array<string, mixed> Объект.
     */
    private function jsonRow(array $row, string $key): array
    {
        $raw = $row[$key] ?? null;
        if (is_string($raw)) {
            $raw = json_decode($raw, true);
        }

        return is_array($raw) ? $raw : [];
    }
}
