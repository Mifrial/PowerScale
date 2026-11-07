<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Dto\GameRecord;

/**
 * Открытие широкого удара.
 */
final class GameWideOpen
{
    /**
     * Создаёт открытие широкого удара.
     *
     * @param GameStrikeReady $ready Проверка запроса.
     * @param GameStrikeAccess $access Права.
     * @param GameStrikeBattleLookup $battles Бой.
     * @param GameWideOpening $opening Проверки открытия.
     * @param GameWideTransaction $transaction Транзакция.
     * @param GameStrikeAnnounce $announce Сигнал.
     *
     * @return void
     */
    public function __construct(
        private readonly GameStrikeReady $ready,
        private readonly GameStrikeAccess $access,
        private readonly GameStrikeBattleLookup $battles,
        private readonly GameWideOpening $opening,
        private readonly GameWideTransaction $transaction,
        private readonly GameStrikeAnnounce $announce,
    ) {
    }

    /**
     * Открывает широкий удар.
     *
     * @param int $gameId Игра.
     * @param int $actorUserId Актор.
     * @param bool $editAll Ключ game.edit_all.
     * @param bool $viewAll Ключ game.view_all.
     * @param int $battleId Бой.
     * @param string $idempotencyKey Ключ.
     * @param int $expectedBattleVersion Версия боя.
     * @param array<string, mixed> $choice Выбор.
     *
     * @return array<string, mixed> Итог.
     */
    public function declare(
        int $gameId,
        int $actorUserId,
        bool $editAll,
        bool $viewAll,
        int $battleId,
        string $idempotencyKey,
        int $expectedBattleVersion,
        array $choice,
    ): array {
        $body = $this->body($choice, $battleId, $expectedBattleVersion);
        $ready = $this->check($gameId, $actorUserId, $editAll, $viewAll, $idempotencyKey, $body);
        if ($ready['replay'] !== null) {
            return $ready['replay'];
        }

        $this->access->assertAttacker($ready['game'], $actorUserId, $editAll, $choice['attacker']);

        return $this->open($ready['game'], $ready['sessionId'], $battleId, $idempotencyKey, $expectedBattleVersion, $body, $choice);
    }

    /**
     * Видимость, повтор, ключ и сессия.
     *
     * @param int $gameId Игра.
     * @param int $actorUserId Актор.
     * @param bool $editAll Ключ game.edit_all.
     * @param bool $viewAll Ключ game.view_all.
     * @param string $idempotencyKey Ключ.
     * @param array<string, mixed> $body Тело.
     *
     * @return array{game: GameRecord, sessionId: int, replay: array<string, mixed>|null} Ход.
     */
    private function check(
        int $gameId,
        int $actorUserId,
        bool $editAll,
        bool $viewAll,
        string $idempotencyKey,
        array $body,
    ): array {
        return $this->ready->check(
            $gameId,
            $actorUserId,
            $editAll,
            $viewAll,
            $idempotencyKey,
            $body,
            null,
            'wide_strike',
            'Game wide strike is invalid',
        );
    }

    /**
     * Отпечаток запроса объявления.
     *
     * @param array<string, mixed> $choice Выбор.
     * @param int $battleId Бой.
     * @param int $expectedBattleVersion Версия.
     *
     * @return array<string, mixed> Тело.
     */
    private function body(array $choice, int $battleId, int $expectedBattleVersion): array
    {
        return [
            'attack' => $choice,
            'battleId' => $battleId,
            'expectedVersion' => $expectedBattleVersion,
        ];
    }

    /**
     * Пишет открытый удар и сигнализирует команду.
     *
     * @param GameRecord $game Игра.
     * @param int $sessionId Сессия.
     * @param int $battleId Бой.
     * @param string $idempotencyKey Ключ.
     * @param int $expectedBattleVersion Версия.
     * @param array<string, mixed> $body Тело.
     * @param array<string, mixed> $choice Выбор.
     *
     * @return array<string, mixed> Итог.
     */
    private function open(
        GameRecord $game,
        int $sessionId,
        int $battleId,
        string $idempotencyKey,
        int $expectedBattleVersion,
        array $body,
        array $choice,
    ): array {
        $battle = $this->battles->of($sessionId, $battleId, $expectedBattleVersion);
        $this->opening->assert($game, $battleId, $choice);
        $result = $this->transaction->run(
            $game,
            $sessionId,
            $battle,
            $idempotencyKey,
            $expectedBattleVersion,
            $body,
            $choice,
        );
        $this->announce->recorded($game->getId(), $idempotencyKey, 'wide_strike');

        return $result;
    }
}
