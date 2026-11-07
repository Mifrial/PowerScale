<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Closure;
use Mifrial\Roleplay\Game\Dto\GameBattleRecord;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Exception\GameBattleConflictException;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Repository\GameCommandRepository;

/**
 * Транзакция открытия широкого удара.
 */
final class GameWideTransaction
{
    /**
     * Создаёт транзакцию широкого удара.
     *
     * @param GameReplayTransaction $replay Граница replay.
     * @param GameCommandRepository $commands Журнал команд.
     * @param GameStrikeOpen $open Сервис открытия.
     *
     * @return void
     */
    public function __construct(
        private readonly GameReplayTransaction $replay,
        private readonly GameCommandRepository $commands,
        private readonly GameStrikeOpen $open,
    ) {
    }

    /**
     * Пишет открытие широкого удара внутри transaction.
     *
     * @param GameRecord $game Игра.
     * @param int $sessionId Сессия.
     * @param GameBattleRecord $battle Бой.
     * @param string $idempotencyKey Ключ.
     * @param int $expectedBattleVersion Версия.
     * @param array<string, mixed> $body Тело запроса.
     * @param array<string, mixed> $choice Выбор.
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
    ): array {
        $execution = $this->replay->execute(
            $this->lookup($game->getId(), $idempotencyKey),
            $this->reserve($game, $sessionId, $idempotencyKey, $body),
            $this->complete(),
            $this->work($game, $sessionId, $battle, $expectedBattleVersion, $choice),
            $body,
        );

        return $execution['result'];
    }

    /**
     * Поиск сохранённой команды.
     *
     * @param int $gameId Игра.
     * @param string $idempotencyKey Ключ.
     *
     * @return Closure Поиск.
     */
    private function lookup(int $gameId, string $idempotencyKey): Closure
    {
        return fn (): ?array => $this->commands->findByKey($gameId, 'wide_strike', $idempotencyKey);
    }

    /**
     * Резервирование ключа команды.
     *
     * @param GameRecord $game Игра.
     * @param int $sessionId Сессия.
     * @param string $idempotencyKey Ключ.
     * @param array<string, mixed> $body Тело.
     *
     * @return Closure Резервирование.
     */
    private function reserve(GameRecord $game, int $sessionId, string $idempotencyKey, array $body): Closure
    {
        return fn (): int => $this->commands->reserve(
            $game->getId(),
            $sessionId,
            'wide_strike',
            $idempotencyKey,
            $body,
        );
    }

    /**
     * Запись итога команды.
     *
     * @return Closure Завершение.
     */
    private function complete(): Closure
    {
        return function (int $reservationId, array $result): void {
            $this->commands->complete($reservationId, $result);
        };
    }

    /**
     * Изменения домена внутри transaction.
     *
     * @param GameRecord $game Игра.
     * @param int $sessionId Сессия.
     * @param GameBattleRecord $battle Бой.
     * @param int $expectedBattleVersion Версия.
     * @param array<string, mixed> $choice Выбор.
     *
     * @return Closure Работа.
     */
    private function work(
        GameRecord $game,
        int $sessionId,
        GameBattleRecord $battle,
        int $expectedBattleVersion,
        array $choice,
    ): Closure {
        return fn (): array => $this->open->openAction(
            $game,
            $sessionId,
            $battle,
            $expectedBattleVersion,
            $choice,
            'wide_strike',
            '1-n',
            'Game wide strike',
        );
    }
}
