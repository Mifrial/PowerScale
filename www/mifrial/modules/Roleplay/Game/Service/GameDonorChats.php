<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Messages\Chat\Dto\NewGroupChat;
use Mifrial\Messages\Chat\Exception\ChatDuplicateException;
use Mifrial\Messages\Chat\Exception\ChatInvalidException;
use Mifrial\Messages\Chat\Exception\ChatNotFoundException;
use Mifrial\Messages\Chat\Interface\Service\IChats;
use Mifrial\Messages\Chat\Interface\Service\IChatTypeRegistry;
use Mifrial\Roleplay\Game\Dto\GameCharacterRecord;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Game\Repository\GameCharacterRepository;

/**
 * Чаты игры через IChats. Имена типов — строки этого модуля.
 */
final class GameDonorChats
{
    private const TABLE = 'game';

    private const DISCUSSION = 'game_discussion';

    private const CHARACTER = 'character_discussion';

    /**
     * Регистрирует три строки донора.
     *
     * @param IChatTypeRegistry $chatTypeRegistry Реестр процесса.
     * @param IChats $chats Фасад чата.
     * @param GameCharacterRepository $characterRepository Строки персонажей.
     *
     * @return void
     */
    public function __construct(
        IChatTypeRegistry $chatTypeRegistry,
        private readonly IChats $chats,
        private readonly GameCharacterRepository $characterRepository,
    ) {
        $chatTypeRegistry->register(self::TABLE);
        $chatTypeRegistry->register(self::DISCUSSION);
        $chatTypeRegistry->register(self::CHARACTER);
    }

    /**
     * Стол и обсуждение. Создатель — владелец игры.
     *
     * @param int $ownerUserId Владелец.
     * @param string $name Имя игры.
     *
     * @return array{gameChatId: int, discussionChatId: int} Id.
     *
     * @throws GameNotFoundException Если учётки нет.
     * @throws GameInvalidException Если тип или имя.
     */
    public function openGameChats(int $ownerUserId, string $name): array
    {
        return [
            'gameChatId' => $this->openTyped(self::TABLE, $name, $ownerUserId, []),
            'discussionChatId' => $this->openTyped(self::DISCUSSION, $name, $ownerUserId, []),
        ];
    }

    /**
     * Чат строки. Имя — id персонажа.
     *
     * @param int $characterId Персонаж.
     * @param int $ownerUserId Владелец персонажа.
     * @param array<int, int> $memberIds Прочие члены.
     *
     * @return int Id чата.
     *
     * @throws GameNotFoundException Если учётки нет.
     * @throws GameInvalidException Если тип или имя.
     */
    public function openCharacterChat(int $characterId, int $ownerUserId, array $memberIds): int
    {
        return $this->openTyped(self::CHARACTER, (string) $characterId, $ownerUserId, $memberIds);
    }

    /**
     * Сажает участника на стол и, для gm, в чаты строк.
     *
     * @param GameRecord $game Игра.
     * @param int $userId Учётка.
     * @param string $role Роль.
     *
     * @return void
     */
    public function seatJoined(GameRecord $game, int $userId, string $role): void
    {
        $this->addSeatIfPresent($game->getGameChatId(), $userId);
        $this->addSeatIfPresent($game->getDiscussionChatId(), $userId);
        if ($role === 'gm') {
            $this->changeCharacterSeats($game, $userId, true);
        }
    }

    /**
     * Снимает участника со стола и, для gm, с чатов строк.
     *
     * @param GameRecord $game Игра.
     * @param int $userId Учётка.
     * @param string $role Роль до снятия.
     *
     * @return void
     */
    public function seatLeft(GameRecord $game, int $userId, string $role): void
    {
        $this->removeSeatIfPresent($game->getGameChatId(), $userId);
        $this->removeSeatIfPresent($game->getDiscussionChatId(), $userId);
        if ($role === 'gm') {
            $this->changeCharacterSeats($game, $userId, false);
        }
    }

    /**
     * Сажает или снимает gm в чатах строк.
     *
     * @param GameRecord $game Игра.
     * @param int $userId Учётка.
     * @param bool $grant Посадить, если true.
     *
     * @return void
     */
    public function seatGm(GameRecord $game, int $userId, bool $grant): void
    {
        $this->changeCharacterSeats($game, $userId, $grant);
    }

    /**
     * Сажает актора, если его нет, и пишет причину.
     *
     * @param int $chatId Чат строки.
     * @param int $authorUserId Актор.
     * @param string $reason Причина.
     *
     * @return int Id сообщения.
     *
     * @throws GameNotFoundException Если нет чата или учётки.
     * @throws GameInvalidException Если текст пуст.
     */
    public function postReturn(int $chatId, int $authorUserId, string $reason): int
    {
        $this->addSeat($chatId, $authorUserId);

        return $this->guard(fn (): int => $this->chats->send($chatId, $authorUserId, $reason, []));
    }

    /**
     * @param array<int, int> $memberIds Прочие члены.
     */
    private function openTyped(string $type, string $name, int $ownerUserId, array $memberIds): int
    {
        $newGroupChat = NewGroupChat::fromNormalized([
            'name' => $name,
            'creatorId' => $ownerUserId,
            'memberIds' => $memberIds,
        ]);

        return $this->guard(fn (): int => $this->chats->addTyped($type, $newGroupChat));
    }

    /**
     * @param bool $grant Посадить, если true.
     */
    private function changeCharacterSeats(GameRecord $game, int $userId, bool $grant): void
    {
        if ($userId === $game->getOwnerId()) {
            return;
        }

        foreach ($this->characterRepository->getListByGame($game->getId(), null) as $row) {
            $this->changeCharacterSeat($row, $userId, $grant);
        }
    }

    /**
     * @param bool $grant Посадить, если true.
     */
    private function changeCharacterSeat(GameCharacterRecord $row, int $userId, bool $grant): void
    {
        $chatId = $row->getDiscussionChatId();
        if ($chatId === null || $userId === $row->getCharacterOwnerId()) {
            return;
        }

        if ($grant) {
            $this->addSeat($chatId, $userId);

            return;
        }

        $this->removeSeat($chatId, $userId);
    }

    private function addSeatIfPresent(?int $chatId, int $userId): void
    {
        if ($chatId !== null) {
            $this->addSeat($chatId, $userId);
        }
    }

    private function removeSeatIfPresent(?int $chatId, int $userId): void
    {
        if ($chatId !== null) {
            $this->removeSeat($chatId, $userId);
        }
    }

    private function addSeat(int $chatId, int $userId): void
    {
        try {
            $this->chats->addMember($chatId, $userId);
        } catch (ChatDuplicateException) {
            return;
        } catch (ChatNotFoundException $exception) {
            throw new GameNotFoundException('Chat was not found', $exception);
        } catch (ChatInvalidException $exception) {
            throw new GameInvalidException('Chat values are invalid', $exception);
        }
    }

    private function removeSeat(int $chatId, int $userId): void
    {
        $this->guard(function () use ($chatId, $userId): void {
            $this->chats->removeMember($chatId, $userId);
        });
    }

    /**
     * Переводит ошибку чата в ошибку игры.
     *
     * @param callable $work Вызов чата.
     *
     * @return mixed Результат.
     */
    private function guard(callable $work): mixed
    {
        try {
            return $work();
        } catch (ChatNotFoundException $exception) {
            throw new GameNotFoundException('Chat was not found', $exception);
        } catch (ChatInvalidException $exception) {
            throw new GameInvalidException('Chat values are invalid', $exception);
        }
    }
}
