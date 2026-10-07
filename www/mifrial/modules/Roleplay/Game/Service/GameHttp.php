<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Core\Kernel\Exception\ActionException;
use Mifrial\Core\User\Interface\Service\IUserAccess;
use Mifrial\Roleplay\Game\Dto\Action\AddGameMemberInput;
use Mifrial\Roleplay\Game\Dto\Action\CreateGameInput;
use Mifrial\Roleplay\Game\Dto\Action\GetGameInput;
use Mifrial\Roleplay\Game\Dto\Action\GetGameMemberListInput;
use Mifrial\Roleplay\Game\Dto\Action\RemoveGameMemberInput;
use Mifrial\Roleplay\Game\Dto\Action\UpdateGameInput;
use Mifrial\Roleplay\Game\Dto\Action\UpdateGameMemberInput;
use Mifrial\Roleplay\Game\Dto\GameMemberPatch;
use Mifrial\Roleplay\Game\Dto\GamePatch;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Dto\NewGame;
use Mifrial\Roleplay\Game\Dto\NewGameMember;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Game\Interface\Service\IGames;
use Mifrial\Roleplay\Game\Interface\Service\IGameSessionRoster;

/**
 * HTTP строки игры. Карточку фильтрует visibility.
 */
final class GameHttp
{
    /**
     * Создаёт сценарий.
     *
     * @param IUserAccess $userAccess Актор.
     * @param IGames $games Фасад.
     * @param GameWorldGate $worldGate Сверка кода мира.
     * @param GameViewAssembler $viewAssembler JSON.
     * @param IGameSessionRoster $sessionRoster Признак сессии.
     * @param GameCardAccess $cardAccess Фильтр карточки.
     *
     * @return void
     */
    public function __construct(
        private readonly IUserAccess $userAccess,
        private readonly IGames $games,
        private readonly GameWorldGate $worldGate,
        private readonly GameViewAssembler $viewAssembler,
        private readonly IGameSessionRoster $sessionRoster,
        private readonly GameCardAccess $cardAccess,
    ) {
    }

    /**
     * Создаёт игру от актора.
     *
     * @param CreateGameInput $input JSON.
     *
     * @return array<string, mixed> Карточка.
     *
     * @throws ActionException AUTH_REQUIRED или AUTH_DENIED.
     * @throws GameNotFoundException Если мира нет.
     * @throws GameInvalidException Если код мира не совпал.
     */
    public function create(CreateGameInput $input): array
    {
        $actor = $this->userAccess->requireKey(GamePermissionKeys::CREATE);
        $this->worldGate->assertClientCode($input->spaceId, $input->spaceCode);
        $gameId = $this->games->add(NewGame::fromNormalized([
            'ownerUserId' => $actor->getUserId(),
            'name' => $input->name,
            'shortDescription' => $input->shortDescription,
            'description' => $input->description,
            'status' => $input->status,
            'visibility' => $input->visibility,
            'joinPolicy' => $input->joinPolicy,
            'spaceId' => $input->spaceId,
            'rulesRevision' => $input->rulesRevision,
            'osPointsLimit' => $input->osPointsLimit,
            'olPointsLimit' => $input->olPointsLimit,
            'orPointsLimit' => $input->orPointsLimit,
            'moneyLimit' => $input->moneyLimit,
        ]));

        return $this->card($this->games->get($gameId));
    }

    /**
     * Карточка своей игры или при game.view_all.
     *
     * @param GetGameInput $input Id.
     *
     * @return array<string, mixed> Карточка.
     *
     * @throws ActionException AUTH_REQUIRED.
     * @throws GameNotFoundException Если нет или чужая.
     */
    public function get(GetGameInput $input): array
    {
        $actor = $this->userAccess->requireActor();
        $record = $this->games->get($input->id);
        $this->assertVisible($record, $actor->getUserId(), $actor->hasKey(GamePermissionKeys::VIEW_ALL));

        return $this->card($record);
    }

    /**
     * Список своих или всех.
     *
     * @return list<array<string, mixed>> Карточки.
     *
     * @throws ActionException AUTH_REQUIRED.
     */
    public function getList(): array
    {
        $actor = $this->userAccess->requireActor();
        $rows = [];
        $viewAll = $actor->hasKey(GamePermissionKeys::VIEW_ALL);
        $records = $viewAll
            ? $this->games->getListByOwnerOrAll($actor->getUserId(), true)
            : $this->cardAccess->retainVisible($actor->getUserId());
        foreach ($records as $record) {
            $rows[] = $this->viewAssembler->listItem($record, $this->sessionRoster->hasSession($record->getId()));
        }

        return $rows;
    }

    /**
     * Пишет свою игру или при game.edit_all.
     *
     * @param UpdateGameInput $input JSON.
     *
     * @return array<string, mixed> Карточка.
     *
     * @throws ActionException AUTH_REQUIRED.
     * @throws GameNotFoundException Если нет, чужая или новой ревизии нет.
     * @throws GameInvalidException Если мир другой, ревизия битая или completed.
     */
    public function update(UpdateGameInput $input): array
    {
        $actor = $this->userAccess->requireActor();
        $record = $this->games->get($input->id);
        $this->assertEditable($record, $actor->getUserId(), $actor->hasKey(GamePermissionKeys::EDIT_ALL));
        $this->assertSameWorld($record, $input);
        if ($input->rulesRevision !== $record->getRulesRevision()) {
            $this->worldGate->requireCode($record->getSpaceId(), $input->rulesRevision);
        }

        $updated = $this->games->update($input->id, GamePatch::fromNormalized([
            'name' => $input->name,
            'shortDescription' => $input->shortDescription,
            'description' => $input->description,
            'status' => $input->status,
            'visibility' => $input->visibility,
            'joinPolicy' => $input->joinPolicy,
            'osPointsLimit' => $input->osPointsLimit,
            'olPointsLimit' => $input->olPointsLimit,
            'orPointsLimit' => $input->orPointsLimit,
            'moneyLimit' => $input->moneyLimit,
            'rulesRevision' => $input->rulesRevision,
        ]));

        return $this->card($updated);
    }

    /**
     * Добавляет участника.
     *
     * @param AddGameMemberInput $input JSON.
     *
     * @return array<string, mixed> Участник.
     *
     * @throws ActionException AUTH_REQUIRED.
     * @throws GameNotFoundException Если нет права или учётки.
     * @throws GameInvalidException Если роль, владелец или completed.
     */
    public function addMember(AddGameMemberInput $input): array
    {
        $this->assertMemberWriter($input->gameId);

        return $this->viewAssembler->member($this->games->addMember(NewGameMember::fromNormalized([
            'gameId' => $input->gameId,
            'userId' => $input->userId,
            'role' => $input->role,
        ])));
    }

    /**
     * Меняет роль.
     *
     * @param UpdateGameMemberInput $input JSON.
     *
     * @return array<string, mixed> Участник.
     *
     * @throws ActionException AUTH_REQUIRED.
     * @throws GameNotFoundException Если нет права или строки.
     * @throws GameInvalidException Если роль или completed.
     */
    public function updateMember(UpdateGameMemberInput $input): array
    {
        $this->assertMemberWriter($input->gameId);

        return $this->viewAssembler->member($this->games->updateMember(
            $input->gameId,
            $input->userId,
            GameMemberPatch::fromNormalized(['role' => $input->role]),
        ));
    }

    /**
     * Снимает участника.
     *
     * @param RemoveGameMemberInput $input JSON.
     *
     * @return null Пусто.
     *
     * @throws ActionException AUTH_REQUIRED.
     * @throws GameNotFoundException Если нет права или строки.
     * @throws GameInvalidException Если completed.
     */
    public function removeMember(RemoveGameMemberInput $input): null
    {
        $this->assertMemberWriter($input->gameId);
        $this->games->deleteMember($input->gameId, $input->userId);

        return null;
    }

    /**
     * Список участников.
     *
     * @param GetGameMemberListInput $input JSON.
     *
     * @return list<array<string, mixed>> Участники.
     *
     * @throws ActionException AUTH_REQUIRED.
     * @throws GameNotFoundException Если нет права или игры.
     */
    public function getMemberList(GetGameMemberListInput $input): array
    {
        $this->assertMemberReader($input->gameId);
        $rows = [];
        foreach ($this->games->getMemberList($input->gameId) as $record) {
            $rows[] = $this->viewAssembler->member($record);
        }

        return $rows;
    }

    /**
     * Карточка с признаком сессии.
     *
     * @param GameRecord $record Строка.
     *
     * @return array<string, mixed> JSON.
     */
    private function card(GameRecord $record): array
    {
        return $this->viewAssembler->detail($record, $this->sessionRoster->hasSession($record->getId()));
    }

    /**
     * Чужую без view_all прячет.
     *
     * @param GameRecord $record Строка.
     * @param int $actorUserId Актор.
     * @param bool $viewAll Ключ.
     *
     * @return void
     *
     * @throws GameNotFoundException Если чужая.
     */
    private function assertVisible(GameRecord $record, int $actorUserId, bool $viewAll): void
    {
        if ($this->cardAccess->isVisible($record, $actorUserId, $viewAll)) {
            return;
        }

        throw new GameNotFoundException();
    }

    /**
     * Чужую без edit_all прячет.
     *
     * @param GameRecord $record Строка.
     * @param int $actorUserId Актор.
     * @param bool $editAll Ключ.
     *
     * @return void
     *
     * @throws GameNotFoundException Если чужая.
     */
    private function assertEditable(GameRecord $record, int $actorUserId, bool $editAll): void
    {
        if ($record->getOwnerId() === $actorUserId || $editAll) {
            return;
        }

        throw new GameNotFoundException();
    }

    /**
     * Пишет состав владелец или game.edit_all.
     *
     * @param int $gameId Игра.
     *
     * @return void
     *
     * @throws ActionException AUTH_REQUIRED.
     * @throws GameNotFoundException Если чужая.
     */
    private function assertMemberWriter(int $gameId): void
    {
        $actor = $this->userAccess->requireActor();
        $record = $this->games->get($gameId);
        if ($record->getOwnerId() === $actor->getUserId() || $actor->hasKey(GamePermissionKeys::EDIT_ALL)) {
            return;
        }

        throw new GameNotFoundException();
    }

    /**
     * Читает состав владелец, view_all или edit_all.
     *
     * @param int $gameId Игра.
     *
     * @return void
     *
     * @throws ActionException AUTH_REQUIRED.
     * @throws GameNotFoundException Если чужая.
     */
    private function assertMemberReader(int $gameId): void
    {
        $actor = $this->userAccess->requireActor();
        $record = $this->games->get($gameId);
        $allowed = $record->getOwnerId() === $actor->getUserId()
            || $actor->hasKey(GamePermissionKeys::VIEW_ALL)
            || $actor->hasKey(GamePermissionKeys::EDIT_ALL);
        if ($allowed) {
            return;
        }

        throw new GameNotFoundException();
    }

    /**
     * spaceId и spaceCode update не меняют. Номер ревизии проверяется отдельно.
     *
     * @param GameRecord $record Строка.
     * @param UpdateGameInput $input JSON.
     *
     * @return void
     *
     * @throws GameInvalidException Если прислано другое.
     */
    private function assertSameWorld(GameRecord $record, UpdateGameInput $input): void
    {
        if ($input->spaceId !== $record->getSpaceId()) {
            throw new GameInvalidException('Game world cannot change');
        }

        if ($input->spaceCode !== null && trim($input->spaceCode) !== $record->getSpaceCode()) {
            throw new GameInvalidException('Game space code does not match');
        }
    }
}
