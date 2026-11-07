<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\ResourceSpend;
use Mifrial\Roleplay\Game\Dto\GameStrikeWideRun;
use Mifrial\Roleplay\Game\Repository\GameActionRepository;
use Mifrial\Roleplay\Game\Repository\GameBattleRepository;

/**
 * Пауза широкого удара на выборе фактической цели.
 */
final class GameStrikeWidePause
{
    /**
     * Создаёт паузу.
     *
     * @param GameStrikeSheetWrites $sheetWrites Запись листа.
     * @param GameActionRepository $actions Действия и цели.
     * @param GameBattleRepository $battles Бои.
     *
     * @return void
     */
    public function __construct(
        private readonly GameStrikeSheetWrites $sheetWrites,
        private readonly GameActionRepository $actions,
        private readonly GameBattleRepository $battles,
    ) {
    }

    /**
     * Останавливает широкий удар и ждёт выбора фактической цели по строкам.
     *
     * @param GameStrikeWideRun $run Контекст прогона.
     * @param int $strikeId Действие.
     * @param array<string, mixed> $attackerRoll Бросок атаки.
     * @param array<int, array<string, mixed>> $pauses Строки, ждущие решения.
     * @param array{type: string, id: int} $attacker Атакующий.
     *
     * @return array<string, mixed> Итог.
     */
    public function pause(
        GameStrikeWideRun $run, int $strikeId, array $attackerRoll, array $pauses, array $attacker,
    ): array {
        $awaitings = [];
        foreach ($pauses as $rowId => $pause) {
            $awaitings[] = $this->pauseRow($run, $rowId, $pause, $attackerRoll, $attacker);
        }

        $saved = $this->battles->replaceTracked($run->battle->getId(), $run->expectedBattleVersion, null, null, null, null);

        return [
            'battleId' => $run->battle->getId(),
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
     * Ставит на паузу одну строку и возвращает её ожидание.
     *
     * @param GameStrikeWideRun $run Контекст прогона.
     * @param int $rowId Строка.
     * @param array<string, mixed> $pause Данные паузы.
     * @param array<string, mixed> $attackerRoll Бросок атаки.
     * @param array{type: string, id: int} $attacker Атакующий.
     *
     * @return array<string, mixed> Ожидание.
     */
    private function pauseRow(
        GameStrikeWideRun $run,
        int $rowId,
        array $pause,
        array $attackerRoll,
        array $attacker,
    ): array {
        $primaryKey = $pause['primaryKey'];
        $target = is_array($pause['target'] ?? null)
            ? $pause['target']
            : ['type' => 'character', 'id' => 0];
        $reaction = is_array($pause['prepared']['reaction'] ?? null) ? $pause['prepared']['reaction'] : [];
        $spends = is_array($reaction['spends'] ?? null) ? $reaction['spends'] : [];
        $choice = is_array($pause['choice'] ?? null) ? $pause['choice'] : [];
        $candidates = $this->withSpends($run, $target, $choice, $pause['candidates'], $primaryKey, $spends);
        $awaiting = [
            'kind' => 'actual_target',
            'actor' => $attacker,
            'strikeId' => $rowId,
            'rowKey' => $primaryKey,
            'options' => $this->options($candidates),
        ];
        $this->answer($rowId, $choice, $candidates, $awaiting, $attackerRoll, $primaryKey);

        return $awaiting;
    }

    /**
     * Пишет траты реакции и проставляет версию листа кандидата.
     *
     * @param GameStrikeWideRun $run Контекст прогона.
     * @param array<string, mixed> $target Цель.
     * @param array<string, mixed> $choice Защита.
     * @param array<string, mixed> $candidates Кандидаты.
     * @param string $primaryKey Основная цель.
     * @param array<int, ResourceSpend> $spends Траты.
     *
     * @return array<string, mixed> Кандидаты.
     */
    private function withSpends(
        GameStrikeWideRun $run,
        array $target,
        array $choice,
        array $candidates,
        string $primaryKey,
        array $spends,
    ): array {
        if ($spends === []) {
            return $candidates;
        }

        $expected = is_int($choice['expectedSheetVersion'] ?? null) ? $choice['expectedSheetVersion'] : 0;
        $expected = $this->sheetWrites->writeResources(
            $run->game,
            is_string($target['type'] ?? null) ? $target['type'] : 'character',
            is_int($target['id'] ?? null) ? $target['id'] : 0,
            $expected,
            $spends,
        );
        $candidates[$primaryKey]['expectedSheetVersion'] = $expected;

        return $candidates;
    }

    /**
     * Варианты фактической цели для окна.
     *
     * @param array<string, mixed> $candidates Кандидаты.
     *
     * @return list<array{key: string, actor: array<string, mixed>}> Варианты.
     */
    private function options(array $candidates): array
    {
        $options = [];
        foreach ($candidates as $key => $candidate) {
            $options[] = ['key' => $key, 'actor' => $candidate['actor']];
        }

        return $options;
    }

    /**
     * Записывает ожидание и решение строки.
     *
     * @param int $rowId Строка.
     * @param array<string, mixed> $choice Защита.
     * @param array<string, mixed> $candidates Кандидаты.
     * @param array<string, mixed> $awaiting Ожидание.
     * @param array<string, mixed> $attackerRoll Бросок атаки.
     * @param string $primaryKey Основная цель.
     *
     * @return void
     */
    private function answer(
        int $rowId,
        array $choice,
        array $candidates,
        array $awaiting,
        array $attackerRoll,
        string $primaryKey,
    ): void {
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
    }
}
