<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.TooManyConstructorDependencies
// Шлюз держит одну транзакцию со вставкой чата; сборщик — IChats, не восьмой порт ради порога.

use Mifrial\Core\Kernel\Value\DateTime;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Core\User\Exception\UserNotFoundException;
use Mifrial\Core\User\Interface\Service\IUserAccounts;
use Mifrial\Roleplay\Game\Dto\GameMemberPatch;
use Mifrial\Roleplay\Game\Dto\GameMemberRecord;
use Mifrial\Roleplay\Game\Dto\GamePatch;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Dto\NewGame;
use Mifrial\Roleplay\Game\Dto\NewGameMember;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Game\Interface\Service\IGames;
use Mifrial\Roleplay\Game\Interface\Service\IGameSessionRoster;
use Mifrial\Roleplay\Game\Repository\GameMemberRepository;
use Mifrial\Roleplay\Game\Repository\GameRepository;

/**
 * Строка игры: владелец, мир, потолки. Сессии нет.
 */
final class Games implements IGames
{
    /**
     * Создаёт фасад.
     *
     * @param GameRepository $gameRepository Строки.
     * @param GameMemberRepository $memberRepository Участники.
     * @param IUserAccounts $userAccounts Учётки.
     * @param GameWorldGate $worldGate Мир и ревизия.
     * @param GameInputNormalizer $inputNormalizer Trim и enum.
     * @param IGameSessionRoster $sessionRoster Текущая сессия.
     * @param ISmartTableGateway $smartTableGateway Транзакция со вставкой чата.
     * @param GameDonorChats $donorChats Чаты донора.
     *
     * @return void
     */
    public function __construct(
        private readonly GameRepository $gameRepository,
        private readonly GameMemberRepository $memberRepository,
        private readonly IUserAccounts $userAccounts,
        private readonly GameWorldGate $worldGate,
        private readonly GameInputNormalizer $inputNormalizer,
        private readonly IGameSessionRoster $sessionRoster,
        private readonly ISmartTableGateway $smartTableGateway,
        private readonly GameDonorChats $donorChats,
    ) {
    }

    /**
     * Создаёт игру.
     *
     * @param NewGame $new Поля.
     *
     * @return int Id.
     *
     * @throws GameNotFoundException Если нет владельца, мира или ревизии.
     * @throws GameInvalidException Если вход или мир выключен.
     */
    public function add(NewGame $new): int
    {
        $this->requireOwner($new->getOwnerUserId());
        $prepared = $this->prepareNew($new);
        $spaceCode = $this->worldGate->requireCode($prepared->getSpaceId(), $prepared->getRulesRevision());

        return $this->smartTableGateway->transaction(function () use ($prepared, $spaceCode): int {
            $gameId = $this->gameRepository->add($prepared, $spaceCode, DateTime::now());
            $chatIds = $this->donorChats->openGameChats($prepared->getOwnerUserId(), $prepared->getName());
            $this->gameRepository->saveChatIds($gameId, $chatIds['gameChatId'], $chatIds['discussionChatId']);

            return $gameId;
        });
    }

    /**
     * Строка по id.
     *
     * @param int $id Игра.
     *
     * @return GameRecord Игра.
     *
     * @throws GameNotFoundException Если строки нет.
     * @throws GameInvalidException Если строка битая.
     */
    public function get(int $id): GameRecord
    {
        return $this->gameRepository->getById($id);
    }

    /**
     * Переписывает изменяемые поля.
     *
     * @param int $id Игра.
     * @param GamePatch $patch Поля.
     *
     * @return GameRecord Игра.
     *
     * @throws GameNotFoundException Если строки нет.
     * @throws GameInvalidException Если completed или вход.
     */
    public function update(int $id, GamePatch $patch): GameRecord
    {
        $current = $this->gameRepository->getById($id);
        if ($current->isCompleted()) {
            throw new GameInvalidException('Completed game is read-only');
        }

        $this->assertSessionAllows($current, $patch);
        $this->gameRepository->update($id, $this->preparePatch($patch), DateTime::now());

        return $this->gameRepository->getById($id);
    }

    /**
     * Свои строки или все.
     *
     * @param int $ownerUserId Владелец.
     * @param bool $viewAll Все строки.
     *
     * @return list<GameRecord> Игры.
     *
     * @throws GameInvalidException Если строка битая.
     */
    public function getListByOwnerOrAll(int $ownerUserId, bool $viewAll): array
    {
        return $this->gameRepository->getListByOwnerOrAll($ownerUserId, $viewAll);
    }

    /**
     * Добавляет участника.
     *
     * @param NewGameMember $new Поля.
     *
     * @return GameMemberRecord Строка.
     *
     * @throws GameNotFoundException Если нет игры или учётки.
     * @throws GameInvalidException Если владелец, роль, пара или completed.
     */
    public function addMember(NewGameMember $new): GameMemberRecord
    {
        $game = $this->requireOpenGame($new->getGameId());
        $this->requireMemberUser($new->getUserId());
        $this->assertNotOwner($game, $new->getUserId());
        $role = $this->inputNormalizer->memberRole($new->getRole());
        $this->smartTableGateway->transaction(function () use ($new, $role): void {
            $this->memberRepository->add($new->getGameId(), $new->getUserId(), $role);
            $this->donorChats->seatJoined($this->gameRepository->getById($new->getGameId()), $new->getUserId(), $role);
        });

        return $this->memberRepository->getByPair($new->getGameId(), $new->getUserId());
    }

    /**
     * Строка пары.
     *
     * @param int $gameId Игра.
     * @param int $userId Учётка.
     *
     * @return GameMemberRecord Участник.
     *
     * @throws GameNotFoundException Если строки нет.
     * @throws GameInvalidException Если строка битая.
     */
    public function getMember(int $gameId, int $userId): GameMemberRecord
    {
        return $this->memberRepository->getByPair($gameId, $userId);
    }

    /**
     * Меняет роль.
     *
     * @param int $gameId Игра.
     * @param int $userId Учётка.
     * @param GameMemberPatch $patch Роль.
     *
     * @return GameMemberRecord Участник.
     *
     * @throws GameNotFoundException Если строки нет.
     * @throws GameInvalidException Если роль или completed.
     */
    public function updateMember(int $gameId, int $userId, GameMemberPatch $patch): GameMemberRecord
    {
        $current = $this->memberRepository->getByPair($gameId, $userId);
        $this->requireOpenGame($gameId);
        $nextRole = $this->inputNormalizer->memberRole($patch->getRole());
        $this->smartTableGateway->transaction(function () use ($gameId, $userId, $current, $nextRole): void {
            $this->memberRepository->updateRole($current->getId(), $nextRole);
            $this->moveGmSeat($gameId, $userId, $current->getRole(), $nextRole);
        });

        return $this->memberRepository->getByPair($gameId, $userId);
    }

    /**
     * Снимает участника.
     *
     * @param int $gameId Игра.
     * @param int $userId Учётка.
     *
     * @return void
     *
     * @throws GameNotFoundException Если строки нет.
     * @throws GameInvalidException Если completed.
     */
    public function deleteMember(int $gameId, int $userId): void
    {
        $current = $this->memberRepository->getByPair($gameId, $userId);
        $this->requireOpenGame($gameId);
        $this->smartTableGateway->transaction(function () use ($gameId, $userId, $current): void {
            $this->memberRepository->deleteById($current->getId());
            $this->donorChats->seatLeft($this->gameRepository->getById($gameId), $userId, $current->getRole());
        });
    }

    /**
     * Участники игры.
     *
     * @param int $gameId Игра.
     *
     * @return list<GameMemberRecord> Строки.
     *
     * @throws GameNotFoundException Если игры нет.
     * @throws GameInvalidException Если строка битая.
     */
    public function getMemberList(int $gameId): array
    {
        $this->gameRepository->getById($gameId);

        return $this->memberRepository->getListByGame($gameId);
    }

    /**
     * Учётка владельца существует.
     *
     * @param int $ownerUserId Владелец.
     *
     * @return void
     *
     * @throws GameNotFoundException Если учётки нет.
     */
    private function requireOwner(int $ownerUserId): void
    {
        try {
            $this->userAccounts->getById($ownerUserId);
        } catch (UserNotFoundException $exception) {
            throw new GameNotFoundException('Game owner was not found', $exception);
        }
    }

    /**
     * Живая сессия не меняет ревизию и не закрывает кампанию.
     *
     * @param GameRecord $current Строка.
     * @param GamePatch $patch Поля.
     *
     * @return void
     *
     * @throws GameInvalidException Если ревизия другая или статус completed.
     */
    private function assertSessionAllows(GameRecord $current, GamePatch $patch): void
    {
        if (!$this->sessionRoster->hasSession($current->getId())) {
            return;
        }

        if ($patch->getRulesRevision() !== $current->getRulesRevision() || $patch->getStatus() === 'completed') {
            throw new GameInvalidException('Running session keeps rules and stays open');
        }
    }

    /**
     * Игра есть и не completed.
     *
     * @param int $gameId Игра.
     *
     * @return GameRecord Игра.
     *
     * @throws GameNotFoundException Если игры нет.
     * @throws GameInvalidException Если completed.
     */
    private function requireOpenGame(int $gameId): GameRecord
    {
        $game = $this->gameRepository->getById($gameId);
        if ($game->isCompleted()) {
            throw new GameInvalidException('Completed game is read-only');
        }

        return $game;
    }

    /**
     * Учётка участника существует.
     *
     * @param int $userId Учётка.
     *
     * @return void
     *
     * @throws GameNotFoundException Если учётки нет.
     */
    private function requireMemberUser(int $userId): void
    {
        try {
            $this->userAccounts->getById($userId);
        } catch (UserNotFoundException $exception) {
            throw new GameNotFoundException('Game member was not found', $exception);
        }
    }

    /**
     * Владелец колонкой не становится строкой.
     *
     * @param GameRecord $game Игра.
     * @param int $userId Учётка.
     *
     * @return void
     *
     * @throws GameInvalidException Если это владелец.
     */
    private function assertNotOwner(GameRecord $game, int $userId): void
    {
        if ($game->getOwnerId() === $userId) {
            throw new GameInvalidException('Game owner is not a member row');
        }
    }

    /**
     * Нормализует create.
     *
     * @param NewGame $new Сырые поля.
     *
     * @return NewGame Поля записи.
     *
     * @throws GameInvalidException Если имя, enum, ревизия или потолок.
     */
    private function prepareNew(NewGame $new): NewGame
    {
        return NewGame::fromNormalized([
            'ownerUserId' => $new->getOwnerUserId(),
            'name' => $this->inputNormalizer->name($new->getName()),
            'shortDescription' => $this->inputNormalizer->text($new->getShortDescription()),
            'description' => $this->inputNormalizer->text($new->getDescription()),
            'status' => $this->inputNormalizer->status($new->getStatus()),
            'visibility' => $this->inputNormalizer->visibility($new->getVisibility()),
            'joinPolicy' => $this->inputNormalizer->joinPolicy($new->getJoinPolicy()),
            'spaceId' => $new->getSpaceId(),
            'rulesRevision' => $this->inputNormalizer->rulesRevision($new->getRulesRevision()),
            'osPointsLimit' => $this->inputNormalizer->limit($new->getOsPointsLimit()),
            'olPointsLimit' => $this->inputNormalizer->limit($new->getOlPointsLimit()),
            'orPointsLimit' => $this->inputNormalizer->limit($new->getOrPointsLimit()),
            'moneyLimit' => $this->inputNormalizer->limit($new->getMoneyLimit()),
        ]);
    }

    /**
     * Нормализует update.
     *
     * @param GamePatch $patch Сырые поля.
     *
     * @return GamePatch Поля записи.
     *
     * @throws GameInvalidException Если имя, enum или потолок.
     */
    private function preparePatch(GamePatch $patch): GamePatch
    {
        return GamePatch::fromNormalized([
            'name' => $this->inputNormalizer->name($patch->getName()),
            'shortDescription' => $this->inputNormalizer->text($patch->getShortDescription()),
            'description' => $this->inputNormalizer->text($patch->getDescription()),
            'status' => $this->inputNormalizer->status($patch->getStatus()),
            'visibility' => $this->inputNormalizer->visibility($patch->getVisibility()),
            'joinPolicy' => $this->inputNormalizer->joinPolicy($patch->getJoinPolicy()),
            'osPointsLimit' => $this->inputNormalizer->limit($patch->getOsPointsLimit()),
            'olPointsLimit' => $this->inputNormalizer->limit($patch->getOlPointsLimit()),
            'orPointsLimit' => $this->inputNormalizer->limit($patch->getOrPointsLimit()),
            'moneyLimit' => $this->inputNormalizer->limit($patch->getMoneyLimit()),
            'rulesRevision' => $this->inputNormalizer->rulesRevision($patch->getRulesRevision()),
        ]);
    }

    /**
     * Чат строки следит только за появлением и уходом gm.
     *
     * @param int $gameId Игра.
     * @param int $userId Учётка.
     * @param string $previousRole Роль до записи.
     * @param string $nextRole Роль после записи.
     *
     * @return void
     */
    private function moveGmSeat(int $gameId, int $userId, string $previousRole, string $nextRole): void
    {
        if ($previousRole === $nextRole) {
            return;
        }

        $game = $this->gameRepository->getById($gameId);
        if ($nextRole === 'gm') {
            $this->donorChats->seatGm($game, $userId, true);
        }

        if ($previousRole === 'gm') {
            $this->donorChats->seatGm($game, $userId, false);
        }
    }
}
