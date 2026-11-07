<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Dto\GameBattleRecord;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Exception\GameBattleConflictException;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Repository\GameCommandRepository;

/**
 * Транзакция открытия удара.
 */
final class GameStrikeOpenTransaction
{
    /**
     * Создаёт транзакцию открытия.
     *
     * @param GameReplayTransaction $replayTransaction Граница replay.
     * @param GameCommandRepository $commands Журнал команд.
     * @param GameStrikeOpen $open Сервис открытия.
     * @param GameStrikeOpenAnnouncement $announcement Объявление.
     *
     * @return void
     */
    public function __construct(
        private readonly GameReplayTransaction $replayTransaction,
        private readonly GameCommandRepository $commands,
        private readonly GameStrikeOpen $open,
        private readonly GameStrikeOpenAnnouncement $announcement,
    ) {
    }

    /**
     * Пишет открытие удара внутри transaction.
     *
     * @param GameRecord $game Игра.
     * @param int $sessionId Сессия.
     * @param GameBattleRecord $battle Бой.
     * @param string $idempotencyKey Ключ.
     * @param int $expectedBattleVersion Ожидаемая версия.
     * @param array<string, mixed> $body Отпечаток запроса.
     * @param array<string, mixed> $choice Подготовленный выбор.
     * @param int $actorUserId Актор.
     *
     * @return array<string, mixed> Итог.
     *
     * @throws GameInvalidException Если поле удара невалидно.
     * @throws GameBattleConflictException Если версия боя другая.
     */
    public function run(
        GameRecord $game,
        int $sessionId,
        GameBattleRecord $battle,
        string $idempotencyKey,
        int $expectedBattleVersion,
        array $body,
        array $choice,
        int $actorUserId,
    ): array {
        $execution = $this->replayTransaction->execute(
            fn (): ?array => $this->commands->findByKey($game->getId(), 'strike', $idempotencyKey),
            fn (): int => $this->commands->reserve(
                $game->getId(),
                $sessionId,
                'strike',
                $idempotencyKey,
                $body,
            ),
            function (int $reservationId, array $result): void {
                $this->commands->complete($reservationId, $result);
            },
            fn (): array => $this->write($game, $sessionId, $battle, $expectedBattleVersion, $choice, $actorUserId),
            $body,
        );

        return $execution['result'];
    }

    /**
     * Пишет удар и объявление внутри transaction.
     *
     * @param GameRecord $game Игра.
     * @param int $sessionId Сессия.
     * @param GameBattleRecord $battle Бой.
     * @param int $expectedBattleVersion Ожидаемая версия.
     * @param array<string, mixed> $choice Выбор.
     * @param int $actorUserId Актор.
     *
     * @return array<string, mixed> Итог.
     */
    private function write(
        GameRecord $game,
        int $sessionId,
        GameBattleRecord $battle,
        int $expectedBattleVersion,
        array $choice,
        int $actorUserId,
    ): array {
        $result = $this->open->openAction(
            $game,
            $sessionId,
            $battle,
            $expectedBattleVersion,
            $choice,
            'strike',
            '1-1',
            'Game strike',
        );
        $this->announcement->post($game, $battle, $choice, $result, $actorUserId);

        return $result;
    }
}
