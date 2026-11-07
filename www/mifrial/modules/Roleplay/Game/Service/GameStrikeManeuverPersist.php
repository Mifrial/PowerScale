<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Dto\GameBattleRecord;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Repository\GameActionRepository;
use Mifrial\Roleplay\Game\Repository\GameBattleRepository;

/**
 * Запись промежуточного и итогового состояния окна манёвра.
 */
final class GameStrikeManeuverPersist
{
    /**
     * Создаёт запись окна.
     *
     * @param GameActionRepository $actions Строки удара.
     * @param GameBattleRepository $battles Бои.
     * @param GameStrikeManeuverState $state Ожидания и описание.
     *
     * @return void
     */
    public function __construct(
        private readonly GameActionRepository $actions,
        private readonly GameBattleRepository $battles,
        private readonly GameStrikeManeuverState $state,
    ) {
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
    public function persist(
        GameBattleRecord $battle,
        array $row,
        array $resolution,
        array $targets,
        string $phase,
        int $expectedBattleVersion,
    ): array {
        $resolution['targets'] = $targets;
        $resolution['phase'] = $phase;
        $resolution['awaiting'] = $this->state->awaiting($targets, $phase);
        $this->save($row, $resolution);
        $saved = $this->replace($battle, $expectedBattleVersion);

        return [
            'closed' => false,
            'version' => $saved->getStateVersion(),
            'maneuver' => $this->state->publicManeuver($resolution),
            'awaiting' => $resolution['awaiting'],
        ];
    }

    /**
     * Открывает окно: пишет ответ защиты и резолюцию.
     *
     * @param int $strikeId Строка удара.
     * @param array<string, mixed> $choice Защита цели.
     * @param int $expectedSheetVersion Версия листа.
     * @param array<string, mixed> $resolution Резолюция.
     *
     * @return void
     */
    public function answer(int $strikeId, array $choice, int $expectedSheetVersion, array $resolution): void
    {
        $this->actions->answerStrike($strikeId, [
            'reaction' => $choice['reaction'],
            'block_item_inventory_id' => $choice['blockItemInventoryId'] ?? null,
            'block_item_profile_index' => $choice['blockItemProfileIndex'] ?? null,
            'block_item_rule_code' => $choice['blockItemRuleCode'] ?? null,
            'expected_sheet_version' => $expectedSheetVersion,
            'resolution' => $resolution,
        ]);
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
    public function save(array $row, array $resolution): void
    {
        $this->actions->answerStrike($this->strikeId($row), ['resolution' => $resolution] + $this->copied($row));
    }

    /**
     * Обновляет версию боя.
     *
     * @param GameBattleRecord $battle Бой.
     * @param int $expectedBattleVersion Версия.
     *
     * @return GameBattleRecord Бой.
     */
    public function replace(GameBattleRecord $battle, int $expectedBattleVersion): GameBattleRecord
    {
        return $this->battles->replaceTracked(
            $battle->getId(),
            $expectedBattleVersion,
            null,
            null,
            null,
            null,
        );
    }

    /**
     * Id строки удара.
     *
     * @param array<string, mixed> $row Строка.
     *
     * @return int Id.
     *
     * @throws GameInvalidException Если id нет.
     */
    public function strikeId(array $row): int
    {
        $strikeId = is_int($row['id'] ?? null) ? $row['id'] : 0;
        if ($strikeId < 1) {
            throw new GameInvalidException('Game strike row is invalid');
        }

        return $strikeId;
    }

    /**
     * Поля строки, которые переносятся вместе с резолюцией.
     *
     * @param array<string, mixed> $row Строка.
     *
     * @return array<string, mixed> Поля.
     */
    private function copied(array $row): array
    {
        $fields = [];
        if (array_key_exists('reaction', $row)) {
            $fields['reaction'] = $row['reaction'];
        }

        if (array_key_exists('coverers', $row)) {
            $fields['coverers'] = $row['coverers'];
        }

        return $fields;
    }
}
