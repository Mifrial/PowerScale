<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Dto\GameStrikeWideRun;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Repository\GameActionRepository;

/**
 * Приём ответов целей широкого удара.
 */
final class GameStrikeWideRequest
{
    /**
     * Создаёт приём ответов.
     *
     * @param GameStrikeCloseRows $rows Строки удара.
     * @param GameStrikeAccess $access Права ответа.
     * @param GameActionRepository $actions Действия и цели.
     * @param GameStrikeWideChecks $checks Проверки целей.
     * @param GameStrikeWideResume $resume Возобновление строк.
     *
     * @return void
     */
    public function __construct(
        private readonly GameStrikeCloseRows $rows,
        private readonly GameStrikeAccess $access,
        private readonly GameActionRepository $actions,
        private readonly GameStrikeWideChecks $checks,
        private readonly GameStrikeWideResume $resume,
    ) {
    }

    /**
     * Принимает ответы и возвращает строки удара или ранний итог ожидания.
     *
     * @param GameStrikeWideRun $run Контекст прогона.
     * @param list<array<string, mixed>> $requestChoices Ответы целей.
     *
     * @return array{open: array<string, mixed>, rows: list<array<string, mixed>>,
     *               targetKeys: list<string>, early: array<string, mixed>|null} Состояние.
     *
     * @throws GameInvalidException Если удар не открыт или цель чужая.
     */
    public function accept(GameStrikeWideRun $run, array $requestChoices): array
    {
        $battleId = $run->battle->getId();
        $open = $this->rows->requireOpen($battleId, 'wide_strike');
        $strikeId = $open['id'] ?? null;
        if (!is_int($strikeId)) {
            throw new GameInvalidException('Game wide strike is not open');
        }

        $rows = is_array($open['strikes'] ?? null) ? $open['strikes'] : $this->actions->getStrikes($strikeId);
        $indexByTarget = $this->index($rows);
        $rows = $this->acceptChoices($run, $rows, $indexByTarget, $requestChoices);

        return [
            'open' => $open,
            'rows' => $rows,
            'targetKeys' => array_keys($indexByTarget),
            'early' => $this->pending($run, $open, $rows, $requestChoices) ?? $this->waiting($run, $open, $rows),
        ];
    }

    /**
     * Индексы строк по ключу цели.
     *
     * @param list<array<string, mixed>> $rows Строки.
     *
     * @return array<string, int> Индексы.
     */
    private function index(array $rows): array
    {
        $indexByTarget = [];
        foreach ($rows as $index => $row) {
            $indexByTarget[$this->rows->targetKey($this->rows->rowTarget($row))] = $index;
        }

        return $indexByTarget;
    }

    /**
     * Записывает ответы в строки.
     *
     * @param GameStrikeWideRun $run Контекст прогона.
     * @param list<array<string, mixed>> $rows Строки.
     * @param array<string, int> $indexByTarget Индексы.
     * @param list<array<string, mixed>> $requestChoices Ответы.
     *
     * @return list<array<string, mixed>> Строки.
     *
     * @throws GameInvalidException Если цель ответа не найдена.
     */
    private function acceptChoices(
        GameStrikeWideRun $run,
        array $rows,
        array $indexByTarget,
        array $requestChoices,
    ): array {
        foreach ($requestChoices as $choice) {
            $index = $indexByTarget[$this->rows->targetKey($choice['target'])] ?? null;
            if (!is_int($index)) {
                throw new GameInvalidException('Game wide strike target is invalid');
            }

            $rows[$index] = $this->acceptChoice($run, $rows[$index], $choice);
        }

        return $rows;
    }

    /**
     * Принимает ответ одной цели.
     *
     * @param GameStrikeWideRun $run Контекст прогона.
     * @param array<string, mixed> $row Строка.
     * @param array<string, mixed> $choice Ответ.
     *
     * @return array<string, mixed> Строка.
     *
     * @throws GameInvalidException Если цель или поворот битые.
     */
    private function acceptChoice(GameStrikeWideRun $run, array $row, array $choice): array
    {
        $target = $choice['target'];
        $this->access->assertTarget($run->game, $run->scope->actorUserId, $run->editAll, $target);
        $this->checks->assertSheet(
            $run->game,
            $run->battle->getId(),
            $target,
            $choice,
            $run->scope->actorUserId,
            $run->scope->viewAll,
        );

        $targetId = $row['id'] ?? null;
        if (!is_int($targetId)) {
            throw new GameInvalidException('Game wide strike target row is invalid');
        }

        $conditions = $this->conditions($row, $choice);
        $this->answer($targetId, $choice, $conditions);

        return $this->marked($row, $choice, $conditions);
    }

    /**
     * Условия строки с учётом поворота.
     *
     * @param array<string, mixed> $row Строка.
     * @param array<string, mixed> $choice Ответ.
     *
     * @return array<string, mixed> Условия.
     *
     * @throws GameInvalidException Если поворот заявлен без фланга.
     */
    private function conditions(array $row, array $choice): array
    {
        $conditions = GameStrikeTargetRow::conditions($row);
        $turn = ($choice['turn'] ?? false) === true;
        if ($turn && (($conditions['flank'] ?? false) !== true || $choice['reaction'] === 'ignore')) {
            throw new GameInvalidException('Game wide strike turn is invalid');
        }

        return $turn ? (new GameStrikeConditions())->withTurn($conditions, true) : $conditions;
    }

    /**
     * Записывает ответ в строку действия.
     *
     * @param int $targetId Строка цели.
     * @param array<string, mixed> $choice Ответ.
     * @param array<string, mixed> $conditions Условия.
     *
     * @return void
     */
    private function answer(int $targetId, array $choice, array $conditions): void
    {
        $this->actions->answerStrike($targetId, [
            'reaction' => $choice['reaction'],
            'block_item_inventory_id' => $choice['blockItemInventoryId'],
            'block_item_profile_index' => $choice['blockItemProfileIndex'],
            'block_item_rule_code' => $choice['blockItemRuleCode'],
            'expected_sheet_version' => $choice['expectedSheetVersion'],
            'spend_concentration' => $choice['spendConcentration'] ?? 0,
            'conditions' => $conditions,
        ]);
    }

    /**
     * Переносит ответ в строку удара.
     *
     * @param array<string, mixed> $row Строка.
     * @param array<string, mixed> $choice Ответ.
     * @param array<string, mixed> $conditions Условия.
     *
     * @return array<string, mixed> Строка.
     */
    private function marked(array $row, array $choice, array $conditions): array
    {
        $row['reaction'] = $choice['reaction'];
        $row['block_item_inventory_id'] = $choice['blockItemInventoryId'];
        $row['block_item_profile_index'] = $choice['blockItemProfileIndex'];
        $row['block_item_rule_code'] = $choice['blockItemRuleCode'];
        $row['expected_sheet_version'] = $choice['expectedSheetVersion'];
        $row['spend_concentration'] = $choice['spendConcentration'] ?? 0;
        $row['conditions'] = $conditions;

        return $row;
    }

    /**
     * Ранний итог, если не все цели ответили.
     *
     * @param GameStrikeWideRun $run Контекст прогона.
     * @param array<string, mixed> $open Открытый удар.
     * @param list<array<string, mixed>> $rows Строки.
     * @param list<array<string, mixed>> $requestChoices Ответы.
     *
     * @return array<string, mixed>|null Итог или null.
     */
    private function pending(GameStrikeWideRun $run, array $open, array $rows, array $requestChoices): ?array
    {
        if ($requestChoices === []) {
            return null;
        }

        $pendingTargets = $this->rows->pendingTargets($rows);
        if ($pendingTargets === []) {
            return null;
        }

        return [
            'battleId' => $run->battle->getId(),
            'strikeId' => $open['id'],
            'version' => $run->expectedBattleVersion,
            'closed' => false,
            'pendingTargets' => $pendingTargets,
            'targetResults' => [],
        ];
    }

    /**
     * Ранний итог, если строки ждут выбора фактической цели.
     *
     * @param GameStrikeWideRun $run Контекст прогона.
     * @param array<string, mixed> $open Открытый удар.
     * @param list<array<string, mixed>> $rows Строки.
     *
     * @return array<string, mixed>|null Итог или null.
     */
    private function waiting(GameStrikeWideRun $run, array $open, array $rows): ?array
    {
        $waiting = $this->resume->waiting($rows, $run->resume);
        if ($waiting === []) {
            return null;
        }

        return [
            'battleId' => $run->battle->getId(),
            'strikeId' => $open['id'],
            'version' => $run->expectedBattleVersion,
            'attackerRoll' => [],
            'targetResults' => [],
            'closed' => false,
            'pendingTargets' => [],
            'awaiting' => $waiting,
        ];
    }
}
