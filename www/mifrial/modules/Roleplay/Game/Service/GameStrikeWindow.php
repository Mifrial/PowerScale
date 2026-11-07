<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;

/**
 * Решение окна удара: манёвр, одна цель или широкий удар.
 */
final class GameStrikeWindow
{
    /**
     * Создаёт окно удара.
     *
     * @param GameStrikeReady $ready Общая проверка запроса.
     * @param GameStrikeAccess $access Права.
     * @param GameStrikeWindowLookup $lookup Открытая строка.
     * @param GameStrikeManeuverWindow $maneuver Окно манёвра.
     * @param GameStrikeWindowResume $resume Продолжение удара.
     * @param GameStrikeAnnounce $announce Сигнал.
     *
     * @return void
     */
    public function __construct(
        private readonly GameStrikeReady $ready,
        private readonly GameStrikeAccess $access,
        private readonly GameStrikeWindowLookup $lookup,
        private readonly GameStrikeManeuverWindow $maneuver,
        private readonly GameStrikeWindowResume $resume,
        private readonly GameStrikeAnnounce $announce,
    ) {
    }

    /**
     * Продолжает удар после выбора фактической цели.
     *
     * @param int $gameId Игра.
     * @param int $actorUserId Актор.
     * @param bool $editAll Ключ game.edit_all.
     * @param bool $viewAll Ключ game.view_all.
     * @param int $battleId Бой.
     * @param string $idempotencyKey Ключ.
     * @param int $expectedBattleVersion Версия боя.
     * @param array<string, mixed> $window Решение окна.
     *
     * @return array<string, mixed> Итог.
     *
     * @throws GameInvalidException Если окно закрыто или выбор чужой.
     */
    public function resolve(
        int $gameId,
        int $actorUserId,
        bool $editAll,
        bool $viewAll,
        int $battleId,
        string $idempotencyKey,
        int $expectedBattleVersion,
        array $window,
    ): array {
        $body = [
            'battleId' => $battleId,
            'window' => $window,
            'expectedVersion' => $expectedBattleVersion,
        ];
        $ready = $this->ready->check($gameId, $actorUserId, $editAll, $viewAll, $idempotencyKey, $body);
        if ($ready['replay'] !== null) {
            return $ready['replay'];
        }

        $outcome = $this->dispatch($ready['game'], $ready['sessionId'], $battleId, $idempotencyKey, $expectedBattleVersion, $window, $actorUserId, $editAll, $viewAll);
        $this->announce->window($gameId, $ready['sessionId'], $idempotencyKey, $body, $outcome['result'], $outcome['source']);

        return $outcome['result'];
    }

    /**
     * Выбирает ветку решения окна.
     *
     * @param GameRecord $game Игра.
     * @param int $sessionId Сессия.
     * @param int $battleId Бой.
     * @param string $idempotencyKey Ключ.
     * @param int $expectedBattleVersion Версия боя.
     * @param array<string, mixed> $window Решение.
     * @param int $actorUserId Актор.
     * @param bool $editAll Ключ.
     * @param bool $viewAll Ключ.
     *
     * @return array{source: string, result: array<string, mixed>} Итог.
     */
    private function dispatch(
        GameRecord $game,
        int $sessionId,
        int $battleId,
        string $idempotencyKey,
        int $expectedBattleVersion,
        array $window,
        int $actorUserId,
        bool $editAll,
        bool $viewAll,
    ): array {
        $opened = $this->lookup->open($battleId);
        if (!$opened['wide'] && $this->lookup->isManeuverOpen($opened['strikes'][0])) {
            return $this->maneuverOutcome($game, $sessionId, $battleId, $idempotencyKey, $expectedBattleVersion, $opened['strikes'][0], $window, $actorUserId, $editAll, $viewAll);
        }

        $this->access->assertAttacker($game, $actorUserId, $editAll, $this->attackerOf($opened['open']));

        return $opened['wide']
            ? $this->wideOutcome($game, $sessionId, $battleId, $idempotencyKey, $expectedBattleVersion, $opened['strikes'], $window, $actorUserId, $editAll, $viewAll)
            : $this->singleOutcome($game, $sessionId, $battleId, $idempotencyKey, $expectedBattleVersion, $opened['strikes'][0], $window, $actorUserId, $editAll, $viewAll);
    }

    /**
     * Итог окна манёвра.
     *
     * @param GameRecord $game Игра.
     * @param int $sessionId Сессия.
     * @param int $battleId Бой.
     * @param string $idempotencyKey Ключ.
     * @param int $expectedBattleVersion Версия боя.
     * @param array<string, mixed> $row Строка.
     * @param array<string, mixed> $window Решение.
     * @param int $actorUserId Актор.
     * @param bool $editAll Ключ.
     * @param bool $viewAll Ключ.
     *
     * @return array{source: string, result: array<string, mixed>} Итог.
     */
    private function maneuverOutcome(
        GameRecord $game,
        int $sessionId,
        int $battleId,
        string $idempotencyKey,
        int $expectedBattleVersion,
        array $row,
        array $window,
        int $actorUserId,
        bool $editAll,
        bool $viewAll,
    ): array {
        return ['source' => 'strike_window', 'result' => $this->maneuver->resume(
            $game,
            $sessionId,
            $battleId,
            $idempotencyKey,
            $expectedBattleVersion,
            $row,
            $window,
            $actorUserId,
            $editAll,
            $viewAll,
        )];
    }

    /**
     * Итог широкого окна.
     *
     * @param GameRecord $game Игра.
     * @param int $sessionId Сессия.
     * @param int $battleId Бой.
     * @param string $idempotencyKey Ключ.
     * @param int $expectedBattleVersion Версия боя.
     * @param list<array<string, mixed>> $strikes Строки.
     * @param array<string, mixed> $window Решение.
     * @param int $actorUserId Актор.
     * @param bool $editAll Ключ.
     * @param bool $viewAll Ключ.
     *
     * @return array{source: string, result: array<string, mixed>} Итог.
     */
    private function wideOutcome(
        GameRecord $game,
        int $sessionId,
        int $battleId,
        string $idempotencyKey,
        int $expectedBattleVersion,
        array $strikes,
        array $window,
        int $actorUserId,
        bool $editAll,
        bool $viewAll,
    ): array {
        $rowKey = is_string($window['row'] ?? null) ? $window['row'] : '';
        $target = is_string($window['actualTarget'] ?? null) ? $window['actualTarget'] : '';

        return ['source' => 'wide_window', 'result' => $this->resume->wide(
            $game,
            $sessionId,
            $battleId,
            $idempotencyKey,
            $expectedBattleVersion,
            $strikes,
            $rowKey,
            $target,
            $window,
            $actorUserId,
            $editAll,
            $viewAll,
        )];
    }

    /**
     * Итог одиночного окна.
     *
     * @param GameRecord $game Игра.
     * @param int $sessionId Сессия.
     * @param int $battleId Бой.
     * @param string $idempotencyKey Ключ.
     * @param int $expectedBattleVersion Версия боя.
     * @param array<string, mixed> $row Строка.
     * @param array<string, mixed> $window Решение.
     * @param int $actorUserId Актор.
     * @param bool $editAll Ключ.
     * @param bool $viewAll Ключ.
     *
     * @return array{source: string, result: array<string, mixed>} Итог.
     */
    private function singleOutcome(
        GameRecord $game,
        int $sessionId,
        int $battleId,
        string $idempotencyKey,
        int $expectedBattleVersion,
        array $row,
        array $window,
        int $actorUserId,
        bool $editAll,
        bool $viewAll,
    ): array {
        $target = is_string($window['actualTarget'] ?? null) ? $window['actualTarget'] : '';

        return ['source' => 'strike_window', 'result' => $this->resume->single(
            $game,
            $sessionId,
            $battleId,
            $idempotencyKey,
            $expectedBattleVersion,
            $row,
            $target,
            $actorUserId,
            $editAll,
            $viewAll,
        )];
    }

    /**
     * Атакующий открытой строки.
     *
     * @param array<string, mixed> $open Открытая строка.
     *
     * @return array{type: string, id: int} Атакующий.
     *
     * @throws GameInvalidException Если строка битая.
     */
    private function attackerOf(array $open): array
    {
        $type = $open['attacker_kind'] ?? null;
        $id = $open['attacker_id'] ?? null;
        if (!is_string($type) || !is_int($id)) {
            throw new GameInvalidException('Game strike window is not open');
        }

        return ['type' => $type, 'id' => $id];
    }
}
