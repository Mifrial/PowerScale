<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Closure;
use Mifrial\Core\Kernel\Exception\ActionException;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Exception\GameBattleConflictException;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;

/**
 * Конец хода, продолжение и отмена долга.
 */
final class GameTurns
{
    /**
     * Создаёт фасад.
     *
     * @param GameTurnEnvelope $envelope Конверт команды.
     * @param GameTurnScenario $scenario Подготовка хода.
     * @param GameTurnEnd $end Конец хода.
     * @param GameTurnContinue $continue Продолжение долга.
     * @param GameTurnAbort $abort Отмена долга.
     * @param GameTurnProcessAbort $abortProcess Прерывание процесса.
     *
     * @return void
     */
    public function __construct(
        private readonly GameTurnEnvelope $envelope,
        private readonly GameTurnScenario $scenario,
        private readonly GameTurnEnd $end,
        private readonly GameTurnContinue $continue,
        private readonly GameTurnAbort $abort,
        private readonly GameTurnProcessAbort $abortProcess,
    ) {
    }

    /**
     * Конец хода текущего.
     *
     * @param int $gameId Игра.
     * @param int $actorUserId Актор.
     * @param bool $editAll Право.
     * @param bool $viewAll Просмотр.
     * @param array<string, mixed> $body Тело.
     *
     * @return array<string, mixed> Итог.
     *
     * @throws GameInvalidException Если ключ, поля или порядок битые.
     * @throws GameNotFoundException Если игра или бой скрыты.
     * @throws ActionException AUTH_DENIED.
     * @throws GameBattleConflictException Если версия или тело ключа чужие.
     */
    public function end(
        int $gameId,
        int $actorUserId,
        bool $editAll,
        bool $viewAll,
        array $body,
    ): array {
        return $this->turn(
            $gameId,
            $actorUserId,
            $editAll,
            $viewAll,
            $body,
            fn (GameRecord $game, array $prepared): array => $this->end->end(
                $game,
                $prepared['battle'],
                $prepared['expectedBattle'],
                $prepared['expectedSheet'],
                $prepared['current'],
                $actorUserId,
            ),
        );
    }

    /**
     * Доплата долга текущего.
     *
     * @param int $gameId Игра.
     * @param int $actorUserId Актор.
     * @param bool $editAll Право.
     * @param bool $viewAll Просмотр.
     * @param array<string, mixed> $body Тело.
     *
     * @return array<string, mixed> Итог.
     *
     * @throws GameInvalidException Если ключ, поля или долг битые.
     * @throws GameNotFoundException Если игра, бой или лист скрыты.
     * @throws ActionException AUTH_DENIED.
     * @throws GameBattleConflictException Если версия или тело ключа чужие.
     */
    public function continueAction(
        int $gameId,
        int $actorUserId,
        bool $editAll,
        bool $viewAll,
        array $body,
    ): array {
        return $this->turn(
            $gameId,
            $actorUserId,
            $editAll,
            $viewAll,
            $body,
            fn (GameRecord $game, array $prepared): array => $this->continue->continue(
                $game,
                $prepared['sessionId'],
                $prepared['battle'],
                $prepared['expectedBattle'],
                $prepared['expectedSheet'],
                $prepared['current'],
                $actorUserId,
                $editAll,
                $viewAll,
            ),
        );
    }

    /**
     * Снимает долг текущего.
     *
     * @param int $gameId Игра.
     * @param int $actorUserId Актор.
     * @param bool $editAll Право.
     * @param bool $viewAll Просмотр.
     * @param array<string, mixed> $body Тело.
     *
     * @return array<string, mixed> Итог.
     *
     * @throws GameInvalidException Если ключ, поля или порядок битые.
     * @throws GameNotFoundException Если игра или бой скрыты.
     * @throws ActionException AUTH_DENIED.
     * @throws GameBattleConflictException Если версия или тело ключа чужие.
     */
    public function abort(
        int $gameId,
        int $actorUserId,
        bool $editAll,
        bool $viewAll,
        array $body,
    ): array {
        return $this->turn(
            $gameId,
            $actorUserId,
            $editAll,
            $viewAll,
            $body,
            fn (GameRecord $game, array $prepared): array => $this->abort->abort(
                $prepared['battle'],
                $prepared['expectedBattle'],
                $prepared['current'],
            ),
        );
    }

    /**
     * Прерывает процесс текущего.
     *
     * @param int $gameId Игра.
     * @param int $actorUserId Актор.
     * @param bool $editAll Право.
     * @param bool $viewAll Просмотр.
     * @param array<string, mixed> $body Тело.
     *
     * @return array<string, mixed> Итог.
     *
     * @throws GameInvalidException Если ключ, поля или процесс не позволяют.
     * @throws GameNotFoundException Если игра или бой скрыты.
     * @throws ActionException AUTH_DENIED.
     * @throws GameBattleConflictException Если версия или тело ключа чужие.
     */
    public function abortProcess(
        int $gameId,
        int $actorUserId,
        bool $editAll,
        bool $viewAll,
        array $body,
    ): array {
        return $this->envelope->execute(
            $gameId, $actorUserId, $viewAll, $body, 'abort_process',
            fn (GameRecord $game, int $sessionId): array => $this->abortProcess->abort(
                $game,
                $sessionId,
                $actorUserId,
                $editAll,
                $body,
            ),
            false,
        );
    }

    /**
     * Общая команда хода: подготовка и сценарий.
     *
     * @param int $gameId Игра.
     * @param int $actorUserId Актор.
     * @param bool $editAll Право.
     * @param bool $viewAll Просмотр.
     * @param array<string, mixed> $body Тело.
     * @param Closure $work Сценарий по подготовке.
     *
     * @return array<string, mixed> Итог.
     *
     * @throws GameInvalidException Если ключ, поля или порядок битые.
     * @throws GameNotFoundException Если игра или бой скрыты.
     * @throws ActionException AUTH_DENIED.
     * @throws GameBattleConflictException Если версия или тело ключа чужие.
     */
    private function turn(
        int $gameId,
        int $actorUserId,
        bool $editAll,
        bool $viewAll,
        array $body,
        Closure $work,
    ): array {
        return $this->envelope->execute(
            $gameId, $actorUserId, $viewAll, $body, 'turn',
            function (GameRecord $game, int $sessionId) use ($actorUserId, $editAll, $body, $work): array {
                $prepared = $this->scenario->prepare($game, $sessionId, $actorUserId, $editAll, $body);

                return $work($game, $prepared);
            },
            true,
        );
    }
}
