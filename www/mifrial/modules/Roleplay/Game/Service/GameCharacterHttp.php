<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Core\Kernel\Exception\ActionException;
use Mifrial\Core\User\Interface\Service\IUserAccess;
use Mifrial\Roleplay\Character\Exception\CharacterNotFoundException;
use Mifrial\Roleplay\Character\Interface\Service\ICharacters;
use Mifrial\Roleplay\Game\Dto\Action\ApproveGameCharacterInput;
use Mifrial\Roleplay\Game\Dto\Action\GetGameCharacterInput;
use Mifrial\Roleplay\Game\Dto\Action\GetGameCharacterListInput;
use Mifrial\Roleplay\Game\Dto\Action\LeaveGameCharacterInput;
use Mifrial\Roleplay\Game\Dto\Action\RejectGameCharacterInput;
use Mifrial\Roleplay\Game\Dto\Action\ReturnGameCharacterInput;
use Mifrial\Roleplay\Game\Dto\Action\SetGameCharacterBonusInput;
use Mifrial\Roleplay\Game\Dto\Action\SubmitGameCharacterInput;
use Mifrial\Roleplay\Game\Dto\GameCharacterRecord;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Game\Interface\Service\IGameMemberships;
use Mifrial\Roleplay\Game\Interface\Service\IGames;

/**
 * HTTP строки персонажа. Видимость листа Character не ослабляет.
 */
final class GameCharacterHttp
{
    /**
     * Создаёт сценарий.
     *
     * @param IUserAccess $userAccess Актор.
     * @param IGames $games Игра и роль.
     * @param IGameMemberships $memberships Строка персонажа.
     * @param ICharacters $characters Actual уже отобранных строк.
     * @param GameCharacterReview $review reviewState.
     * @param GameCharacterViewAssembler $viewAssembler JSON.
     *
     * @return void
     */
    public function __construct(
        private readonly IUserAccess $userAccess,
        private readonly IGames $games,
        private readonly IGameMemberships $memberships,
        private readonly ICharacters $characters,
        private readonly GameCharacterReview $review,
        private readonly GameCharacterViewAssembler $viewAssembler,
    ) {
    }

    /**
     * Подаёт персонажа владельцем листа.
     *
     * @param SubmitGameCharacterInput $input JSON.
     *
     * @return array<string, mixed> Строка.
     *
     * @throws ActionException AUTH_REQUIRED.
     * @throws GameNotFoundException Если чужой лист или нет игры.
     */
    public function submit(SubmitGameCharacterInput $input): array
    {
        $actor = $this->userAccess->requireActor();

        return $this->viewAssembler->detail($this->memberships->submit(
            $input->gameId,
            $input->characterId,
            $actor->getUserId(),
        ));
    }

    /**
     * Принимает заявку и пишет копию листа.
     *
     * @param ApproveGameCharacterInput $input JSON.
     *
     * @return array<string, mixed> Строка.
     *
     * @throws ActionException AUTH_REQUIRED.
     * @throws GameNotFoundException Если нет права.
     */
    public function approve(ApproveGameCharacterInput $input): array
    {
        $this->assertModerator($input->gameId);

        return $this->viewAssembler->detail($this->memberships->approve(
            $input->gameId,
            $input->characterId,
            $input->actualVersion,
            $input->membershipRevision,
        ));
    }

    /**
     * Возвращает строку на доработку.
     *
     * @param ReturnGameCharacterInput $input JSON.
     *
     * @return array<string, mixed> Строка.
     *
     * @throws ActionException AUTH_REQUIRED.
     * @throws GameNotFoundException Если нет права.
     */
    public function returnToOwner(ReturnGameCharacterInput $input): array
    {
        $this->assertModerator($input->gameId);

        return $this->viewAssembler->detail($this->memberships->returnToOwner(
            $input->gameId,
            $input->characterId,
            $input->membershipRevision,
            $input->reason,
            $this->userAccess->requireActor()->getUserId(),
        ));
    }

    /**
     * Удаляет ещё не принятую заявку.
     *
     * @param RejectGameCharacterInput $input JSON.
     *
     * @return null Пусто.
     *
     * @throws ActionException AUTH_REQUIRED.
     * @throws GameNotFoundException Если нет права.
     */
    public function reject(RejectGameCharacterInput $input): null
    {
        $this->assertModerator($input->gameId);
        $this->memberships->reject($input->gameId, $input->characterId, $input->membershipRevision);

        return null;
    }

    /**
     * Выход владельца строки.
     *
     * @param LeaveGameCharacterInput $input JSON.
     *
     * @return array<string, mixed> Строка.
     *
     * @throws ActionException AUTH_REQUIRED.
     * @throws GameNotFoundException Если актор не владелец строки.
     */
    public function leave(LeaveGameCharacterInput $input): array
    {
        $actor = $this->userAccess->requireActor();

        return $this->viewAssembler->detail($this->memberships->leave(
            $input->gameId,
            $input->characterId,
            $actor->getUserId(),
            $input->membershipRevision,
        ));
    }

    /**
     * Бонус.
     *
     * @param SetGameCharacterBonusInput $input JSON.
     *
     * @return array<string, mixed> Строка.
     *
     * @throws ActionException AUTH_REQUIRED.
     * @throws GameNotFoundException Если нет права.
     */
    public function setBonus(SetGameCharacterBonusInput $input): array
    {
        $this->assertModerator($input->gameId);

        return $this->viewAssembler->detail($this->memberships->setBonus(
            $input->gameId,
            $input->characterId,
            $input->membershipRevision,
            $input->osBonus,
            $input->orBonus,
            $input->olBonus,
        ));
    }

    /**
     * Одна строка.
     *
     * @param GetGameCharacterInput $input JSON.
     *
     * @return array<string, mixed> Строка.
     *
     * @throws ActionException AUTH_REQUIRED.
     * @throws GameNotFoundException Если нет права или строки.
     */
    public function get(GetGameCharacterInput $input): array
    {
        $this->assertRowVisible($input->gameId, $input->characterId);

        return $this->viewAssembler->detail($this->memberships->get($input->gameId, $input->characterId));
    }

    /**
     * Список. Чужой actual не читается.
     *
     * @param GetGameCharacterListInput $input JSON.
     *
     * @return list<array<string, mixed>> Строки.
     *
     * @throws ActionException AUTH_REQUIRED.
     * @throws GameNotFoundException Если игры нет.
     */
    public function getList(GetGameCharacterListInput $input): array
    {
        $actor = $this->userAccess->requireActor();
        $ownerFilter = $this->canModerate($input->gameId) ? null : $actor->getUserId();
        $rows = [];
        foreach ($this->memberships->getListByGame($input->gameId, $ownerFilter) as $record) {
            $rows[] = $this->viewAssembler->detail($this->withReview($record));
        }

        return $rows;
    }

    /**
     * Модератор или владелец этой строки. Actual ещё не читается.
     *
     * @param int $gameId Игра.
     * @param int $characterId Персонаж.
     *
     * @return void
     *
     * @throws ActionException AUTH_REQUIRED.
     * @throws GameNotFoundException Если нет права или строки.
     */
    private function assertRowVisible(int $gameId, int $characterId): void
    {
        $actor = $this->userAccess->requireActor();
        $ownerFilter = $this->canModerate($gameId) ? null : $actor->getUserId();
        $this->storedRow($gameId, $characterId, $ownerFilter);
    }

    /**
     * Строка без actual.
     *
     * @param int $gameId Игра.
     * @param int $characterId Персонаж.
     * @param int|null $ownerUserId Фильтр владельца или все строки.
     *
     * @return GameCharacterRecord Строка.
     *
     * @throws GameNotFoundException Если строки нет.
     */
    private function storedRow(int $gameId, int $characterId, ?int $ownerUserId): GameCharacterRecord
    {
        $leftRow = null;
        foreach ($this->memberships->getListByGame($gameId, $ownerUserId) as $record) {
            if ($record->getCharacterId() !== $characterId) {
                continue;
            }

            if ($record->getStatus() !== 'left') {
                return $record;
            }

            $leftRow = $record;
        }

        if ($leftRow !== null) {
            return $leftRow;
        }

        throw new GameNotFoundException();
    }

    /**
     * game.moderate или game.edit_all.
     *
     * @param int $gameId Игра.
     *
     * @return void
     *
     * @throws ActionException AUTH_REQUIRED.
     * @throws GameNotFoundException Если нет права.
     */
    private function assertModerator(int $gameId): void
    {
        $this->userAccess->requireActor();
        if ($this->canModerate($gameId)) {
            return;
        }

        throw new GameNotFoundException();
    }

    /**
     * Право модерации этой игры.
     *
     * @param int $gameId Игра.
     *
     * @return bool true, если moderate или edit_all.
     *
     * @throws GameNotFoundException Если игры нет.
     */
    private function canModerate(int $gameId): bool
    {
        $actor = $this->userAccess->requireActor();
        if ($actor->hasKey(GamePermissionKeys::EDIT_ALL)) {
            return true;
        }

        $game = $this->games->get($gameId);
        $role = null;
        try {
            $role = $this->games->getMember($gameId, $actor->getUserId())->getRole();
        } catch (GameNotFoundException) {
            $role = null;
        }

        $keys = GamePermissionKeys::keysFor($game->getOwnerId() === $actor->getUserId(), $role);

        return in_array(GamePermissionKeys::MODERATE, $keys, true);
    }

    /**
     * reviewState одной уже отобранной строки.
     *
     * @param GameCharacterRecord $record Строка без actual.
     *
     * @return GameCharacterRecord Строка.
     *
     * @throws GameNotFoundException Если персонажа нет.
     */
    private function withReview(GameCharacterRecord $record): GameCharacterRecord
    {
        try {
            $actual = $this->characters->get($record->getCharacterId());
        } catch (CharacterNotFoundException $exception) {
            throw new GameNotFoundException('Game character was not found', $exception);
        }

        $reviewed = $record->withReviewState($this->review->reviewState(
            $record->getApprovedCharacterVersion(),
            $record->getReturnedAt(),
            $actual,
        ));

        return $reviewed->withAdmission(
            $this->review->needsModeration($record->getApprovedCharacterVersion(), $actual),
            $this->review->canStartSession($this->games->get($record->getGameId()), $record, $actual),
            $this->review->isActiveSessionParticipant($record->getGameId(), $record->getCharacterId()),
        );
    }
}
