<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Closure;
use Mifrial\Roleplay\Character\Exception\CharacterConflictException;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Dto\GameStrikeWriteScope;
use Mifrial\Roleplay\Game\Exception\GameEconomyConflictException;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Запись листа цели и трат удара.
 */
final class GameStrikeTargetWrite
{
    /**
     * Создаёт запись цели.
     *
     * @param GameStrikeSheets $sheets Листы и конфликты.
     * @param GameStrikeSheetWrites $sheetWrites Запись листа.
     * @param GameStrikeResolution $resolution Расчёт.
     *
     * @return void
     */
    public function __construct(
        private readonly GameStrikeSheets $sheets,
        private readonly GameStrikeSheetWrites $sheetWrites,
        private readonly GameStrikeResolution $resolution,
    ) {
    }

    /**
     * Пишет повреждение и последствия цели.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $open Открытый удар.
     * @param array{type: string, id: int} $target Цель.
     * @param array<string, mixed> $choice Защита.
     * @param DimensionalNumber $injury Повреждение.
     * @param int $success Успех.
     * @param array<int, \Mifrial\Roleplay\Character\Dto\ResourceSpend> $spends Траты.
     * @param GameStrikeWriteScope $scope Права записи.
     * @param array<string, mixed> $record Итог цели.
     *
     * @return int Версия листа.
     *
     * @throws \Mifrial\Roleplay\Game\Exception\GameInvalidException Если лист или строка битые.
     * @throws \Mifrial\Roleplay\Game\Exception\GameNotFoundException Если листа нет.
     */
    public function writeTarget(
        GameRecord $game,
        array $open,
        array $target,
        array $choice,
        DimensionalNumber $injury,
        int $success,
        array $spends,
        GameStrikeWriteScope $scope,
        array &$record,
    ): int {
        $prepared = $this->resolution->damage->prepareSheet(
            $game,
            GameStrikeTargetRow::targetOpen($open, $target),
            $injury,
            $success,
        );
        $record = array_merge($record, $prepared['consequence']);

        return $this->write(
            $game,
            $target,
            $choice['expectedSheetVersion'],
            $prepared['operations'],
            $spends,
            $scope,
        );
    }

    /**
     * Пишет только траты реакции.
     *
     * @param GameRecord $game Игра.
     * @param array{type: string, id: int} $target Цель.
     * @param int $version Ожидаемая версия листа.
     * @param array<int, \Mifrial\Roleplay\Character\Dto\ResourceSpend> $spends Траты.
     * @param GameStrikeWriteScope $scope Права записи.
     *
     * @return int Версия листа.
     */
    public function writeResources(
        GameRecord $game,
        array $target,
        int $version,
        array $spends,
        GameStrikeWriteScope $scope,
    ): int {
        return $this->sheetWrites->writeResources(
            $game,
            $target['type'],
            $target['id'],
            $version,
            $spends,
            $target,
            $this->conflict($game, $target, $scope),
        );
    }

    /**
     * Пишет операции листа цели.
     *
     * @param GameRecord $game Игра.
     * @param array{type: string, id: int} $target Цель.
     * @param int $version Ожидаемая версия листа.
     * @param list<array<string, mixed>> $operations Операции.
     * @param array<int, \Mifrial\Roleplay\Character\Dto\ResourceSpend> $spends Траты.
     * @param GameStrikeWriteScope $scope Права записи.
     *
     * @return int Версия листа.
     */
    private function write(
        GameRecord $game,
        array $target,
        int $version,
        array $operations,
        array $spends,
        GameStrikeWriteScope $scope,
    ): int {
        return $this->sheetWrites->write(
            $game,
            $target['type'],
            $target['id'],
            $version,
            $operations,
            $target,
            $this->conflict($game, $target, $scope),
            $spends,
        );
    }

    /**
     * Обработчик конфликта записи.
     *
     * @param GameRecord $game Игра.
     * @param array{type: string, id: int} $target Цель.
     * @param GameStrikeWriteScope $scope Права записи.
     *
     * @return Closure(CharacterConflictException|GameEconomyConflictException): array<string, mixed>
     */
    private function conflict(GameRecord $game, array $target, GameStrikeWriteScope $scope): Closure
    {
        return fn (
            CharacterConflictException|GameEconomyConflictException $exception,
        ): array => $this->sheets->projectWriteConflict(
            $game,
            $target,
            $exception,
            $scope->actorUserId,
            $scope->viewAll,
        );
    }
}
