<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Closure;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Exception\GameBattleConflictException;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Game\Repository\GameCommandRepository;

/**
 * Конверт команды хода: видимость, сессия, replay и доставка.
 */
final class GameTurnEnvelope
{
    /**
     * Создаёт конверт.
     *
     * @param GameTurnAccess $access Права и сессия.
     * @param GameCommandRepository $commands Команды.
     * @param GameReplayTransaction $replay Граница replay.
     * @param GameDeliverySignal $delivery Сигнал доставки.
     *
     * @return void
     */
    public function __construct(
        private readonly GameTurnAccess $access,
        private readonly GameCommandRepository $commands,
        private readonly GameReplayTransaction $replay,
        private readonly GameDeliverySignal $delivery,
    ) {
    }

    /**
     * Выполняет команду хода с повтором и одной транзакцией.
     *
     * @param int $gameId Игра.
     * @param int $actorUserId Актор.
     * @param bool $viewAll Ключ game.view_all.
     * @param array<string, mixed> $body Тело.
     * @param string $kind Вид команды.
     * @param Closure $work Работа внутри транзакции.
     * @param bool $notify Отправлять ли сигнал доставки.
     *
     * @return array<string, mixed> Итог.
     *
     * @throws GameInvalidException Если ключ или сессии нет.
     * @throws GameNotFoundException Если игра скрыта.
     * @throws GameBattleConflictException Если тело ключа другое.
     */
    public function execute(
        int $gameId,
        int $actorUserId,
        bool $viewAll,
        array $body,
        string $kind,
        Closure $work,
        bool $notify,
    ): array {
        $game = $this->access->visible($gameId, $actorUserId, $viewAll);
        $sessionId = $this->access->session($gameId);
        $execution = $this->replay->execute(
            fn (): ?array => $this->commands->findByKey($gameId, $kind, $this->key($body)),
            fn (): int => $this->commands->reserve($gameId, $sessionId, $kind, $this->key($body), $body),
            function (int $reservationId, array $result): void {
                $this->commands->complete($reservationId, $result);
            },
            fn (): array => $work($game, $sessionId),
            $body,
        );
        if ($notify) {
            $this->deliver($gameId, $kind, $body, $execution);
        }

        return $execution['result'];
    }

    /**
     * Сигнал доставки после первой записи.
     *
     * @param int $gameId Игра.
     * @param string $kind Вид команды.
     * @param array<string, mixed> $body Тело.
     * @param array{result: array<string, mixed>, replay: bool} $execution Итог replay.
     *
     * @return void
     *
     * @throws GameInvalidException Если ключ битый.
     */
    private function deliver(int $gameId, string $kind, array $body, array $execution): void
    {
        if ($execution['replay']) {
            return;
        }

        $stored = $this->commands->findByKey($gameId, $kind, $this->key($body));
        if ($stored !== null) {
            $this->delivery->recorded($gameId, $kind, $stored['id'], []);
        }
    }

    /**
     * Ключ идемпотентности тела.
     *
     * @param array<string, mixed> $body Тело.
     *
     * @return string Ключ.
     *
     * @throws GameInvalidException Если ключа нет.
     */
    private function key(array $body): string
    {
        $key = $body['idempotencyKey'] ?? null;
        if (!is_string($key) || $key === '') {
            throw new GameInvalidException('Game turn field is invalid');
        }

        return $key;
    }
}
