<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Core\Kernel\Value\DateTime;
use Mifrial\Core\User\Exception\UserNotFoundException;
use Mifrial\Core\User\Interface\Service\IUserAccounts;
use Mifrial\Roleplay\Game\Dto\GameInvitationRecord;
use Mifrial\Roleplay\Game\Dto\GameJoinRequestRecord;
use Mifrial\Roleplay\Game\Dto\GameMemberRecord;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Dto\NewGameMember;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Game\Interface\Service\IGameAdmissions;
use Mifrial\Roleplay\Game\Interface\Service\IGames;
use Mifrial\Roleplay\Game\Repository\GameInvitationRepository;
use Mifrial\Roleplay\Game\Repository\GameJoinRequestRepository;
use Mifrial\Roleplay\Game\Repository\GameRepository;

/**
 * Приглашение, заявка и whitelist.
 */
final class GameAdmissions implements IGameAdmissions // phpcs:ignore MifrialCodingStandard.Metrics.ClassQuality.TooManyPublicMethods,MifrialCodingStandard.Metrics.ClassQuality.ClassComplexityTooHigh,MifrialCodingStandard.Metrics.ClassQuality.ClassTooLong -- конструктор входит в счётчик; десять действий одного порта G8.
{
    /**
     * Создаёт сценарий.
     *
     * @param IGames $games Фасад игры.
     * @param IUserAccounts $userAccounts Учётки.
     * @param GameRepository $gameRepository Запись whitelist.
     * @param GameInvitationRepository $invitationRepository Приглашения.
     * @param GameJoinRequestRepository $joinRequestRepository Заявки.
     * @param GameCardAccess $cardAccess Фильтр карточки.
     *
     * @return void
     */
    public function __construct(
        private readonly IGames $games,
        private readonly IUserAccounts $userAccounts,
        private readonly GameRepository $gameRepository,
        private readonly GameInvitationRepository $invitationRepository,
        private readonly GameJoinRequestRepository $joinRequestRepository,
        private readonly GameCardAccess $cardAccess,
    ) {
    }

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
     * @throws GameInvalidException Если policy, владелец или повтор.
     */
    public function invite(int $actorUserId, bool $editAll, int $gameId, int $inviteeId): GameInvitationRecord
    {
        $game = $this->requireOpen($gameId);
        $this->requirePolicy($game, 'invite_only');
        $this->assertStaff($game, $actorUserId, $editAll);
        $this->assertJoinable($game, $inviteeId);
        $this->assertNoPendingInvitation($gameId, $inviteeId);
        $invitationId = $this->invitationRepository->add($gameId, $actorUserId, $inviteeId, DateTime::now());

        return $this->invitationRepository->getById($invitationId);
    }

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
    public function getInvitations(int $actorUserId, bool $editAll, int $gameId): array
    {
        $game = $this->games->get($gameId);
        $this->assertStaff($game, $actorUserId, $editAll);

        return $this->invitationRepository->getListByGame($gameId);
    }

    /**
     * Приглашения актора.
     *
     * @param int $actorUserId Актор.
     *
     * @return list<GameInvitationRecord> Строки.
     */
    public function getMyInvitations(int $actorUserId): array
    {
        return $this->invitationRepository->getListByInvitee($actorUserId);
    }

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
     * @throws GameInvalidException Если не pending.
     */
    public function respondInvitation(int $actorUserId, int $invitationId, string $action): GameInvitationRecord
    {
        $invitation = $this->invitationRepository->getById($invitationId);
        if ($invitation->getInviteeId() !== $actorUserId) {
            throw new GameNotFoundException();
        }

        $this->assertPending($invitation->getStatus(), $action);
        $game = $this->requireOpen($invitation->getGameId());
        if ($action === 'accept') {
            $this->requirePolicy($game, 'invite_only');
            $this->addPlayer($game->getId(), $actorUserId);
        }

        $status = $action === 'accept' ? 'accepted' : 'declined';
        $this->invitationRepository->saveStatus($invitation->getId(), $status, DateTime::now());

        return $this->invitationRepository->getById($invitation->getId());
    }

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
    public function requestJoin(int $actorUserId, bool $viewAll, int $gameId): GameJoinRequestRecord
    {
        $game = $this->requireOpen($gameId);
        $this->requirePolicy($game, 'anyone');
        if (!$this->cardAccess->isVisible($game, $actorUserId, $viewAll)) {
            throw new GameNotFoundException();
        }

        $this->assertJoinable($game, $actorUserId);
        if ($this->joinRequestRepository->findPending($gameId, $actorUserId) !== null) {
            throw new GameInvalidException('Game join request is already pending');
        }

        $requestId = $this->joinRequestRepository->add($gameId, $actorUserId, DateTime::now());
        $pending = $this->joinRequestRepository->findPending($gameId, $actorUserId);
        if ($pending === null || $pending->getId() !== $requestId) {
            throw new GameInvalidException('Game join request row is invalid');
        }

        return $pending;
    }

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
    public function getJoinRequests(int $actorUserId, bool $editAll, bool $viewAll, int $gameId): array
    {
        $game = $this->games->get($gameId);
        $allowed = $game->getOwnerId() === $actorUserId || $editAll || $viewAll || $this->isGm($game, $actorUserId);
        if (!$allowed) {
            throw new GameNotFoundException();
        }

        return $this->joinRequestRepository->getListByGame($gameId);
    }

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
    ): GameJoinRequestRecord {
        $game = $this->requireOpen($gameId);
        $this->assertStaff($game, $actorUserId, $editAll);
        $request = $this->joinRequestRepository->findPending($gameId, $userId);
        if ($request === null) {
            throw new GameNotFoundException();
        }

        $this->assertPending($request->getStatus(), $action);
        $this->closeJoinRequest($game, $request, $action);

        return $this->savedJoinRequest($gameId, $request->getId());
    }

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
    public function getWhitelist(int $actorUserId, bool $editAll, int $gameId): array
    {
        $game = $this->games->get($gameId);
        if (!$this->canReadWhitelist($game, $actorUserId, $editAll)) {
            throw new GameNotFoundException();
        }

        return $game->getWhitelist();
    }

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
    public function setWhitelist(int $actorUserId, bool $editAll, int $gameId, array $userIds): array
    {
        $game = $this->games->get($gameId);
        if ($game->getOwnerId() !== $actorUserId && !$editAll) {
            throw new GameNotFoundException();
        }

        if ($game->isCompleted()) {
            throw new GameInvalidException('Completed game is read-only');
        }

        $this->gameRepository->saveWhitelist($gameId, $userIds);

        return $this->games->get($gameId)->getWhitelist();
    }

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
    public function joinWhitelist(int $actorUserId, int $gameId): GameMemberRecord
    {
        $game = $this->requireOpen($gameId);
        if (!in_array($actorUserId, $game->getWhitelist(), true)) {
            throw new GameNotFoundException();
        }

        $this->requirePolicy($game, 'whitelist');
        $this->assertJoinable($game, $actorUserId);

        return $this->addPlayer($gameId, $actorUserId);
    }

    /**
     * Пишет участника player.
     *
     * @param int $gameId Игра.
     * @param int $userId Учётка.
     *
     * @return GameMemberRecord Строка.
     *
     * @throws GameNotFoundException Если учётки нет.
     * @throws GameInvalidException Если владелец, повтор или completed.
     */

    /**
     * Пишет статус заявки и участника при accept.
     *
     * @param GameRecord $game Строка.
     * @param GameJoinRequestRecord $request Заявка.
     * @param string $action accept или decline.
     *
     * @return void
     *
     * @throws GameInvalidException Если policy уже не anyone.
     */
    private function closeJoinRequest(GameRecord $game, GameJoinRequestRecord $request, string $action): void
    {
        if ($action === 'accept') {
            $this->requirePolicy($game, 'anyone');
            $this->addPlayer($game->getId(), $request->getUserId());
        }

        $status = $action === 'accept' ? 'accepted' : 'declined';
        $this->joinRequestRepository->saveStatus($request->getId(), $status, DateTime::now());
    }

    /**
     * Заявка после записи статуса.
     *
     * @param int $gameId Игра.
     * @param int $requestId Id.
     *
     * @return GameJoinRequestRecord Строка.
     *
     * @throws GameNotFoundException Если строки нет.
     */
    private function savedJoinRequest(int $gameId, int $requestId): GameJoinRequestRecord
    {
        foreach ($this->joinRequestRepository->getListByGame($gameId) as $record) {
            if ($record->getId() === $requestId) {
                return $record;
            }
        }

        throw new GameNotFoundException();
    }

    /**
     * Пишет участника player.
     *
     * @param int $gameId Игра.
     * @param int $userId Учётка.
     *
     * @return GameMemberRecord Строка.
     *
     * @throws GameNotFoundException Если учётки нет.
     * @throws GameInvalidException Если владелец, повтор или completed.
     */
    private function addPlayer(int $gameId, int $userId): GameMemberRecord
    {
        return $this->games->addMember(NewGameMember::fromNormalized([
            'gameId' => $gameId,
            'userId' => $userId,
            'role' => 'player',
        ]));
    }

    /**
     * Игра есть и не completed.
     *
     * @param int $gameId Игра.
     *
     * @return GameRecord Строка.
     *
     * @throws GameNotFoundException Если нет.
     * @throws GameInvalidException Если completed.
     */
    private function requireOpen(int $gameId): GameRecord
    {
        $game = $this->games->get($gameId);
        if ($game->isCompleted()) {
            throw new GameInvalidException('Completed game is read-only');
        }

        return $game;
    }

    /**
     * Policy совпадает.
     *
     * @param GameRecord $game Строка.
     * @param string $policy Ожидание.
     *
     * @return void
     *
     * @throws GameInvalidException Если другая.
     */
    private function requirePolicy(GameRecord $game, string $policy): void
    {
        if ($game->getJoinPolicy() !== $policy) {
            throw new GameInvalidException('Game join policy does not allow this flow');
        }
    }

    /**
     * Владелец, gm или edit_all.
     *
     * @param GameRecord $game Строка.
     * @param int $actorUserId Актор.
     * @param bool $editAll Ключ.
     *
     * @return void
     *
     * @throws GameNotFoundException Если нет права.
     */
    private function assertStaff(GameRecord $game, int $actorUserId, bool $editAll): void
    {
        if ($game->getOwnerId() === $actorUserId || $editAll || $this->isGm($game, $actorUserId)) {
            return;
        }

        throw new GameNotFoundException();
    }

    /**
     * Ведущий строки.
     *
     * @param GameRecord $game Строка.
     * @param int $actorUserId Актор.
     *
     * @return bool Да, если gm.
     */
    private function isGm(GameRecord $game, int $actorUserId): bool
    {
        try {
            return $this->games->getMember($game->getId(), $actorUserId)->getRole() === 'gm';
        } catch (GameNotFoundException) {
            return false;
        }
    }

    /**
     * Чтение набора: владелец, gm или edit_all.
     *
     * @param GameRecord $game Строка.
     * @param int $actorUserId Актор.
     * @param bool $editAll Ключ.
     *
     * @return bool Да, если можно.
     */
    private function canReadWhitelist(GameRecord $game, int $actorUserId, bool $editAll): bool
    {
        return $game->getOwnerId() === $actorUserId || $editAll || $this->isGm($game, $actorUserId);
    }

    /**
     * Не владелец, не участник, учётка есть.
     *
     * @param GameRecord $game Строка.
     * @param int $userId Учётка.
     *
     * @return void
     *
     * @throws GameNotFoundException Если учётки нет.
     * @throws GameInvalidException Если владелец или участник.
     */
    private function assertJoinable(GameRecord $game, int $userId): void
    {
        $this->requireUser($userId);
        if ($game->getOwnerId() === $userId) {
            throw new GameInvalidException('Game owner is not a member');
        }

        try {
            $this->games->getMember($game->getId(), $userId);
        } catch (GameNotFoundException) {
            return;
        }

        throw new GameInvalidException('Game member already exists');
    }

    /**
     * Учётка есть.
     *
     * @param int $userId Учётка.
     *
     * @return void
     *
     * @throws GameNotFoundException Если нет.
     */
    private function requireUser(int $userId): void
    {
        try {
            $this->userAccounts->getById($userId);
        } catch (UserNotFoundException $exception) {
            throw new GameNotFoundException('Game member was not found', $exception);
        }
    }

    /**
     * Нет второго pending.
     *
     * @param int $gameId Игра.
     * @param int $inviteeId Учётка.
     *
     * @return void
     *
     * @throws GameInvalidException Если pending уже есть.
     */
    private function assertNoPendingInvitation(int $gameId, int $inviteeId): void
    {
        foreach ($this->invitationRepository->getListByGame($gameId) as $record) {
            if ($record->getInviteeId() === $inviteeId && $record->getStatus() === 'pending') {
                throw new GameInvalidException('Game invitation is already pending');
            }
        }
    }

    /**
     * pending и accept либо decline.
     *
     * @param string $status Статус.
     * @param string $action Действие.
     *
     * @return void
     *
     * @throws GameInvalidException Если нельзя.
     */
    private function assertPending(string $status, string $action): void
    {
        if ($status !== 'pending' || ($action !== 'accept' && $action !== 'decline')) {
            throw new GameInvalidException('Game admission is not pending');
        }
    }
}
