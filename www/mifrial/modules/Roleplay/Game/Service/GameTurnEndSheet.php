<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Game\Dto\GameRecord;

/**
 * Тики конца хода и запись листа.
 */
final class GameTurnEndSheet
{
    /**
     * Создаёт шаг.
     *
     * @param GameTurnSnapshot $snapshot Снимок листа.
     * @param GameTurnTicks $ticks Тики.
     * @param GameStrikeSheetWrites $sheets Запись листа.
     *
     * @return void
     */
    public function __construct(
        private readonly GameTurnSnapshot $snapshot,
        private readonly GameTurnTicks $ticks,
        private readonly GameStrikeSheetWrites $sheets,
    ) {
    }

    /**
     * Считает тики, закрывает цикл концентрации и пишет лист.
     *
     * @param GameRecord $game Игра.
     * @param CharacterRuleSlice $slice Срез.
     * @param array{type: string, id: int} $current Текущий.
     * @param int $sheetVersion Версия листа после Ожидания.
     * @param array<string, mixed> $cycle Цикл концентрации.
     *
     * @return array{sheetVersion: int, rolls: list<array<string, mixed>>, cycle: array<string, mixed>} Итог.
     */
    public function apply(
        GameRecord $game,
        CharacterRuleSlice $slice,
        array $current,
        int $sheetVersion,
        array $cycle,
    ): array {
        $snapshot = $this->snapshot->of($current);
        $built = $this->ticks->build($game, $slice, $snapshot['sheet']);
        $ended = $this->concentration($slice, $snapshot['sheet'], $cycle, $current);
        $cycle = $ended['cycle'];
        if ($ended['refill']) {
            $built['operations'][] = ['kind' => 'refillCheckTokenResources'];
        }

        if ($built['operations'] !== []) {
            $sheetVersion = $this->sheets->write(
                $game,
                $current['type'],
                $current['id'],
                $snapshot['actualVersion'],
                $built['operations'],
            );
        }

        return ['sheetVersion' => $sheetVersion, 'rolls' => $built['rolls'], 'cycle' => $cycle];
    }

    /**
     * Закрывает цикл концентрации и добавляет refill токенов.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param array<string, mixed> $sheet Лист.
     * @param array<string, mixed> $cycle Цикл.
     * @param array{type: string, id: int} $current Текущий.
     *
     * @return array{cycle: array<string, mixed>, refill: bool} Цикл и признак refill.
     */
    private function concentration(
        CharacterRuleSlice $slice,
        array $sheet,
        array $cycle,
        array $current,
    ): array {
        $concentration = new GameConcentration();
        if ($concentration->resourceCode($slice, $sheet) === null) {
            return ['cycle' => $cycle, 'refill' => false];
        }

        $actorKey = $current['type'] . ':' . $current['id'];

        return (new GameConcentrationCycle())->endTurn($cycle, $actorKey);
    }
}
