<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Dto\GameBattleRecord;
use Mifrial\Roleplay\Game\Dto\GameRecord;

/**
 * Приём ответов и закрытие широкого удара.
 */
final class GameWideResolve
{
    /**
     * Создаёт закрытие широкого удара.
     *
     * @param GameStrikeReady $ready Проверка запроса.
     * @param GameStrikeBattleLookup $battles Бой.
     * @param GameStrikeClose $closeService Закрытие удара.
     * @param GameStrikeAnnounce $announce Сигнал.
     *
     * @return void
     */
    public function __construct(
        private readonly GameStrikeReady $ready,
        private readonly GameStrikeBattleLookup $battles,
        private readonly GameStrikeClose $closeService,
        private readonly GameStrikeAnnounce $announce,
    ) {
    }

    /**
     * Принимает ответы за цели; последний необходимый ответ закрывает удар.
     *
     * @param int $gameId Игра.
     * @param int $actorUserId Актор.
     * @param bool $editAll Ключ game.edit_all.
     * @param bool $viewAll Ключ game.view_all.
     * @param int $battleId Бой.
     * @param string $idempotencyKey Ключ.
     * @param int $expectedBattleVersion Версия боя.
     * @param list<array<string, mixed>> $choices Выбор защиты.
     *
     * @return array<string, mixed> Итог.
     */
    public function run(
        int $gameId,
        int $actorUserId,
        bool $editAll,
        bool $viewAll,
        int $battleId,
        string $idempotencyKey,
        int $expectedBattleVersion,
        array $choices,
    ): array {
        $body = ['battleId' => $battleId, 'defense' => $choices, 'expectedVersion' => $expectedBattleVersion];
        $ready = $this->check($gameId, $actorUserId, $editAll, $viewAll, $idempotencyKey, $body);
        if ($ready['replay'] !== null) {
            return $ready['replay'];
        }

        $battle = $this->battles->of($ready['sessionId'], $battleId, $expectedBattleVersion);
        $result = $this->close($ready['game'], $ready['sessionId'], $battle, $idempotencyKey, $expectedBattleVersion, $choices, $actorUserId, $editAll, $viewAll);
        $this->announce->recorded($ready['game']->getId(), $idempotencyKey, 'wide_strike');

        return $result;
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
     * Закрывает широкий удар.
     *
     * @param GameRecord $game Игра.
     * @param int $sessionId Сессия.
     * @param GameBattleRecord $battle Бой.
     * @param string $idempotencyKey Ключ.
     * @param int $expectedBattleVersion Версия.
     * @param list<array<string, mixed>> $choices Выбор защиты.
     * @param int $actorUserId Актор.
     * @param bool $editAll Ключ game.edit_all.
     * @param bool $viewAll Ключ game.view_all.
     *
     * @return array<string, mixed> Итог.
     */
    private function close(
        GameRecord $game,
        int $sessionId,
        GameBattleRecord $battle,
        string $idempotencyKey,
        int $expectedBattleVersion,
        array $choices,
        int $actorUserId,
        bool $editAll,
        bool $viewAll,
    ): array {
        return $this->closeService->closeWide(
            $game,
            $sessionId,
            $battle,
            $idempotencyKey,
            $expectedBattleVersion,
            $choices,
            $actorUserId,
            $editAll,
            $viewAll,
        );
    }
}
