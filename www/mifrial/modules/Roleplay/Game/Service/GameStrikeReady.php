<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Core\Kernel\Exception\ActionException;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Exception\GameBattleConflictException;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Game\Interface\Service\IGames;
use Mifrial\Roleplay\Game\Repository\GameCommandRepository;
use Mifrial\Roleplay\Game\Repository\GameSessionRepository;

/**
 * Общая проверка запроса удара: видимость, повтор, права и сессия.
 */
final class GameStrikeReady
{
    /**
     * Создаёт проверку запроса.
     *
     * @param IGames $games Игры.
     * @param GameStrikeAccess $access Права.
     * @param GameSessionRepository $sessions Сессии.
     * @param GameCommandRepository $commands Журнал команд.
     * @param GameReplayTransaction $replayTransaction Граница replay.
     *
     * @return void
     */
    public function __construct(
        private readonly IGames $games,
        private readonly GameStrikeAccess $access,
        private readonly GameSessionRepository $sessions,
        private readonly GameCommandRepository $commands,
        private readonly GameReplayTransaction $replayTransaction,
    ) {
    }

    /**
     * Видимость, повтор, право и сессия.
     *
     * @param int $gameId Игра.
     * @param int $actorUserId Актор.
     * @param bool $editAll Ключ.
     * @param bool $viewAll Ключ.
     * @param string $idempotencyKey Ключ.
     * @param array<string, mixed> $requestBody Тело.
     * @param array<string, mixed>|null $actingEntity Действующий персонаж или NPC.
     * @param string $source Источник команды.
     * @param string $invalid Текст ошибки невалидного запроса.
     *
     * @return array{game: GameRecord, sessionId: int, replay: array<string, mixed>|null} Ход.
     *
     * @throws ActionException AUTH_DENIED.
     * @throws GameNotFoundException Если карточки нет.
     * @throws GameInvalidException Если ключ, статус или сессия.
     * @throws GameBattleConflictException Если тело ключа чужое.
     */
    public function check(
        int $gameId,
        int $actorUserId,
        bool $editAll,
        bool $viewAll,
        string $idempotencyKey,
        array $requestBody,
        ?array $actingEntity = null,
        string $source = 'strike',
        string $invalid = 'Game strike is invalid',
    ): array {
        $game = $this->games->get($gameId);
        $this->access->assertVisible($game, $actorUserId, $viewAll);

        $replay = $this->findReplay($gameId, $idempotencyKey, $requestBody, $source);
        if ($replay !== null) {
            return ['game' => $game, 'sessionId' => 0, 'replay' => $replay];
        }

        $this->assertKey($game, $idempotencyKey, $invalid);
        if ($actingEntity !== null) {
            $this->access->assertAttacker($game, $actorUserId, $editAll, $actingEntity);
        }

        return ['game' => $game, 'sessionId' => $this->sessionId($gameId), 'replay' => null];
    }

    /**
     * Ключ и статус игры.
     *
     * @param GameRecord $game Игра.
     * @param string $idempotencyKey Ключ.
     * @param string $invalid Текст ошибки.
     *
     * @return void
     *
     * @throws GameInvalidException Если ключ пуст или игра завершена.
     */
    private function assertKey(GameRecord $game, string $idempotencyKey, string $invalid): void
    {
        if ($idempotencyKey === '' || $game->getStatus() === 'completed') {
            throw new GameInvalidException($invalid);
        }
    }

    /**
     * Текущая сессия игры.
     *
     * @param int $gameId Игра.
     *
     * @return int Сессия.
     *
     * @throws GameInvalidException Если сессии нет.
     */
    private function sessionId(int $gameId): int
    {
        $sessionId = $this->sessions->findSessionId($gameId);
        if ($sessionId === null) {
            throw new GameInvalidException('Game session is not running');
        }

        return $sessionId;
    }

    /**
     * Ищет сохранённый итог команды.
     *
     * @param int $gameId Игра.
     * @param string $idempotencyKey Ключ.
     * @param array<string, mixed> $requestBody Тело.
     * @param string $source Источник команды.
     *
     * @return array<string, mixed>|null Итог или null.
     *
     * @throws GameBattleConflictException Если тело отличается.
     * @throws GameInvalidException Если тело битое.
     */
    private function findReplay(int $gameId, string $idempotencyKey, array $requestBody, string $source): ?array
    {
        return $this->replayTransaction->find(
            fn (): ?array => $this->commands->findByKey($gameId, $source, $idempotencyKey),
            $requestBody,
        );
    }
}
