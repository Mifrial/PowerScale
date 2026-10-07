<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Interface\Service;

use Mifrial\Roleplay\Game\Dto\GameInvitationRecord;
use Mifrial\Roleplay\Game\Dto\GameJoinRequestRecord;
use Mifrial\Roleplay\Game\Dto\GameMemberRecord;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;

/**
 * Приглашение, заявка и whitelist. Не строка игры.
 */
interface IGameAdmissions
{
    /**
     * Приглашает при invite_only.
     *
     * @param int $actorUserId Актор.
     * @param bool $editAll Ключ game.edit_all.
     * @param int $gameId Игра.
     * @param int $inviteeId Кого пригласили.
     *
     * @return GameInvitationRecord Pending.
     *
     * @throws GameNotFoundException Если нет права или учётки.
     * @throws GameInvalidException Если policy, владелец, повтор или completed.
     */
    public function invite(int $actorUserId, bool $editAll, int $gameId, int $inviteeId): GameInvitationRecord;

    /**
     * Приглашения игры.
     *
     * @param int $actorUserId Актор.
     * @param bool $editAll Ключ game.edit_all.
     * @param int $gameId Игра.
     *
     * @return list<GameInvitationRecord> Строки.
     *
     * @throws GameNotFoundException Если нет права.
     */
    public function getInvitations(int $actorUserId, bool $editAll, int $gameId): array;

    /**
     * Приглашения актора.
     *
     * @param int $actorUserId Актор.
     *
     * @return list<GameInvitationRecord> Строки.
     */
    public function getMyInvitations(int $actorUserId): array;

    /**
     * Принимает или отклоняет своё приглашение.
     *
     * @param int $actorUserId Актор.
     * @param int $invitationId Приглашение.
     * @param string $action accept или decline.
     *
     * @return GameInvitationRecord Строка.
     *
     * @throws GameNotFoundException Если чужое.
     * @throws GameInvalidException Если не pending или accept не создал участника.
     */
    public function respondInvitation(int $actorUserId, int $invitationId, string $action): GameInvitationRecord;

    /**
     * Заявка при anyone.
     *
     * @param int $actorUserId Актор.
     * @param bool $viewAll Ключ game.view_all.
     * @param int $gameId Игра.
     *
     * @return GameJoinRequestRecord Pending.
     *
     * @throws GameNotFoundException Если карточка скрыта.
     * @throws GameInvalidException Если policy не anyone.
     */
    public function requestJoin(int $actorUserId, bool $viewAll, int $gameId): GameJoinRequestRecord;

    /**
     * Заявки игры.
     *
     * @param int $actorUserId Актор.
     * @param bool $editAll Ключ game.edit_all.
     * @param bool $viewAll Ключ game.view_all.
     * @param int $gameId Игра.
     *
     * @return list<GameJoinRequestRecord> Строки.
     *
     * @throws GameNotFoundException Если нет права.
     */
    public function getJoinRequests(int $actorUserId, bool $editAll, bool $viewAll, int $gameId): array;

    /**
     * Принимает или отклоняет заявку.
     *
     * @param int $actorUserId Актор.
     * @param bool $editAll Ключ game.edit_all.
     * @param int $gameId Игра.
     * @param int $userId Заявитель.
     * @param string $action accept или decline.
     *
     * @return GameJoinRequestRecord Строка.
     *
     * @throws GameNotFoundException Если нет права или заявки.
     * @throws GameInvalidException Если policy уже не anyone.
     */
    public function respondJoinRequest(
        int $actorUserId,
        bool $editAll,
        int $gameId,
        int $userId,
        string $action,
    ): GameJoinRequestRecord;

    /**
     * Читает набор.
     *
     * @param int $actorUserId Актор.
     * @param bool $editAll Ключ game.edit_all.
     * @param int $gameId Игра.
     *
     * @return list<int> Id.
     *
     * @throws GameNotFoundException Если нет права.
     */
    public function getWhitelist(int $actorUserId, bool $editAll, int $gameId): array;

    /**
     * Заменяет набор.
     *
     * @param int $actorUserId Актор.
     * @param bool $editAll Ключ game.edit_all.
     * @param int $gameId Игра.
     * @param list<int> $userIds Id.
     *
     * @return list<int> Id.
     *
     * @throws GameNotFoundException Если нет права или учётки.
     * @throws GameInvalidException Если completed или дубль.
     */
    public function setWhitelist(int $actorUserId, bool $editAll, int $gameId, array $userIds): array;

    /**
     * Вступает по whitelist.
     *
     * @param int $actorUserId Актор.
     * @param int $gameId Игра.
     *
     * @return GameMemberRecord Участник player.
     *
     * @throws GameNotFoundException Если актора нет в наборе.
     * @throws GameInvalidException Если policy не whitelist.
     */
    public function joinWhitelist(int $actorUserId, int $gameId): GameMemberRecord;
}
