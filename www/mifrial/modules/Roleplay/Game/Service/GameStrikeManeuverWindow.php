<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;

/**
 * Решение окна раскрытия манёвра.
 */
final class GameStrikeManeuverWindow
{
    /**
     * Создаёт окно манёвра.
     *
     * @param GameStrikeAccess $access Права.
     * @param GameStrikeBattleLookup $battles Бой.
     * @param GameStrikeManeuver $maneuver Манёвр.
     * @param GameCheckRoll $checkRoll Бросок проверки.
     * @param GameStrikeClose $closeService Закрытие удара.
     *
     * @return void
     */
    public function __construct(
        private readonly GameStrikeAccess $access,
        private readonly GameStrikeBattleLookup $battles,
        private readonly GameStrikeManeuver $maneuver,
        private readonly GameCheckRoll $checkRoll,
        private readonly GameStrikeClose $closeService,
    ) {
    }

    /**
     * Принимает решение в окне раскрытия манёвра.
     *
     * @param GameRecord $game Игра.
     * @param int $sessionId Сессия.
     * @param int $battleId Бой.
     * @param string $idempotencyKey Ключ.
     * @param int $expectedBattleVersion Версия боя.
     * @param array<string, mixed> $row Строка удара.
     * @param array<string, mixed> $window Решение.
     * @param int $actorUserId Актор.
     * @param bool $editAll Ключ.
     * @param bool $viewAll Ключ.
     *
     * @return array<string, mixed> Итог.
     *
     * @throws GameInvalidException Если актор или выбор чужие.
     */
    public function resume(
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
        $this->assertActor($game, $actorUserId, $editAll, $window);
        $battle = $this->battles->of($sessionId, $battleId, $expectedBattleVersion);
        $result = $this->maneuver->resolve(
            $game,
            $battle,
            $row,
            $window,
            $expectedBattleVersion,
            $this->checkRoll,
        );

        return $this->finish($game, $sessionId, $battleId, $idempotencyKey, $result, $actorUserId, $editAll, $viewAll);
    }

    /**
     * Закрывает удар, если манёвр завершился.
     *
     * @param GameRecord $game Игра.
     * @param int $sessionId Сессия.
     * @param int $battleId Бой.
     * @param string $idempotencyKey Ключ.
     * @param array<string, mixed> $result Итог манёвра.
     * @param int $actorUserId Актор.
     * @param bool $editAll Ключ.
     * @param bool $viewAll Ключ.
     *
     * @return array<string, mixed> Итог.
     */
    private function finish(
        GameRecord $game,
        int $sessionId,
        int $battleId,
        string $idempotencyKey,
        array $result,
        int $actorUserId,
        bool $editAll,
        bool $viewAll,
    ): array {
        if (!is_array($result['finish'] ?? null)) {
            return $result;
        }

        return $this->closeService->resumeSingle(
            $game,
            $sessionId,
            $battleId,
            $idempotencyKey,
            (int) $result['version'],
            ['maneuver' => $result['finish']],
            $actorUserId,
            $editAll,
            $viewAll,
        );
    }

    /**
     * Проверяет актора решения.
     *
     * @param GameRecord $game Игра.
     * @param int $actorUserId Актор.
     * @param bool $editAll Ключ.
     * @param array<string, mixed> $window Решение.
     *
     * @return void
     *
     * @throws GameInvalidException Если актор битый или чужой.
     */
    private function assertActor(GameRecord $game, int $actorUserId, bool $editAll, array $window): void
    {
        $actor = $window['actor'] ?? null;
        if (
            !is_array($actor)
            || !is_string($actor['type'] ?? null)
            || !is_int($actor['id'] ?? null)
        ) {
            throw new GameInvalidException('Game maneuver actor is invalid');
        }

        $this->access->assertTarget($game, $actorUserId, $editAll, [
            'type' => $actor['type'],
            'id' => $actor['id'],
        ]);
    }
}
