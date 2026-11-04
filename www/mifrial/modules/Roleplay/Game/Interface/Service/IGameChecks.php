<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Interface\Service;

use Mifrial\Core\Kernel\Exception\ActionException;
use Mifrial\Roleplay\Game\Exception\GameBattleConflictException;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;

/**
 * Соло-проверка и pairwise одной цели.
 */
interface IGameChecks
{
    /**
     * Считает соло и закрывает process.
     *
     * @param int $gameId Игра.
     * @param int $actorUserId Актор.
     * @param bool $editAll Ключ game.edit_all.
     * @param bool $viewAll Ключ game.view_all.
     * @param int|null $battleId Бой или null.
     * @param string $idempotencyKey Ключ.
     * @param int $expectedSheetVersion Версия листа.
     * @param array $check Решение.
     *
     * @return array<string, mixed> Итог.
     *
     * @throws ActionException AUTH_DENIED.
     * @throws GameNotFoundException Если карточки или участника нет.
     * @throws GameInvalidException Если правило или сессия.
     * @throws GameBattleConflictException Если версия или чужой ключ.
     */
    public function declareCheck(
        int $gameId,
        int $actorUserId,
        bool $editAll,
        bool $viewAll,
        ?int $battleId,
        string $idempotencyKey,
        int $expectedSheetVersion,
        array $check,
    ): array;

    /**
     * Открывает предложение без броска.
     *
     * @param int $gameId Игра.
     * @param int $actorUserId Актор.
     * @param bool $editAll Ключ game.edit_all.
     * @param bool $viewAll Ключ game.view_all.
     * @param int|null $battleId Бой или null.
     * @param string $idempotencyKey Ключ.
     * @param int $expectedSheetVersion Версия листа.
     * @param array $proposal Решение.
     *
     * @return array<string, mixed> Итог.
     *
     * @throws ActionException AUTH_DENIED.
     * @throws GameNotFoundException Если карточки или участника нет.
     * @throws GameInvalidException Если правило или сессия.
     * @throws GameBattleConflictException Если версия или чужой ключ.
     */
    public function proposeCheck(
        int $gameId,
        int $actorUserId,
        bool $editAll,
        bool $viewAll,
        ?int $battleId,
        string $idempotencyKey,
        int $expectedSheetVersion,
        array $proposal,
    ): array;

    /**
     * Правит блок своей стороны.
     *
     * @param int $gameId Игра.
     * @param int $actorUserId Актор.
     * @param bool $editAll Ключ game.edit_all.
     * @param bool $viewAll Ключ game.view_all.
     * @param int $processId Process.
     * @param string $idempotencyKey Ключ.
     * @param int $expectedSheetVersion Версия листа.
     * @param array $proposal Предложение.
     *
     * @return array<string, mixed> Итог.
     *
     * @throws ActionException AUTH_DENIED.
     * @throws GameNotFoundException Если process чужой.
     * @throws GameInvalidException Если статус не open.
     * @throws GameBattleConflictException Если версия или чужой ключ.
     */
    public function reviseCheck(
        int $gameId,
        int $actorUserId,
        bool $editAll,
        bool $viewAll,
        int $processId,
        string $idempotencyKey,
        int $expectedSheetVersion,
        array $proposal,
    ): array;

    /**
     * Отменяет предложение.
     *
     * @param int $gameId Игра.
     * @param int $actorUserId Актор.
     * @param bool $editAll Ключ game.edit_all.
     * @param bool $viewAll Ключ game.view_all.
     * @param int $processId Process.
     * @param string $idempotencyKey Ключ.
     * @param array $proposal Тело с actor.
     *
     * @return array<string, mixed> Итог.
     *
     * @throws ActionException AUTH_DENIED.
     * @throws GameNotFoundException Если process чужой.
     * @throws GameInvalidException Если статус не open.
     * @throws GameBattleConflictException Если версия или чужой ключ.
     */
    public function cancelCheck(
        int $gameId,
        int $actorUserId,
        bool $editAll,
        bool $viewAll,
        int $processId,
        string $idempotencyKey,
        array $proposal,
    ): array;

    /**
     * Ожидающие предложения.
     *
     * @param int $gameId Игра.
     * @param int $actorUserId Актор.
     * @param bool $viewAll Ключ game.view_all.
     * @param array{type: string, id: int}|null $entity Участник или null.
     *
     * @return array<int, array<string, mixed>> Предложения.
     *
     * @throws GameNotFoundException Если карточка скрыта.
     */
    public function checkOffers(int $gameId, int $actorUserId, bool $viewAll, ?array $entity = null): array;

    /**
     * Принимает или отклоняет предложение.
     *
     * @param int $gameId Игра.
     * @param int $actorUserId Актор.
     * @param bool $editAll Ключ game.edit_all.
     * @param bool $viewAll Ключ game.view_all.
     * @param int $processId Process.
     * @param string $idempotencyKey Ключ.
     * @param int $expectedSheetVersion Версия листа.
     * @param array $answer Решение.
     *
     * @return array<string, mixed> Итог.
     *
     * @throws ActionException AUTH_DENIED.
     * @throws GameNotFoundException Если process чужой.
     * @throws GameInvalidException Если статус не open.
     * @throws GameBattleConflictException Если версия или чужой ключ.
     */
    public function answerCheck(
        int $gameId,
        int $actorUserId,
        bool $editAll,
        bool $viewAll,
        int $processId,
        string $idempotencyKey,
        int $expectedSheetVersion,
        array $answer,
    ): array;
}
