<?php

declare(strict_types=1);

namespace Mifrial\Messages\Chat\Service;

use Mifrial\Messages\Chat\Dto\ChatHostTypes;
use Mifrial\Messages\Chat\Dto\ChatMemberRecord;
use Mifrial\Messages\Chat\Dto\ChatRecord;
use Mifrial\Messages\Chat\Dto\InboxStats;
use Mifrial\Messages\Chat\Dto\MessageAudience;
use Mifrial\Messages\Chat\Dto\MessageRecord;
use Mifrial\Messages\Chat\Repository\ChatMemberRepository;
use Mifrial\Messages\Chat\Repository\ChatMessageRepository;
use Mifrial\Messages\Chat\Repository\ChatRepository;

/**
 * JSON Chat/Member/Message: unix UTC, unread и preview.
 */
final class ChatViewAssembler
{
    /**
     * Создаёт сборщик.
     *
     * @param ChatRepository $chatRepository Чаты.
     * @param ChatMemberRepository $memberRepository Членство.
     * @param ChatMessageRepository $messageRepository Inbox stats и preview.
     *
     * @return void
     */
    public function __construct(
        private readonly ChatRepository $chatRepository,
        private readonly ChatMemberRepository $memberRepository,
        private readonly ChatMessageRepository $messageRepository,
    ) {
    }

    /**
     * Inbox актора: свои чаты, до 500.
     *
     * @param int $userId Учётка.
     *
     * @return array<int, array<string, mixed>> Chat[].
     */
    public function assembleInbox(int $userId): array
    {
        $memberships = $this->memberRepository->getByUserId($userId);
        $chats = ChatHostTypes::hostChats($this->chatRepository->getByIds($this->chatIdsOf($memberships)));
        $chatIds = $this->chatIdsOfRecords($chats);
        $members = $this->memberRepository->getByChatIds($chatIds);

        return $this->assembleChats($chats, $this->membershipsOfChatIds($memberships, $chatIds), $members, $userId);
    }

    /**
     * Host-id из набора членства; пустой список без запроса.
     *
     * @param array<int, int> $chatIds Кандидаты.
     *
     * @return array<int, int> private и group.
     */
    public function hostChatIds(array $chatIds): array
    {
        if ($chatIds === []) {
            return [];
        }

        return ChatHostTypes::hostIds($chatIds, $this->chatRepository->getByIds($chatIds));
    }

    /**
     * JSON чатов по id; пустой список без запроса.
     *
     * @param int $userId Актор (lastRead).
     * @param array<int, int> $chatIds Чаты кадра.
     *
     * @return array<int, array<string, mixed>> Chat[].
     */
    public function assembleChatsByIds(int $userId, array $chatIds): array
    {
        if ($chatIds === []) {
            return [];
        }

        $chats = $this->chatRepository->getByIds($chatIds);
        $members = $this->memberRepository->getByChatIds($chatIds);

        return $this->assembleChats($chats, $this->membershipsOf($userId, $members), $members, $userId);
    }

    /**
     * JSON одного сообщения.
     *
     * @param MessageRecord $messageRecord Строка.
     * @param string $username Имя автора или ''.
     *
     * @return array<string, mixed> Message.
     */
    public function assembleMessage(MessageRecord $messageRecord, string $username): array
    {
        return [
            'id' => $messageRecord->getId(),
            'chatId' => $messageRecord->getChatId(),
            'userId' => $messageRecord->getUserId(),
            'username' => $username,
            'content' => $messageRecord->getContent(),
            'attachments' => $messageRecord->getAttachments(),
            'createdAt' => $messageRecord->getCreatedAt()->toUnix(),
            'updatedAt' => $messageRecord->getUpdatedAt()->toUnix(),
            'visibility' => $this->assembleVisibility($messageRecord),
        ];
    }

    /**
     * Id чатов из членств.
     *
     * @param array<int, ChatMemberRecord> $memberships Строки.
     *
     * @return array<int, int> Id.
     */
    private function chatIdsOf(array $memberships): array
    {
        $chatIds = [];
        foreach ($memberships as $membership) {
            $chatIds[] = $membership->getChatId();
        }

        return $chatIds;
    }

    /**
     * Id из записей чата.
     *
     * @param array<int, ChatRecord> $chats Строки.
     *
     * @return array<int, int> Id.
     */
    private function chatIdsOfRecords(array $chats): array
    {
        $chatIds = [];
        foreach ($chats as $chat) {
            $chatIds[] = $chat->getId();
        }

        return $chatIds;
    }

    /**
     * Членства только host-чатов.
     *
     * @param array<int, ChatMemberRecord> $memberships Строки.
     * @param array<int, int> $chatIds Host-id.
     *
     * @return array<int, ChatMemberRecord> Отфильтрованные.
     */
    private function membershipsOfChatIds(array $memberships, array $chatIds): array
    {
        $allowed = array_fill_keys($chatIds, true);
        $filtered = [];
        foreach ($memberships as $membership) {
            if (isset($allowed[$membership->getChatId()])) {
                $filtered[] = $membership;
            }
        }

        return $filtered;
    }

    /**
     * Членства актора из пачки членов.
     *
     * @param int $userId Актор.
     * @param array<int, ChatMemberRecord> $members Члены.
     *
     * @return array<int, ChatMemberRecord> Строки актора.
     */
    private function membershipsOf(int $userId, array $members): array
    {
        $actorMemberships = [];
        foreach ($members as $member) {
            if ($member->getUserId() === $userId) {
                $actorMemberships[] = $member;
            }
        }

        return $actorMemberships;
    }

    /**
     * JSON чатов inbox.
     *
     * @param array<int, ChatRecord> $chats Чаты.
     * @param array<int, ChatMemberRecord> $actorMemberships Членства актора.
     * @param array<int, ChatMemberRecord> $allMembers Члены этих чатов.
     * @param int $viewerId Зритель unread/preview.
     *
     * @return array<int, array<string, mixed>> Chat[].
     */
    private function assembleChats(
        array $chats,
        array $actorMemberships,
        array $allMembers,
        int $viewerId,
    ): array {
        $chatIds = $this->chatIdsOf($actorMemberships);
        $inboxStats = $this->messageRepository->getInboxStats($chatIds, $viewerId);
        $previewByChatId = $this->previewByChatId($inboxStats);
        $membershipByChatId = $this->indexByChatId($actorMemberships);
        $membersByChatId = $this->groupByChatId($allMembers);
        $views = [];
        foreach ($chats as $chat) {
            $views[] = $this->assembleChat(
                $chat,
                $membershipByChatId[$chat->getId()] ?? null,
                $membersByChatId[$chat->getId()] ?? [],
                $inboxStats,
                $previewByChatId[$chat->getId()] ?? null,
            );
        }

        return $views;
    }

    /**
     * Preview-строки по chat id.
     *
     * @param InboxStats $inboxStats Статы.
     *
     * @return array<int, MessageRecord> chat id → строка.
     */
    private function previewByChatId(InboxStats $inboxStats): array
    {
        $recordsById = [];
        foreach ($this->messageRepository->getByIds($inboxStats->lastMessageIds()) as $messageRecord) {
            $recordsById[$messageRecord->getId()] = $messageRecord;
        }

        $previewByChatId = [];
        foreach ($recordsById as $messageRecord) {
            $previewByChatId[$messageRecord->getChatId()] = $messageRecord;
        }

        return $previewByChatId;
    }

    /**
     * JSON одного чата.
     *
     * @param ChatRecord $chat Чат.
     * @param ChatMemberRecord|null $actorMembership Членство актора.
     * @param array<int, ChatMemberRecord> $members Члены.
     * @param InboxStats $inboxStats Unread.
     * @param MessageRecord|null $previewMessage Последнее видимое.
     *
     * @return array<string, mixed> Chat.
     */
    private function assembleChat(
        ChatRecord $chat,
        ?ChatMemberRecord $actorMembership,
        array $members,
        InboxStats $inboxStats,
        ?MessageRecord $previewMessage,
    ): array {
        $view = [
            'id' => $chat->getId(),
            'type' => $chat->getType(),
            'name' => $chat->getName(),
            'unreadCount' => $inboxStats->unreadCountOf($chat->getId()),
            'lastReadMessageId' => $actorMembership?->getLastReadMessageId(),
            'lastMessageAt' => $previewMessage === null
                ? $chat->getCreatedAt()->toUnix()
                : $previewMessage->getCreatedAt()->toUnix(),
            'members' => $this->assembleMembers($members),
        ];
        if ($previewMessage !== null) {
            $view['lastMessage'] = $previewMessage->getContent();
        }

        return $view;
    }

    /**
     * JSON visibility сообщения.
     *
     * @param MessageRecord $messageRecord Строка.
     *
     * @return array<string, mixed> { all: true } или { all: false, forUsers }.
     */
    private function assembleVisibility(MessageRecord $messageRecord): array
    {
        if ($messageRecord->getAudience() === MessageAudience::ALL) {
            return ['all' => true];
        }

        return [
            'all' => false,
            'forUsers' => $messageRecord->getAudienceUserIds(),
        ];
    }

    /**
     * JSON членов.
     *
     * @param array<int, ChatMemberRecord> $members Строки.
     *
     * @return array<int, array<string, mixed>> Member[].
     */
    private function assembleMembers(array $members): array
    {
        $views = [];
        foreach ($members as $member) {
            $views[] = [
                'userId' => $member->getUserId(),
                'status' => 'member',
                'joinedAt' => $member->getJoinedAt()->toUnix(),
            ];
        }

        return $views;
    }

    /**
     * Членство актора по chat id.
     *
     * @param array<int, ChatMemberRecord> $memberships Строки.
     *
     * @return array<int, ChatMemberRecord> chat id → строка.
     */
    private function indexByChatId(array $memberships): array
    {
        $indexed = [];
        foreach ($memberships as $membership) {
            $indexed[$membership->getChatId()] = $membership;
        }

        return $indexed;
    }

    /**
     * Члены по chat id.
     *
     * @param array<int, ChatMemberRecord> $members Строки.
     *
     * @return array<int, array<int, ChatMemberRecord>> chat id → члены.
     */
    private function groupByChatId(array $members): array
    {
        $grouped = [];
        foreach ($members as $member) {
            $grouped[$member->getChatId()][] = $member;
        }

        return $grouped;
    }
}
