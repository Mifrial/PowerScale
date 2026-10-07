<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Core\Kernel\Exception\ActionException;
use Mifrial\Core\User\Interface\Service\IUserAccess;
use Mifrial\Roleplay\Game\Dto\Action\GetGameInvitationsInput;
use Mifrial\Roleplay\Game\Dto\Action\GetGameJoinRequestsInput;
use Mifrial\Roleplay\Game\Dto\Action\GetGameWhitelistInput;
use Mifrial\Roleplay\Game\Dto\Action\InviteGameInput;
use Mifrial\Roleplay\Game\Dto\Action\JoinGameWhitelistInput;
use Mifrial\Roleplay\Game\Dto\Action\RequestGameJoinInput;
use Mifrial\Roleplay\Game\Dto\Action\RespondGameInvitationInput;
use Mifrial\Roleplay\Game\Dto\Action\RespondGameJoinRequestInput;
use Mifrial\Roleplay\Game\Dto\Action\SetGameWhitelistInput;
use Mifrial\Roleplay\Game\Dto\GameInvitationRecord;
use Mifrial\Roleplay\Game\Dto\GameJoinRequestRecord;
use Mifrial\Roleplay\Game\Dto\GameMemberRecord;
use Mifrial\Roleplay\Game\Interface\Service\IGameAdmissions;

/**
 * HTTP вступления. Карточку игры не собирает.
 */
final class GameAdmissionHttp // phpcs:ignore MifrialCodingStandard.Metrics.ClassQuality.TooManyPublicMethods -- конструктор входит в счётчик; десять действий контракта G8.
{
    /**
     * Создаёт сценарий.
     *
     * @param IUserAccess $userAccess Актор.
     * @param IGameAdmissions $admissions Потоки.
     *
     * @return void
     */
    public function __construct(
        private readonly IUserAccess $userAccess,
        private readonly IGameAdmissions $admissions,
    ) {
    }

    /**
     * Приглашает.
     *
     * @param InviteGameInput $input JSON.
     *
     * @return array<string, mixed> Приглашение.
     *
     * @throws ActionException AUTH_REQUIRED.
     */
    public function invite(InviteGameInput $input): array
    {
        $actor = $this->userAccess->requireActor();

        return $this->invitation($this->admissions->invite(
            $actor->getUserId(),
            $actor->hasKey(GamePermissionKeys::EDIT_ALL),
            $input->gameId,
            $input->inviteeId,
        ));
    }

    /**
     * Приглашения игры.
     *
     * @param GetGameInvitationsInput $input JSON.
     *
     * @return list<array<string, mixed>> Список.
     *
     * @throws ActionException AUTH_REQUIRED.
     */
    public function getInvitations(GetGameInvitationsInput $input): array
    {
        $actor = $this->userAccess->requireActor();
        $rows = [];
        foreach (
            $this->admissions->getInvitations(
                $actor->getUserId(),
                $actor->hasKey(GamePermissionKeys::EDIT_ALL),
                $input->gameId,
            ) as $record
        ) {
            $rows[] = $this->invitation($record);
        }

        return $rows;
    }

    /**
     * Свои приглашения.
     *
     * @return list<array<string, mixed>> Список.
     *
     * @throws ActionException AUTH_REQUIRED.
     */
    public function getMyInvitations(): array
    {
        $actor = $this->userAccess->requireActor();
        $rows = [];
        foreach ($this->admissions->getMyInvitations($actor->getUserId()) as $record) {
            $rows[] = $this->invitation($record);
        }

        return $rows;
    }

    /**
     * Ответ на приглашение.
     *
     * @param RespondGameInvitationInput $input JSON.
     *
     * @return array<string, mixed> Приглашение.
     *
     * @throws ActionException AUTH_REQUIRED.
     */
    public function respondInvitation(RespondGameInvitationInput $input): array
    {
        $actor = $this->userAccess->requireActor();

        return $this->invitation($this->admissions->respondInvitation(
            $actor->getUserId(),
            $input->invitationId,
            $input->action,
        ));
    }

    /**
     * Заявка.
     *
     * @param RequestGameJoinInput $input JSON.
     *
     * @return array<string, mixed> Заявка.
     *
     * @throws ActionException AUTH_REQUIRED.
     */
    public function requestJoin(RequestGameJoinInput $input): array
    {
        $actor = $this->userAccess->requireActor();

        return $this->joinRequest($this->admissions->requestJoin(
            $actor->getUserId(),
            $actor->hasKey(GamePermissionKeys::VIEW_ALL),
            $input->gameId,
        ));
    }

    /**
     * Заявки игры.
     *
     * @param GetGameJoinRequestsInput $input JSON.
     *
     * @return list<array<string, mixed>> Список.
     *
     * @throws ActionException AUTH_REQUIRED.
     */
    public function getJoinRequests(GetGameJoinRequestsInput $input): array
    {
        $actor = $this->userAccess->requireActor();
        $rows = [];
        foreach (
            $this->admissions->getJoinRequests(
                $actor->getUserId(),
                $actor->hasKey(GamePermissionKeys::EDIT_ALL),
                $actor->hasKey(GamePermissionKeys::VIEW_ALL),
                $input->gameId,
            ) as $record
        ) {
            $rows[] = $this->joinRequest($record);
        }

        return $rows;
    }

    /**
     * Ответ на заявку.
     *
     * @param RespondGameJoinRequestInput $input JSON.
     *
     * @return array<string, mixed> Заявка.
     *
     * @throws ActionException AUTH_REQUIRED.
     */
    public function respondJoinRequest(RespondGameJoinRequestInput $input): array
    {
        $actor = $this->userAccess->requireActor();

        return $this->joinRequest($this->admissions->respondJoinRequest(
            $actor->getUserId(),
            $actor->hasKey(GamePermissionKeys::EDIT_ALL),
            $input->gameId,
            $input->userId,
            $input->action,
        ));
    }

    /**
     * Читает набор.
     *
     * @param GetGameWhitelistInput $input JSON.
     *
     * @return array<string, mixed> Список id.
     *
     * @throws ActionException AUTH_REQUIRED.
     */
    public function getWhitelist(GetGameWhitelistInput $input): array
    {
        $actor = $this->userAccess->requireActor();

        return [
            'userIds' => $this->admissions->getWhitelist(
                $actor->getUserId(),
                $actor->hasKey(GamePermissionKeys::EDIT_ALL),
                $input->gameId,
            ),
        ];
    }

    /**
     * Заменяет набор.
     *
     * @param SetGameWhitelistInput $input JSON.
     *
     * @return array<string, mixed> Список id.
     *
     * @throws ActionException AUTH_REQUIRED.
     */
    public function setWhitelist(SetGameWhitelistInput $input): array
    {
        $actor = $this->userAccess->requireActor();

        return [
            'userIds' => $this->admissions->setWhitelist(
                $actor->getUserId(),
                $actor->hasKey(GamePermissionKeys::EDIT_ALL),
                $input->gameId,
                $input->userIds,
            ),
        ];
    }

    /**
     * Вступает по списку.
     *
     * @param JoinGameWhitelistInput $input JSON.
     *
     * @return array<string, mixed> Участник.
     *
     * @throws ActionException AUTH_REQUIRED.
     */
    public function joinWhitelist(JoinGameWhitelistInput $input): array
    {
        $actor = $this->userAccess->requireActor();

        return $this->member($this->admissions->joinWhitelist($actor->getUserId(), $input->gameId));
    }

    /**
     * JSON приглашения.
     *
     * @param GameInvitationRecord $record Строка.
     *
     * @return array<string, mixed> Поля.
     */
    private function invitation(GameInvitationRecord $record): array
    {
        return [
            'id' => $record->getId(),
            'gameId' => $record->getGameId(),
            'inviterId' => $record->getInviterId(),
            'inviteeId' => $record->getInviteeId(),
            'status' => $record->getStatus(),
        ];
    }

    /**
     * JSON заявки.
     *
     * @param GameJoinRequestRecord $record Строка.
     *
     * @return array<string, mixed> Поля.
     */
    private function joinRequest(GameJoinRequestRecord $record): array
    {
        return [
            'id' => $record->getId(),
            'gameId' => $record->getGameId(),
            'userId' => $record->getUserId(),
            'status' => $record->getStatus(),
        ];
    }

    /**
     * JSON участника.
     *
     * @param GameMemberRecord $record Строка.
     *
     * @return array<string, mixed> Поля.
     */
    private function member(GameMemberRecord $record): array
    {
        return [
            'userId' => $record->getUserId(),
            'role' => $record->getRole(),
        ];
    }
}
