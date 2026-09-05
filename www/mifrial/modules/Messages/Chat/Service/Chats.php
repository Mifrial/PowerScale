<?php

declare(strict_types=1);

// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.TooManyPublicMethods
// 10 методов IChats плюс __construct; отдельный порт не нужен.

namespace Mifrial\Messages\Chat\Service;

use Mifrial\Core\Kernel\Value\DateTime;
use Mifrial\Core\User\Exception\UserNotFoundException;
use Mifrial\Core\User\Interface\Service\IUserAccounts;
use Mifrial\Messages\Chat\Dto\ChatRecord;
use Mifrial\Messages\Chat\Dto\MessageAudience;
use Mifrial\Messages\Chat\Dto\MessagePage;
use Mifrial\Messages\Chat\Dto\NewGroupChat;
use Mifrial\Messages\Chat\Exception\ChatDuplicateException;
use Mifrial\Messages\Chat\Exception\ChatInvalidException;
use Mifrial\Messages\Chat\Exception\ChatNotFoundException;
use Mifrial\Messages\Chat\Interface\Service\IChats;
use Mifrial\Messages\Chat\Repository\ChatMemberRepository;
use Mifrial\Messages\Chat\Repository\ChatMessageRepository;
use Mifrial\Messages\Chat\Repository\ChatRepository;

/**
 * Сценарий чатов: без SmartTable и без имён колонок.
 */
final class Chats implements IChats
{
    /**
     * Создаёт фасад.
     *
     * @param ChatRepository $chatRepository Чаты.
     * @param ChatMemberRepository $memberRepository Членство.
     * @param ChatMessageRepository $messageRepository Сообщения.
     * @param IUserAccounts $userAccounts Учётки соседа.
     * @param ChatInputNormalizer $inputNormalizer Разбор входа.
     *
     * @return void
     */
    public function __construct(
        private readonly ChatRepository $chatRepository,
        private readonly ChatMemberRepository $memberRepository,
        private readonly ChatMessageRepository $messageRepository,
        private readonly IUserAccounts $userAccounts,
        private readonly ChatInputNormalizer $inputNormalizer = new ChatInputNormalizer(),
    ) {
    }

    /**
     * Создаёт или возвращает private-чат двух учёток.
     *
     * @param int $firstUserId Первая учётка.
     * @param int $secondUserId Вторая учётка.
     *
     * @return int Id чата.
     *
     * @throws ChatInvalidException Если пара из одного id.
     * @throws ChatNotFoundException Если учётки нет.
     */
    public function addPrivate(int $firstUserId, int $secondUserId): int
    {
        if ($firstUserId === $secondUserId) {
            throw new ChatInvalidException('Private chat requires two users');
        }

        $this->requireExistingUser($firstUserId);
        $this->requireExistingUser($secondUserId);
        $chatId = $this->chatRepository->addOrFindPrivate($firstUserId, $secondUserId);
        $this->ensureMember($chatId, $firstUserId);
        $this->ensureMember($chatId, $secondUserId);

        return $chatId;
    }

    /**
     * Создаёт групповой чат.
     *
     * @param NewGroupChat $newGroupChat Группа.
     *
     * @return int Id чата.
     *
     * @throws ChatInvalidException Если имя пусто.
     * @throws ChatNotFoundException Если учётки нет.
     */
    public function addGroup(NewGroupChat $newGroupChat): int
    {
        $name = trim($newGroupChat->getName());
        if ($name === '') {
            throw new ChatInvalidException('Group chat name is empty');
        }

        $memberIds = $this->uniqueMemberIds($newGroupChat);
        foreach ($memberIds as $memberId) {
            $this->requireExistingUser($memberId);
        }

        $chatId = $this->chatRepository->addGroup($name);
        foreach ($memberIds as $memberId) {
            $this->memberRepository->add($chatId, $memberId);
        }

        return $chatId;
    }

    /**
     * Возвращает чат по id без проверки членства.
     *
     * @param int $chatId Идентификатор.
     *
     * @return ChatRecord Чат.
     *
     * @throws ChatNotFoundException Если чата нет.
     */
    public function getById(int $chatId): ChatRecord
    {
        return $this->chatRepository->getById($chatId);
    }

    /**
     * Id чатов пользователя, до 500.
     *
     * @param int $userId Учётка.
     *
     * @return array<int, int> Id чатов.
     *
     * @throws ChatNotFoundException Если учётки нет.
     */
    public function getChatIdsOfUser(int $userId): array
    {
        $this->requireExistingUser($userId);

        return $this->memberRepository->getChatIdsOfUser($userId);
    }

    /**
     * Id членов чата, до 500.
     *
     * @param int $chatId Чат.
     *
     * @return array<int, int> user id.
     *
     * @throws ChatNotFoundException Если чата нет.
     */
    public function getMemberIds(int $chatId): array
    {
        $this->chatRepository->getById($chatId);

        return $this->memberRepository->getMemberIds($chatId);
    }

    /**
     * Добавляет членство в group-чат.
     *
     * @param int $chatId Чат.
     * @param int $userId Учётка.
     *
     * @return void
     *
     * @throws ChatNotFoundException Если нет чата или учётки.
     * @throws ChatInvalidException Если чат private.
     * @throws ChatDuplicateException Если пара уже есть.
     */
    public function addMember(int $chatId, int $userId): void
    {
        $this->assertGroupChat($this->chatRepository->getById($chatId));
        $this->requireExistingUser($userId);
        $this->memberRepository->add($chatId, $userId);
    }

    /**
     * Снимает членство с group-чата.
     *
     * @param int $chatId Чат.
     * @param int $userId Учётка.
     *
     * @return void
     *
     * @throws ChatNotFoundException Если нет чата или членства.
     * @throws ChatInvalidException Если private или это последний член.
     */
    public function removeMember(int $chatId, int $userId): void
    {
        $this->assertGroupChat($this->chatRepository->getById($chatId));
        $memberId = $this->memberRepository->findId($chatId, $userId);
        if ($memberId === null) {
            throw new ChatNotFoundException();
        }

        if ($this->memberRepository->countMembers($chatId) <= 1) {
            throw new ChatInvalidException('Group chat requires a member');
        }

        $this->memberRepository->deleteById($memberId);
    }

    /**
     * Пишет сообщение и обновляет updated_at чата.
     *
     * @param int $chatId Чат.
     * @param int $userId Автор; должен быть членом.
     * @param string $content Текст.
     * @param array<int, mixed> $attachments Вложения.
     * @param MessageAudience|null $audience Аудитория; null — всем.
     *
     * @return int Id сообщения.
     *
     * @throws ChatNotFoundException Если нет чата или автор не член.
     * @throws ChatInvalidException Если пустое send или вложение без type.
     */
    public function send(
        int $chatId,
        int $userId,
        string $content,
        array $attachments,
        ?MessageAudience $audience = null,
    ): int {
        $this->requireMember($chatId, $userId);
        $normalized = $this->inputNormalizer->normalizeSend($content, $attachments);
        $sentAt = DateTime::now();
        $messageId = $this->messageRepository->add(
            $chatId,
            $userId,
            $normalized['content'],
            $normalized['attachments'],
            $sentAt,
            $audience ?? MessageAudience::all(),
        );
        $this->chatRepository->updateUpdatedAt($chatId, $sentAt);

        return $messageId;
    }

    /**
     * Страница сообщений: created_at desc, id desc.
     *
     * @param int $chatId Чат.
     * @param int $userId Читатель; должен быть членом.
     * @param int $limit Размер 1…500.
     * @param int $offset Сдвиг ≥ 0.
     *
     * @return MessagePage Страница.
     *
     * @throws ChatNotFoundException Если нет чата или читатель не член.
     * @throws ChatInvalidException Если страница недопустима.
     */
    public function findMessagePage(int $chatId, int $userId, int $limit, int $offset): MessagePage
    {
        $this->requireMember($chatId, $userId);

        return $this->messageRepository->findPage($chatId, $userId, $limit, $offset);
    }

    /**
     * Ставит last_read на max id видимых сообщений чата.
     *
     * @param int $chatId Чат.
     * @param int $userId Член.
     *
     * @return void
     *
     * @throws ChatNotFoundException Если нет чата или не член.
     */
    public function markRead(int $chatId, int $userId): void
    {
        $this->requireMember($chatId, $userId);
        $this->memberRepository->updateLastRead(
            $chatId,
            $userId,
            $this->messageRepository->findMaxId($chatId, $userId),
        );
    }

    /**
     * Проверяет, что учётка есть; USER_NOT_FOUND → CHAT_NOT_FOUND.
     *
     * @param int $userId Учётка.
     *
     * @return void
     *
     * @throws ChatNotFoundException Если учётки нет.
     */
    private function requireExistingUser(int $userId): void
    {
        try {
            $this->userAccounts->getById($userId);
        } catch (UserNotFoundException $exception) {
            throw new ChatNotFoundException($exception);
        }
    }

    /**
     * Чат есть и userId в членах, иначе NOT_FOUND.
     *
     * @param int $chatId Чат.
     * @param int $userId Учётка.
     *
     * @return void
     *
     * @throws ChatNotFoundException Если нет чата или не член.
     */
    private function requireMember(int $chatId, int $userId): void
    {
        $this->chatRepository->getById($chatId);
        if ($this->memberRepository->findId($chatId, $userId) === null) {
            throw new ChatNotFoundException();
        }
    }

    /**
     * Добавляет членство, если его ещё нет.
     *
     * @param int $chatId Чат.
     * @param int $userId Учётка.
     *
     * @return void
     *
     * @throws ChatInvalidException Если значения недопустимы.
     */
    private function ensureMember(int $chatId, int $userId): void
    {
        if ($this->memberRepository->findId($chatId, $userId) !== null) {
            return;
        }

        try {
            $this->memberRepository->add($chatId, $userId);
        } catch (ChatDuplicateException) {
            return;
        }
    }

    /**
     * Создатель и уникальные memberIds.
     *
     * @param NewGroupChat $newGroupChat Группа.
     *
     * @return array<int, int> Id членов.
     */
    private function uniqueMemberIds(NewGroupChat $newGroupChat): array
    {
        $uniqueIds = [$newGroupChat->getCreatorId() => $newGroupChat->getCreatorId()];
        foreach ($newGroupChat->getMemberIds() as $memberId) {
            $uniqueIds[$memberId] = $memberId;
        }

        return array_values($uniqueIds);
    }

    /**
     * Private нельзя менять состав.
     *
     * @param ChatRecord $chatRecord Чат.
     *
     * @return void
     *
     * @throws ChatInvalidException Если private.
     */
    private function assertGroupChat(ChatRecord $chatRecord): void
    {
        if ($chatRecord->getType() === 'private') {
            throw new ChatInvalidException('Private chat membership is fixed');
        }
    }
}
