<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Dto\GameBattleRecord;
use Mifrial\Roleplay\Game\Dto\GameStrikeSingleRun;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Repository\GameActionRepository;
use Mifrial\Roleplay\Game\Repository\GameBattleRepository;

/**
 * Прикрытие одиночного удара и окно выбора цели.
 */
final class GameStrikeSingleCoverPhase
{
    /**
     * Создаёт прикрытие удара.
     *
     * @param GameStrikeCover $cover Прикрытие.
     * @param GameStrikeCloseRows $rows Строки удара.
     * @param GameStrikeSheetWrites $sheetWrites Запись листа.
     * @param GameStrikeTargetChoices $choices Choices целей.
     * @param GameActionRepository $actions Действия.
     * @param GameBattleRepository $battles Бои.
     *
     * @return void
     */
    public function __construct(
        private readonly GameStrikeCover $cover,
        private readonly GameStrikeCloseRows $rows,
        private readonly GameStrikeSheetWrites $sheetWrites,
        private readonly GameStrikeTargetChoices $choices,
        private readonly GameActionRepository $actions,
        private readonly GameBattleRepository $battles,
    ) {
    }

    /**
     * Разрешает прикрытие и при необходимости останавливает удар.
     *
     * @param GameStrikeSingleRun $run Прогон.
     *
     * @return array<string, mixed>|null Итог ожидания выбора цели.
     *
     * @throws GameInvalidException Если выбор прикрытия битый.
     */
    public function apply(GameStrikeSingleRun $run): ?array
    {
        if ($run->coverers === [] || $run->resumeState !== null) {
            return null;
        }

        $cover = $this->resolveCover($run);
        if ($cover['winner'] !== null) {
            $this->winner($run, $cover);

            return null;
        }

        return $cover['failed'] ? $this->pause($run, $cover) : null;
    }

    /**
     * Считает прикрытие по строке.
     *
     * @param GameStrikeSingleRun $run Прогон.
     *
     * @return array<string, mixed> Итог прикрытия.
     */
    private function resolveCover(GameStrikeSingleRun $run): array
    {
        return $this->cover->resolve(
            $run->input->game,
            $run->open,
            $run->row,
            $run->conditions,
            $run->choice,
            $run->attackerRoll,
            $run->hitCode,
            $run->slice,
            $run->pending,
            $run->defenderPending,
            $run->profileType,
            $run->isPush,
            $run->profileType === 'shoot' ? $run->metrics['distance'] : null,
            $run->input->expectedSheetVersion,
            $run->battle->getMarks(),
        );
    }

    /**
     * Подменяет цель победителем прикрытия.
     *
     * @param GameStrikeSingleRun $run Прогон.
     * @param array<string, mixed> $cover Итог прикрытия.
     *
     * @return void
     *
     * @throws GameInvalidException Если выбор победителя битый.
     */
    private function winner(GameStrikeSingleRun $run, array $cover): void
    {
        $winner = $cover['winner'];
        $target = $winner['actor'] ?? null;
        if (!is_array($target)) {
            throw new GameInvalidException('Game strike cover winner is invalid');
        }

        $primaryKey = $this->rows->targetKey($run->target);
        $winnerKey = $this->rows->targetKey($target);
        $run->coverInfo = $this->cover->info($cover['candidates'], $primaryKey, $winnerKey);
        if ($run->spends !== []) {
            $this->sheetWrites->writeResources(
                $run->input->game,
                $run->target['type'],
                $run->target['id'],
                $run->input->expectedSheetVersion,
                $run->spends,
            );
        }

        $this->reassign($run, $winner, $target, $winnerKey);
    }

    /**
     * Переносит победителя прикрытия в состояние строки.
     *
     * @param GameStrikeSingleRun $run Прогон.
     * @param array<string, mixed> $winner Победитель.
     * @param array{type: string, id: int} $target Цель.
     * @param string $winnerKey Ключ победителя.
     *
     * @return void
     */
    private function reassign(GameStrikeSingleRun $run, array $winner, array $target, string $winnerKey): void
    {
        $run->target = $target;
        $run->targetKey = $winnerKey;
        $run->choice = is_array($winner['choice'] ?? null) ? $winner['choice'] : $run->choice;
        $run->input->expectedSheetVersion = is_int($winner['expectedSheetVersion'] ?? null)
            ? $winner['expectedSheetVersion']
            : $run->input->expectedSheetVersion;
        $run->reactionRuleCode = is_string($winner['reactionRuleCode'] ?? null)
            ? $winner['reactionRuleCode']
            : null;
        $run->turnRuleCode = null;
        $run->defenderChoices = $this->choices->targetChoices($run->input->game, $run->target);
        $run->spends = is_array($winner['spends'] ?? null) ? $winner['spends'] : [];
        $run->defenderPending = $run->pending;
        $run->defenderToken = null;
        $run->targetOpen = $this->rows->targetOpen($run->open, $run->target);
        $run->attackerRoll = is_array($winner['attackerRoll'] ?? null) ? $winner['attackerRoll'] : $run->attackerRoll;
        $run->defenderRoll = is_array($winner['defenderRoll'] ?? null) ? $winner['defenderRoll'] : null;
        $run->defenseRolled = true;
        $run->markKeys = $this->stringList($winner['markKeys'] ?? null);
    }

    /**
     * Останавливает удар и ждёт выбора фактической цели атакующим.
     *
     * @param GameStrikeSingleRun $run Прогон.
     * @param array<string, mixed> $cover Итог прикрытия.
     *
     * @return array<string, mixed> Итог с ожиданием.
     */
    private function pause(GameStrikeSingleRun $run, array $cover): array
    {
        $target = $run->target;
        $primaryKey = $target['type'] . ':' . $target['id'];
        $expectedSheetVersion = $this->writeSpends($run, $primaryKey, $cover);
        $awaiting = $this->awaiting($run, $cover);
        $resolution = [
            'attackerRoll' => $run->attackerRoll,
            'primaryKey' => $primaryKey,
            'candidates' => $cover['candidates'],
            'awaiting' => $awaiting,
        ];
        $this->answer($run, $expectedSheetVersion, $resolution);
        $saved = $this->save($run);

        return [
            'battleId' => $run->input->battleId,
            'strikeId' => $run->strikeId,
            'version' => $saved->getStateVersion(),
            'closed' => false,
            'awaiting' => $awaiting,
        ];
    }

    /**
     * Записывает траты исходной цели до ожидания.
     *
     * @param GameStrikeSingleRun $run Прогон.
     * @param string $primaryKey Ключ исходной цели.
     * @param array<string, mixed> $cover Итог прикрытия.
     *
     * @return int Версия листа.
     */
    private function writeSpends(GameStrikeSingleRun $run, string $primaryKey, array &$cover): int
    {
        $expectedSheetVersion = $run->input->expectedSheetVersion;
        if ($run->spends === []) {
            return $expectedSheetVersion;
        }

        $expectedSheetVersion = $this->sheetWrites->writeResources(
            $run->input->game,
            $run->target['type'],
            $run->target['id'],
            $expectedSheetVersion,
            $run->spends,
        );
        $cover['candidates'][$primaryKey]['expectedSheetVersion'] = $expectedSheetVersion;

        return $expectedSheetVersion;
    }

    /**
     * Ожидание выбора фактической цели.
     *
     * @param GameStrikeSingleRun $run Прогон.
     * @param array<string, mixed> $cover Итог прикрытия.
     *
     * @return list<array<string, mixed>> Ожидания.
     */
    private function awaiting(GameStrikeSingleRun $run, array $cover): array
    {
        $options = [];
        foreach ($cover['candidates'] as $key => $candidate) {
            $options[] = ['key' => $key, 'actor' => $candidate['actor']];
        }

        return [
            [
                'kind' => 'actual_target',
                'actor' => ['type' => $run->attackerKind, 'id' => $run->attackerId],
                'options' => $options,
            ],
        ];
    }

    /**
     * Записывает ответ защиты исходной цели.
     *
     * @param GameStrikeSingleRun $run Прогон.
     * @param int $expectedSheetVersion Версия листа.
     * @param array<string, mixed> $resolution Решение окна.
     *
     * @return void
     */
    private function answer(GameStrikeSingleRun $run, int $expectedSheetVersion, array $resolution): void
    {
        $this->actions->answerStrike(
            is_int($run->row['id'] ?? null) ? $run->row['id'] : $run->strikeId,
            [
                'reaction' => $run->choice['reaction'],
                'block_item_inventory_id' => $run->choice['blockItemInventoryId'],
                'block_item_profile_index' => $run->choice['blockItemProfileIndex'],
                'block_item_rule_code' => $run->choice['blockItemRuleCode'],
                'expected_sheet_version' => $expectedSheetVersion,
                'resolution' => $resolution,
            ],
        );
    }

    /**
     * Сохраняет остановленный бой.
     *
     * @param GameStrikeSingleRun $run Прогон.
     *
     * @return GameBattleRecord Бой.
     */
    private function save(GameStrikeSingleRun $run): GameBattleRecord
    {
        return $this->battles->replaceTracked(
            $run->input->battleId,
            $run->input->expectedBattleVersion,
            null,
            null,
            null,
            null,
        );
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
}
