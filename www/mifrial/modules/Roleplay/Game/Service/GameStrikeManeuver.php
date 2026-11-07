<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Dto\GameBattleRecord;
use Mifrial\Roleplay\Game\Dto\GameRecord;
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
    private readonly GameStrikeManeuverRow $row;

    private readonly GameStrikeManeuverBegin $begin;

    private readonly GameStrikeManeuverResolve $resolve;

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
        GameActionRepository $actions,
        GameBattleRepository $battles,
        GameStrikeSheets $sheets,
    ) {
        $row = new GameStrikeManeuverRow();
        $state = new GameStrikeManeuverState();
        $persist = new GameStrikeManeuverPersist($actions, $battles, $state);
        $this->row = $row;
        $this->begin = new GameStrikeManeuverBegin(
            $row,
            new GameStrikeManeuverTargets($row),
            $state,
            $persist,
        );
        $this->resolve = new GameStrikeManeuverResolve(
            $row,
            new GameStrikeManeuverCheck(),
            new GameStrikeManeuverRoll($sheets),
            $state,
            $persist,
            new GameStrikeManeuverDecisions($row),
        );
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
        return $this->begin->begin(
            $game,
            $battle,
            $row,
            $attacker,
            $choice,
            $expectedBattleVersion,
            $expectedSheetVersion,
        );
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
        return $this->resolve->resolve($game, $battle, $row, $window, $expectedBattleVersion, $checkRoll);
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
        return $this->row->maneuverOf($row);
    }
}
