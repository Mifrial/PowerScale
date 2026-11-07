<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Dto\GameBattleRecord;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;

/**
 * Открывает окно раскрытия манёвра.
 */
final class GameStrikeManeuverBegin
{
    /**
     * Создаёт открытие окна.
     *
     * @param GameStrikeManeuverRow $row Разбор строки.
     * @param GameStrikeManeuverTargets $targets Защитники.
     * @param GameStrikeManeuverState $state Ожидания и описание.
     * @param GameStrikeManeuverPersist $persist Запись окна.
     *
     * @return void
     */
    public function __construct(
        private readonly GameStrikeManeuverRow $row,
        private readonly GameStrikeManeuverTargets $targets,
        private readonly GameStrikeManeuverState $state,
        private readonly GameStrikeManeuverPersist $persist,
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
        $maneuver = $this->row->maneuverOf($row);
        $targets = $maneuver === [] ? [] : $this->targets->collect($row, $choice);
        if ($targets === []) {
            return ['active' => false];
        }
        $resolution = [
            'maneuver' => $maneuver,
            'attacker' => $attacker,
            'phase' => 'check',
            'targets' => $targets,
            'awaiting' => $this->state->awaiting($targets, 'check'),
        ];
        $strikeId = $this->persist->strikeId($row);
        $this->persist->answer($strikeId, $choice, $expectedSheetVersion, $resolution);
        $saved = $this->persist->replace($battle, $expectedBattleVersion);

        return [
            'active' => true,
            'result' => $this->result($battle, $strikeId, $saved->getStateVersion(), $resolution, $targets),
        ];
    }

    /**
     * Итог открытого окна.
     *
     * @param GameBattleRecord $battle Бой.
     * @param int $strikeId Строка удара.
     * @param int $version Версия боя.
     * @param array<string, mixed> $resolution Резолюция.
     * @param array<string, array<string, mixed>> $targets Защитники.
     *
     * @return array<string, mixed> Итог.
     */
    private function result(
        GameBattleRecord $battle,
        int $strikeId,
        int $version,
        array $resolution,
        array $targets,
    ): array {
        return [
            'battleId' => $battle->getId(),
            'strikeId' => $strikeId,
            'version' => $version,
            'closed' => false,
            'maneuver' => $this->state->publicManeuver($resolution),
            'awaiting' => $this->state->awaiting($targets, 'check'),
        ];
    }
}
