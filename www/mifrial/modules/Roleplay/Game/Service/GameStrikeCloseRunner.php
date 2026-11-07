<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Dto\GameRecord;

/**
 * Закрытие одиночного удара и сигнал.
 */
final class GameStrikeCloseRunner
{
    /**
     * Создаёт закрытие удара.
     *
     * @param GameStrikeBody $body Разбор защиты.
     * @param GameStrikeReady $ready Общая проверка запроса.
     * @param GameStrikeClose $closeService Закрытие.
     * @param GameStrikeAnnounce $announce Сигнал.
     *
     * @return void
     */
    public function __construct(
        private readonly GameStrikeBody $body,
        private readonly GameStrikeReady $ready,
        private readonly GameStrikeClose $closeService,
        private readonly GameStrikeAnnounce $announce,
    ) {
    }

    /**
     * Закрывает удар и пишет деление повреждения в лист цели.
     *
     * @param int $gameId Игра.
     * @param int $actorUserId Актор.
     * @param bool $editAll Ключ game.edit_all.
     * @param bool $viewAll Полный просмотр.
     * @param int $battleId Бой.
     * @param string $idempotencyKey Ключ.
     * @param int $expectedBattleVersion Версия боя.
     * @param int $expectedSheetVersion Версия листа.
     * @param array $defense Защита.
     *
     * @return array<string, mixed> Итог.
     */
    public function resolve(
        int $gameId,
        int $actorUserId,
        bool $editAll,
        bool $viewAll,
        int $battleId,
        string $idempotencyKey,
        int $expectedBattleVersion,
        int $expectedSheetVersion,
        array $defense,
    ): array {
        $choice = $this->body->defense($defense);
        $ready = $this->ready($gameId, $actorUserId, $editAll, $viewAll, $battleId, $idempotencyKey, $expectedBattleVersion, $expectedSheetVersion, $choice);
        if ($ready['replay'] !== null) {
            return $ready['replay'];
        }

        return $this->close($ready['game'], $ready['sessionId'], $battleId, $idempotencyKey, $expectedBattleVersion, $expectedSheetVersion, $choice, $actorUserId, $editAll, $viewAll);
    }

    /**
     * Общая проверка запроса закрытия.
     *
     * @param int $gameId Игра.
     * @param int $actorUserId Актор.
     * @param bool $editAll Ключ.
     * @param bool $viewAll Ключ.
     * @param int $battleId Бой.
     * @param string $idempotencyKey Ключ.
     * @param int $expectedBattleVersion Версия боя.
     * @param int $expectedSheetVersion Версия листа.
     * @param array<string, mixed> $choice Защита.
     *
     * @return array{game: GameRecord, sessionId: int, replay: array<string, mixed>|null} Ход.
     */
    private function ready(
        int $gameId,
        int $actorUserId,
        bool $editAll,
        bool $viewAll,
        int $battleId,
        string $idempotencyKey,
        int $expectedBattleVersion,
        int $expectedSheetVersion,
        array $choice,
    ): array {
        return $this->ready->check($gameId, $actorUserId, $editAll, $viewAll, $idempotencyKey, [
            'battleId' => $battleId,
            'defense' => $choice,
            'expectedSheetVersion' => $expectedSheetVersion,
            'expectedVersion' => $expectedBattleVersion,
        ]);
    }

    /**
     * Закрывает удар и шлёт сигнал.
     *
     * @param GameRecord $game Игра.
     * @param int $sessionId Сессия.
     * @param int $battleId Бой.
     * @param string $idempotencyKey Ключ.
     * @param int $expectedBattleVersion Версия боя.
     * @param int $expectedSheetVersion Версия листа.
     * @param array<string, mixed> $choice Защита.
     * @param int $actorUserId Актор.
     * @param bool $editAll Ключ.
     * @param bool $viewAll Ключ.
     *
     * @return array<string, mixed> Итог.
     */
    private function close(
        GameRecord $game,
        int $sessionId,
        int $battleId,
        string $idempotencyKey,
        int $expectedBattleVersion,
        int $expectedSheetVersion,
        array $choice,
        int $actorUserId,
        bool $editAll,
        bool $viewAll,
    ): array {
        $result = $this->closeService->closeSingle(
            $game,
            $sessionId,
            $battleId,
            $idempotencyKey,
            $expectedBattleVersion,
            $expectedSheetVersion,
            $choice,
            $actorUserId,
            $editAll,
            $viewAll,
        );
        $this->announce->recorded($game->getId(), $idempotencyKey);

        return $result;
    }
}
