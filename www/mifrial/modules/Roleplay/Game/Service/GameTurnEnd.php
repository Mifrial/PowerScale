<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Dto\GameBattleRecord;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Exception\GameBattleConflictException;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Game\Repository\GameBattleRepository;

/**
 * Конец хода: Ожидание, тики, метки и передача хода.
 */
final class GameTurnEnd
{
    /**
     * Создаёт сценарий.
     *
     * @param GameTurnWait $wait Ожидание.
     * @param GameTurnEndSheet $sheet Тики и лист.
     * @param GameBattleRepository $battles Строки боя.
     * @param GameBattleMarks $marks Метки боя.
     * @param GameTurnCursor $cursor Курсор хода.
     * @param GameTurnAnnounce $announce Сообщения.
     *
     * @return void
     */
    public function __construct(
        private readonly GameTurnWait $wait,
        private readonly GameTurnEndSheet $sheet,
        private readonly GameBattleRepository $battles,
        private readonly GameBattleMarks $marks,
        private readonly GameTurnCursor $cursor,
        private readonly GameTurnAnnounce $announce,
    ) {
    }

    /**
     * Закрывает ход текущего участника.
     *
     * @param GameRecord $game Игра.
     * @param GameBattleRecord $battle Бой.
     * @param int $expectedBattle Версия боя.
     * @param int $expectedSheet Версия листа текущего.
     * @param array{type: string, id: int} $current Текущий.
     * @param int $actorUserId Пользователь.
     *
     * @return array<string, mixed> Итог.
     *
     * @throws GameBattleConflictException Если версия боя или листа другая.
     * @throws GameInvalidException Если удар открыт или карточки Ожидания нет.
     * @throws GameNotFoundException Если листа или боя нет.
     */
    public function end(
        GameRecord $game,
        GameBattleRecord $battle,
        int $expectedBattle,
        int $expectedSheet,
        array $current,
        int $actorUserId,
    ): array {
        $performed = $this->wait->perform($game, $battle, $current, $expectedSheet);
        $ticked = $this->sheet->apply(
            $game, $performed['slice'], $current,
            $performed['sheetVersion'], $battle->getConcentrationCycle(),
        );
        $cursor = $this->cursor->advanced($battle);
        $saved = $this->persist($battle, $expectedBattle, $performed['pending'], $cursor, $ticked['cycle'], $current);
        $result = [
            'battleId' => $battle->getId(),
            'version' => $saved->getStateVersion(),
            'sheetVersion' => $ticked['sheetVersion'],
            'activeIndex' => $cursor['index'],
            'round' => $cursor['round'],
            'pendingActionEffects' => $performed['pending'],
        ];
        $this->announce->wait($game, $actorUserId, $current, $battle, $performed['waitCode'], $performed);
        $this->announce->rolls($game, $ticked['rolls']);
        $this->announce->passed($game, $actorUserId, $battle, $result);

        return $result;
    }

    /**
     * Пишет ожидание, курсор, цикл и метки одним CAS.
     *
     * @param GameBattleRecord $battle Бой.
     * @param int $expectedBattle Версия боя.
     * @param array<string, mixed> $pending Карта ожидания.
     * @param array{index: int, round: int} $cursor Курсор.
     * @param array<string, mixed> $cycle Цикл концентрации.
     * @param array{type: string, id: int} $current Текущий.
     *
     * @return GameBattleRecord Строка после записи.
     *
     * @throws GameBattleConflictException Если версия другая.
     * @throws GameNotFoundException Если боя нет.
     * @throws GameInvalidException Если поле.
     */
    private function persist(
        GameBattleRecord $battle,
        int $expectedBattle,
        array $pending,
        array $cursor,
        array $cycle,
        array $current,
    ): GameBattleRecord {
        return $this->battles->replaceTracked(
            $battle->getId(),
            $expectedBattle,
            $pending,
            null,
            $cursor['index'],
            $cursor['round'],
            $cycle,
            $this->expiredMarks($battle, $current),
        );
    }

    /**
     * Метки после истечения хода или null, если их число не изменилось.
     *
     * @param GameBattleRecord $battle Бой.
     * @param array{type: string, id: int} $current Текущий.
     *
     * @return array<int, array<string, mixed>>|null Метки или null.
     */
    private function expiredMarks(GameBattleRecord $battle, array $current): ?array
    {
        $before = $battle->getMarks();
        $after = $this->marks->expireTurn($before, $current);

        return count($after) === count($before) ? null : $after;
    }
}
