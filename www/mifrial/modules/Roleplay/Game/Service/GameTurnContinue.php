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
 * Доплата долга действия, раны или оплаченного удара.
 */
final class GameTurnContinue
{
    /**
     * Создаёт сценарий.
     *
     * @param GameTurnSnapshot $snapshot Снимок и срез листа.
     * @param GameStrikeSheetWrites $sheets Запись листа.
     * @param GameBattleRepository $battles Строки боя.
     * @param GameActionDeclare $actionDeclare Сценарий карточки.
     * @param GameWoundActions $wounds Сценарий раны.
     * @param GameStrikeOpen $open Открытие оплаченного удара.
     *
     * @return void
     */
    public function __construct(
        private readonly GameTurnSnapshot $snapshot,
        private readonly GameStrikeSheetWrites $sheets,
        private readonly GameBattleRepository $battles,
        private readonly GameActionDeclare $actionDeclare,
        private readonly GameWoundActions $wounds,
        private readonly GameStrikeOpen $open,
    ) {
    }

    /**
     * Доплачивает остаток долга и возобновляет сценарий.
     *
     * @param GameRecord $game Игра.
     * @param int $sessionId Сессия.
     * @param GameBattleRecord $battle Бой.
     * @param int $expectedBattle Версия боя.
     * @param int $expectedSheet Версия листа.
     * @param array{type: string, id: int} $current Текущий.
     * @param int $actorUserId Актор.
     * @param bool $editAll Ключ game.edit_all.
     * @param bool $viewAll Ключ game.view_all.
     *
     * @return array<string, mixed> Итог.
     *
     * @throws GameInvalidException Если долга нет или сценарий отвергнут.
     * @throws GameNotFoundException Если листа или боя нет.
     * @throws GameBattleConflictException Если версия листа другая.
     */
    public function continue(
        GameRecord $game,
        int $sessionId,
        GameBattleRecord $battle,
        int $expectedBattle,
        int $expectedSheet,
        array $current,
        int $actorUserId,
        bool $editAll,
        bool $viewAll,
    ): array {
        $prepared = $this->slot($battle, $current, $expectedSheet);
        $debt = new GameActionDebt();
        $paid = $debt->continuePay($this->snapshot->slice($game), $prepared['snapshot']['sheet'], $prepared['slot']);
        $sheetVersion = $this->spend($game, $current, $expectedSheet, $paid['spend']);
        $payload = $debt->payloadOf($paid['slot']);
        if ($paid['open'] && $payload !== null) {
            return $this->opened(
                $game, $sessionId, $battle, $expectedBattle, $sheetVersion,
                $current, $actorUserId, $editAll, $viewAll, $prepared, $paid, $payload,
            );
        }

        return $this->save($battle, $expectedBattle, $sheetVersion, $prepared, $paid['slot']);
    }

    /**
     * Слот долга, карта и снимок листа.
     *
     * @param GameBattleRecord $battle Бой.
     * @param array{type: string, id: int} $current Текущий.
     * @param int $expectedSheet Версия листа.
     *
     * @return array{debtMap: array<string, mixed>, key: string, slot: array<string, mixed>, snapshot: array{actualVersion: int, sheet: array<string, mixed>}} Подготовка.
     *
     * @throws GameInvalidException Если долга нет.
     * @throws GameBattleConflictException Если версия листа другая.
     */
    private function slot(GameBattleRecord $battle, array $current, int $expectedSheet): array
    {
        $debtMap = $battle->getActionDebt();
        $key = $current['type'] . ':' . $current['id'];
        $slot = $debtMap[$key] ?? null;
        if (!is_array($slot)) {
            throw new GameInvalidException('Game action debt is missing');
        }

        $snapshot = $this->snapshot->of($current);
        if ($snapshot['actualVersion'] !== $expectedSheet) {
            throw new GameBattleConflictException($snapshot['actualVersion']);
        }

        return ['debtMap' => $debtMap, 'key' => $key, 'slot' => $slot, 'snapshot' => $snapshot];
    }

    /**
     * Записывает оплату, если она есть.
     *
     * @param GameRecord $game Игра.
     * @param array{type: string, id: int} $current Текущий.
     * @param int $expectedSheet Версия листа.
     * @param mixed $spend Оплата или null.
     *
     * @return int Версия листа.
     */
    private function spend(GameRecord $game, array $current, int $expectedSheet, mixed $spend): int
    {
        if ($spend === null) {
            return $expectedSheet;
        }

        return $this->sheets->write(
            $game,
            $current['type'],
            $current['id'],
            $expectedSheet,
            [],
            null,
            null,
            [$spend],
        );
    }

    /**
     * Возобновляет открытый сценарий или отказывает.
     *
     * @param GameRecord $game Игра.
     * @param int $sessionId Сессия.
     * @param GameBattleRecord $battle Бой.
     * @param int $expectedBattle Версия боя.
     * @param int $sheetVersion Версия листа.
     * @param array{type: string, id: int} $current Текущий.
     * @param int $actorUserId Актор.
     * @param bool $editAll Ключ game.edit_all.
     * @param bool $viewAll Ключ game.view_all.
     * @param array{debtMap: array<string, mixed>, key: string, slot: array<string, mixed>, snapshot: array{actualVersion: int, sheet: array<string, mixed>}} $prepared Подготовка.
     * @param array<string, mixed> $paid Итог доплаты.
     * @param array<string, mixed> $payload Тело сценария.
     *
     * @return array<string, mixed> Итог.
     *
     * @throws GameNotFoundException Если сценария нет.
     */
    private function opened(
        GameRecord $game,
        int $sessionId,
        GameBattleRecord $battle,
        int $expectedBattle,
        int $sheetVersion,
        array $current,
        int $actorUserId,
        bool $editAll,
        bool $viewAll,
        array $prepared,
        array $paid,
        array $payload,
    ): array {
        $kind = (new GameActionDebt())->kindOf($paid['slot']);
        $attempt = $this->attempt(
            $game, $sessionId, $battle, $expectedBattle, $sheetVersion,
            $actorUserId, $editAll, $viewAll, $paid, $payload,
        );
        if ($attempt['refused'] !== null) {
            return $this->save($battle, $expectedBattle, $sheetVersion, $prepared, $paid['slot'], $attempt['refused']);
        }

        if ($kind === 'action' || $kind === 'wound') {
            unset($prepared['debtMap'][$prepared['key']]);
            $this->battles->replaceActionDebt($battle->getId(), $prepared['debtMap']);
        }

        return $attempt['result'] + ['sheetVersion' => $sheetVersion, 'opened' => true];
    }

    /**
     * Пробует возобновить сценарий, оставляя отказ для вызывающего.
     *
     * @param GameRecord $game Игра.
     * @param int $sessionId Сессия.
     * @param GameBattleRecord $battle Бой.
     * @param int $expectedBattle Версия боя.
     * @param int $sheetVersion Версия листа.
     * @param int $actorUserId Актор.
     * @param bool $editAll Ключ game.edit_all.
     * @param bool $viewAll Ключ game.view_all.
     * @param array<string, mixed> $paid Итог доплаты.
     * @param array<string, mixed> $payload Тело сценария.
     *
     * @return array{result: array<string, mixed>|null, refused: GameInvalidException|null} Попытка.
     */
    private function attempt(
        GameRecord $game,
        int $sessionId,
        GameBattleRecord $battle,
        int $expectedBattle,
        int $sheetVersion,
        int $actorUserId,
        bool $editAll,
        bool $viewAll,
        array $paid,
        array $payload,
    ): array {
        try {
            $result = $this->resume(
                $game, $sessionId, $battle, $expectedBattle, $sheetVersion,
                $actorUserId, $editAll, $viewAll, $paid, $payload,
            );
        } catch (GameInvalidException $exception) {
            return ['result' => null, 'refused' => $exception];
        }

        return ['result' => $result, 'refused' => null];
    }

    /**
     * Возобновляет сценарий по виду долга.
     *
     * @param GameRecord $game Игра.
     * @param int $sessionId Сессия.
     * @param GameBattleRecord $battle Бой.
     * @param int $expectedBattle Версия боя.
     * @param int $sheetVersion Версия листа.
     * @param int $actorUserId Актор.
     * @param bool $editAll Ключ game.edit_all.
     * @param bool $viewAll Ключ game.view_all.
     * @param array<string, mixed> $paid Итог доплаты.
     * @param array<string, mixed> $payload Тело сценария.
     *
     * @return array<string, mixed> Итог.
     *
     * @throws GameInvalidException Если сценарий отвергнут.
     * @throws GameNotFoundException Если сценария или листа нет.
     */
    private function resume(
        GameRecord $game,
        int $sessionId,
        GameBattleRecord $battle,
        int $expectedBattle,
        int $sheetVersion,
        int $actorUserId,
        bool $editAll,
        bool $viewAll,
        array $paid,
        array $payload,
    ): array {
        return match ((new GameActionDebt())->kindOf($paid['slot'])) {
            'action' => $this->action($game, $actorUserId, $editAll, $viewAll, $payload, $expectedBattle, $sheetVersion),
            'wound' => $this->wound($game, $actorUserId, $payload, $expectedBattle, $sheetVersion),
            default => $this->open->openPaidAction(
                $game,
                $sessionId,
                $battle,
                $expectedBattle,
                $payload,
                $this->kindForLane($paid['slot']['lane'] ?? null),
            ),
        };
    }

    /**
     * Возобновляет карточку действия.
     *
     * @param GameRecord $game Игра.
     * @param int $actorUserId Актор.
     * @param bool $editAll Ключ game.edit_all.
     * @param bool $viewAll Ключ game.view_all.
     * @param array<string, mixed> $payload Тело сценария.
     * @param int $expectedBattle Версия боя.
     * @param int $sheetVersion Версия листа.
     *
     * @return array<string, mixed> Итог.
     *
     * @throws GameInvalidException Если карточка отвергнута.
     * @throws GameNotFoundException Если карточки нет.
     */
    private function action(
        GameRecord $game,
        int $actorUserId,
        bool $editAll,
        bool $viewAll,
        array $payload,
        int $expectedBattle,
        int $sheetVersion,
    ): array {
        return $this->actionDeclare->resume(
            $game->getId(),
            $actorUserId,
            $editAll,
            $viewAll,
            $payload,
            $expectedBattle,
            $sheetVersion,
        );
    }

    /**
     * Возобновляет рану.
     *
     * @param GameRecord $game Игра.
     * @param int $actorUserId Актор.
     * @param array<string, mixed> $payload Тело сценария.
     * @param int $expectedBattle Версия боя.
     * @param int $sheetVersion Версия листа.
     *
     * @return array<string, mixed> Итог.
     *
     * @throws GameInvalidException Если рана отвергнута.
     * @throws GameNotFoundException Если листа нет.
     */
    private function wound(
        GameRecord $game,
        int $actorUserId,
        array $payload,
        int $expectedBattle,
        int $sheetVersion,
    ): array {
        return $this->wounds->resume($game, $actorUserId, $payload, $expectedBattle, $sheetVersion);
    }

    /**
     * Сохраняет долг: неполный или с отказом сценария.
     *
     * @param GameBattleRecord $battle Бой.
     * @param int $expectedBattle Версия боя.
     * @param int $sheetVersion Версия листа.
     * @param array{debtMap: array<string, mixed>, key: string, slot: array<string, mixed>, snapshot: array{actualVersion: int, sheet: array<string, mixed>}} $prepared Подготовка.
     * @param array<string, mixed> $slot Слот.
     * @param GameInvalidException|null $exception Отказ или null.
     *
     * @return array<string, mixed> Итог.
     *
     * @throws GameBattleConflictException Если версия другая.
     * @throws GameNotFoundException Если боя нет.
     * @throws GameInvalidException Если поле.
     */
    private function save(
        GameBattleRecord $battle,
        int $expectedBattle,
        int $sheetVersion,
        array $prepared,
        array $slot,
        ?GameInvalidException $exception = null,
    ): array {
        $prepared['debtMap'][$prepared['key']] = $slot;
        $saved = $this->battles->replaceTracked(
            $battle->getId(), $expectedBattle, null, $prepared['debtMap'], null, null,
        );
        $result = ['battleId' => $battle->getId(), 'version' => $saved->getStateVersion(), 'sheetVersion' => $sheetVersion, 'opened' => false];
        if ($exception !== null) {
            $result['refused'] = $exception->getMessage();

            return $result;
        }

        $result['remaining'] = $slot['remaining'] ?? 0;

        return $result;
    }

    /**
     * Вид удара по полосе долга.
     *
     * @param mixed $lane Полоса слота долга.
     *
     * @return string Вид удара.
     */
    private function kindForLane(mixed $lane): string
    {
        return $lane === '1-n' ? 'wide_strike' : 'strike';
    }
}
