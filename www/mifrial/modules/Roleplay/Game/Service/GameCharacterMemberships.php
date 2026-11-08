<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Core\Kernel\Value\DateTime;
use Mifrial\Roleplay\Character\Dto\CharacterRecord;
use Mifrial\Roleplay\Character\Exception\CharacterNotFoundException;
use Mifrial\Roleplay\Character\Interface\Service\ICharacters;
use Mifrial\Roleplay\Game\Dto\GameCharacterRecord;
use Mifrial\Roleplay\Game\Exception\GameConflictException;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Game\Interface\Service\IGameMemberships;
use Mifrial\Roleplay\Game\Interface\Service\IGames;
use Mifrial\Roleplay\Game\Repository\GameProcessRepository;
use Mifrial\Roleplay\Game\Repository\GameSessionRepository;

/**
 * Заявка, snapshot и бонус персонажа в игре.
 */
final class GameCharacterMemberships implements IGameMemberships
{
    private readonly GameCharacterRelease $release;

    /**
     * Создаёт фасад.
     *
     * @param GameMembershipPorts $ports Хранилища строки.
     * @param IGames $games Игра.
     * @param ICharacters $characters Actual.
     * @param GameCharacterReview $review Состояние проверки.
     * @param GameDonorChats $donorChats Чат строки.
     *
     * @return void
     */
    public function __construct(
        private readonly GameMembershipPorts $ports,
        private readonly IGames $games,
        private readonly ICharacters $characters,
        private readonly GameCharacterReview $review,
        private readonly GameDonorChats $donorChats,
    ) {
        $this->release = new GameCharacterRelease($ports, $games, $characters);
    }

    /**
     * Подаёт существующего персонажа.
     *
     * @param int $gameId Игра.
     * @param int $characterId Персонаж.
     * @param int $ownerUserId Актор, владелец листа.
     *
     * @return GameCharacterRecord Заявка.
     *
     * @throws GameNotFoundException Если нет игры, персонажа или актор не владелец.
     * @throws GameInvalidException Если completed или живой вход уже есть.
     */
    public function submit(int $gameId, int $characterId, int $ownerUserId): GameCharacterRecord
    {
        $this->assertWritableGame($gameId);
        $actual = $this->actual($characterId);
        if ($actual->getOwnerId() !== $ownerUserId) {
            throw new GameNotFoundException();
        }

        if ($this->ports->characters->findLiveId($characterId) !== null) {
            throw new GameInvalidException('Character is already in a game');
        }

        return $this->ports->gateway->transaction(
            function () use ($gameId, $characterId, $actual): GameCharacterRecord {
                $row = $this->ports->characters->add($gameId, $characterId, $actual->getOwnerId());
                $chatId = $this->donorChats->openCharacterChat(
                    $characterId,
                    $actual->getOwnerId(),
                    $this->characterChatMemberIds($gameId, $actual->getOwnerId()),
                );
                $this->ports->characters->saveDiscussionChatId($row->getId(), $chatId);

                $row = $this->ports->characters->getByPair($gameId, $characterId);

                return $this->review->decorate($this->games->get($gameId), $row, $actual);
            },
        );
    }

    /**
     * Строка пары. Считает reviewState.
     *
     * @param int $gameId Игра.
     * @param int $characterId Персонаж.
     *
     * @return GameCharacterRecord Строка.
     *
     * @throws GameNotFoundException Если нет строки или персонажа.
     * @throws GameInvalidException Если строка битая.
     */
    public function get(int $gameId, int $characterId): GameCharacterRecord
    {
        $row = $this->ports->characters->getByPair($gameId, $characterId);
        $actual = $this->actual($characterId);

        return $this->review->decorate($this->games->get($gameId), $row, $actual);
    }

    /**
     * Строки игры без чтения actual.
     *
     * @param int $gameId Игра.
     * @param int|null $ownerUserId Фильтр владельца или все.
     *
     * @return list<GameCharacterRecord> Строки без reviewState.
     *
     * @throws GameNotFoundException Если игры нет.
     * @throws GameInvalidException Если строка битая.
     */
    public function getListByGame(int $gameId, ?int $ownerUserId): array
    {
        $this->games->get($gameId);

        return $this->ports->characters->getListByGame($gameId, $ownerUserId);
    }

    /**
     * Пишет snapshot actual. Actual не меняет.
     *
     * @param int $gameId Игра.
     * @param int $characterId Персонаж.
     * @param int $expectedActualVersion Ожидаемый actual_version.
     * @param int $expectedMembershipRevision Ожидаемая revision.
     *
     * @return GameCharacterRecord Строка.
     *
     * @throws GameNotFoundException Если нет строки или персонажа.
     * @throws GameInvalidException Если статус, completed.
     * @throws GameConflictException Если версия устарела.
     */
    public function approve(
        int $gameId,
        int $characterId,
        int $expectedActualVersion,
        int $expectedMembershipRevision,
    ): GameCharacterRecord {
        $this->assertWritableGame($gameId);
        $row = $this->openRow($gameId, $characterId);
        $actual = $this->actual($characterId);
        $this->assertMembership($row, $expectedMembershipRevision, $actual);
        if ($actual->getActualVersion() !== $expectedActualVersion) {
            throw new GameConflictException($actual->getActualVersion(), $row->getMembershipRevision());
        }

        $this->ports->characters->saveApproved(
            $row->getId(),
            $this->snapshot($actual),
            $row->getMembershipRevision() + 1,
        );

        return $this->get($gameId, $characterId);
    }

    /**
     * Оставляет строку и пишет причину.
     *
     * @param int $gameId Игра.
     * @param int $characterId Персонаж.
     * @param int $expectedMembershipRevision Ожидаемая revision.
     * @param string $reason Причина.
     * @param int $actorUserId Актор return.
     *
     * @return GameCharacterRecord Строка.
     *
     * @throws GameNotFoundException Если нет строки или персонажа.
     * @throws GameInvalidException Если статус, пустая причина или completed.
     * @throws GameConflictException Если revision устарела.
     */
    public function returnToOwner(
        int $gameId,
        int $characterId,
        int $expectedMembershipRevision,
        string $reason,
        int $actorUserId,
    ): GameCharacterRecord {
        $this->assertWritableGame($gameId);
        $trimmed = $this->trimmedReason($reason);
        $row = $this->openRow($gameId, $characterId);
        $actual = $this->actual($characterId);
        $this->assertMembership($row, $expectedMembershipRevision, $actual);
        $this->ports->gateway->transaction(
            function () use ($row, $actorUserId, $trimmed, $gameId, $characterId): void {
                $messageId = $this->returnMessageId($row, $actorUserId, $trimmed);
                $this->ports->characters->saveReturned(
                    $row->getId(),
                    DateTime::now(),
                    $trimmed,
                    $row->getMembershipRevision() + 1,
                    $messageId,
                );
                $this->cancelOpenCharacter($gameId, $characterId);
            },
        );

        return $this->get($gameId, $characterId);
    }

    /**
     * Удаляет только submitted.
     *
     * @param int $gameId Игра.
     * @param int $characterId Персонаж.
     * @param int $expectedMembershipRevision Ожидаемая revision.
     *
     * @return void
     *
     * @throws GameNotFoundException Если нет строки.
     * @throws GameInvalidException Если статус не submitted или completed.
     * @throws GameConflictException Если revision устарела.
     */
    public function reject(int $gameId, int $characterId, int $expectedMembershipRevision): void
    {
        $this->assertWritableGame($gameId);
        $row = $this->ports->characters->getByPair($gameId, $characterId);
        if ($row->getStatus() !== 'submitted') {
            throw new GameInvalidException('Game character cannot be rejected');
        }

        if ($row->getMembershipRevision() !== $expectedMembershipRevision) {
            $actual = $this->actual($characterId);
            throw new GameConflictException($actual->getActualVersion(), $row->getMembershipRevision());
        }

        $this->ports->characters->deleteById($row->getId());
    }

    /**
     * Выход владельца персонажа.
     *
     * @param int $gameId Игра.
     * @param int $characterId Персонаж.
     * @param int $ownerUserId Актор.
     * @param int $expectedMembershipRevision Ожидаемая revision.
     *
     * @return GameCharacterRecord Строка.
     *
     * @throws GameNotFoundException Если нет строки или актор не владелец.
     * @throws GameInvalidException Если статус или completed.
     * @throws GameConflictException Если revision устарела.
     */
    public function leave(
        int $gameId,
        int $characterId,
        int $ownerUserId,
        int $expectedMembershipRevision,
    ): GameCharacterRecord {
        $this->assertWritableGame($gameId);
        $row = $this->openRow($gameId, $characterId);
        if ($row->getCharacterOwnerId() !== $ownerUserId) {
            throw new GameNotFoundException();
        }

        $actual = $this->actual($characterId);
        $this->assertMembership($row, $expectedMembershipRevision, $actual);
        $this->ports->gateway->transaction(function () use ($row, $gameId, $characterId): void {
            $this->ports->characters->saveLeft($row->getId(), $row->getMembershipRevision() + 1);
            $this->release->release($gameId, $characterId);
        });

        return $this->get($gameId, $characterId);
    }

    /**
     * Пишет бонус строки.
     *
     * @param int $gameId Игра.
     * @param int $characterId Персонаж.
     * @param int $expectedMembershipRevision Ожидаемая revision.
     * @param int $osBonus ОС.
     * @param int $orBonus ОР.
     * @param int $olBonus ОЛ.
     *
     * @return GameCharacterRecord Строка.
     *
     * @throws GameNotFoundException Если нет строки или персонажа.
     * @throws GameInvalidException Если статус, бонус или completed.
     * @throws GameConflictException Если revision устарела.
     */
    public function setBonus(
        int $gameId,
        int $characterId,
        int $expectedMembershipRevision,
        int $osBonus,
        int $orBonus,
        int $olBonus,
    ): GameCharacterRecord {
        $this->assertWritableGame($gameId);
        $this->assertBonus($osBonus);
        $this->assertBonus($orBonus);
        $this->assertBonus($olBonus);
        $row = $this->openRow($gameId, $characterId);
        $actual = $this->actual($characterId);
        $this->assertMembership($row, $expectedMembershipRevision, $actual);
        $this->ports->characters->saveBonus(
            $row->getId(),
            $osBonus,
            $orBonus,
            $olBonus,
            $row->getMembershipRevision() + 1,
        );

        return $this->get($gameId, $characterId);
    }

    /**
     * Потолок игры плюс бонус. null потолка остаётся null.
     *
     * @param int|null $gameCeiling Потолок игры.
     * @param int $bonus Бонус строки.
     *
     * @return int|null Лимит или null.
     */
    public function getPointsLimit(?int $gameCeiling, int $bonus): ?int
    {
        if ($gameCeiling === null) {
            return null;
        }

        return $gameCeiling + $bonus;
    }

    /**
     * Игра есть и не completed.
     *
     * @param int $gameId Игра.
     *
     * @return void
     *
     * @throws GameNotFoundException Если игры нет.
     * @throws GameInvalidException Если completed.
     */
    private function assertWritableGame(int $gameId): void
    {
        if ($this->games->get($gameId)->isCompleted()) {
            throw new GameInvalidException('Completed game is read-only');
        }
    }

    /**
     * submitted или active.
     *
     * @param int $gameId Игра.
     * @param int $characterId Персонаж.
     *
     * @return GameCharacterRecord Строка без reviewState.
     *
     * @throws GameNotFoundException Если строки нет.
     * @throws GameInvalidException Если left.
     */
    private function openRow(int $gameId, int $characterId): GameCharacterRecord
    {
        $row = $this->ports->characters->getByPair($gameId, $characterId);
        if ($row->getStatus() === 'left') {
            throw new GameInvalidException('Game character has left');
        }

        return $row;
    }

    /**
     * Revision совпала.
     *
     * @param GameCharacterRecord $row Строка.
     * @param int $expectedMembershipRevision Ожидание.
     * @param CharacterRecord $actual Лист.
     *
     * @return void
     *
     * @throws GameConflictException Если revision другая.
     */
    private function assertMembership(
        GameCharacterRecord $row,
        int $expectedMembershipRevision,
        CharacterRecord $actual,
    ): void {
        if ($row->getMembershipRevision() !== $expectedMembershipRevision) {
            throw new GameConflictException($actual->getActualVersion(), $row->getMembershipRevision());
        }
    }

    /**
     * Бонус не отрицательный.
     *
     * @param int $bonus Число.
     *
     * @return void
     *
     * @throws GameInvalidException Если меньше нуля.
     */
    private function assertBonus(int $bonus): void
    {
        if ($bonus < 0) {
            throw new GameInvalidException('Game character bonus is invalid');
        }
    }

    /**
     * Владелец игры и gm, без владельца строки.
     *
     * @param int $gameId Игра.
     * @param int $characterOwnerId Владелец персонажа.
     *
     * @return list<int> Id учёток.
     */
    private function characterChatMemberIds(int $gameId, int $characterOwnerId): array
    {
        $memberIds = [$this->games->get($gameId)->getOwnerId() => $this->games->get($gameId)->getOwnerId()];
        foreach ($this->games->getMemberList($gameId) as $member) {
            if ($member->getRole() === 'gm') {
                $memberIds[$member->getUserId()] = $member->getUserId();
            }
        }

        unset($memberIds[$characterOwnerId]);

        return array_values($memberIds);
    }

    /**
     * Причина return без краевых пробелов.
     *
     * @param string $reason Причина.
     *
     * @return string Текст.
     *
     * @throws GameInvalidException Если причина пустая.
     */
    private function trimmedReason(string $reason): string
    {
        $trimmed = trim($reason);
        if ($trimmed === '') {
            throw new GameInvalidException('Game character return reason is empty');
        }

        return $trimmed;
    }

    /**
     * Гасит open персонажа текущей сессии.
     *
     * @param int $gameId Игра.
     * @param int $characterId Персонаж.
     *
     * @return void
     *
     * @throws GameNotFoundException Если строку сняли.
     * @throws GameInvalidException Если поле.
     */
    private function cancelOpenCharacter(int $gameId, int $characterId): void
    {
        $sessions = new GameSessionRepository($this->ports->gateway);
        $processes = new GameProcessRepository($this->ports->gateway);
        $processes->cancelOpenForCharacter($sessions->findSessionId($gameId), $characterId);
    }

    /**
     * Сообщение return или null, если чата строки нет.
     *
     * @param GameCharacterRecord $row Строка.
     * @param int $actorUserId Актор.
     * @param string $reason Причина.
     *
     * @return int|null Id сообщения.
     */
    private function returnMessageId(GameCharacterRecord $row, int $actorUserId, string $reason): ?int
    {
        $chatId = $row->getDiscussionChatId();
        if ($chatId === null) {
            return null;
        }

        return $this->donorChats->postReturn($chatId, $actorUserId, $reason);
    }

    /**
     * Actual или отказ игры.
     *
     * @param int $characterId Персонаж.
     *
     * @return CharacterRecord Лист.
     *
     * @throws GameNotFoundException Если листа нет.
     */
    private function actual(int $characterId): CharacterRecord
    {
        try {
            return $this->characters->get($characterId);
        } catch (CharacterNotFoundException $exception) {
            throw new GameNotFoundException('Game character was not found', $exception);
        }
    }

    /**
     * Копия полей actual на approve.
     *
     * @param CharacterRecord $actual Лист.
     *
     * @return array<string, mixed> Snapshot.
     */
    private function snapshot(CharacterRecord $actual): array
    {
        return [
            'name' => $actual->getName(),
            'active' => $actual->isActive(),
            'spaceId' => $actual->getSpaceId(),
            'rulesRevision' => $actual->getRulesRevision(),
            'actualVersion' => $actual->getActualVersion(),
            'choices' => $actual->getChoices(),
            'sheet' => $actual->getSheet(),
        ];
    }
}
