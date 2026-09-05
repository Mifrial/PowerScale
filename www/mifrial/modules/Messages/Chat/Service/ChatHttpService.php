<?php

declare(strict_types=1);

namespace Mifrial\Messages\Chat\Service;

use Mifrial\Core\Kernel\Exception\ActionException;
use Mifrial\Core\Kernel\Value\DateTime;
use Mifrial\Core\User\Interface\Service\IUserAccess;
use Mifrial\Core\User\Interface\Service\IUserAccounts;
use Mifrial\Messages\Chat\Dto\Action\AddGroupChatInput;
use Mifrial\Messages\Chat\Dto\Action\AddPrivateChatInput;
use Mifrial\Messages\Chat\Dto\Action\FindMessagePageInput;
use Mifrial\Messages\Chat\Dto\Action\SendMessageInput;
use Mifrial\Messages\Chat\Dto\Action\UpdateMessageVisibilityInput;
use Mifrial\Messages\Chat\Dto\MessageAudience;
use Mifrial\Messages\Chat\Dto\MessageRecord;
use Mifrial\Messages\Chat\Exception\ChatInvalidException;
use Mifrial\Messages\Chat\Exception\ChatNotFoundException;
use Mifrial\Messages\Chat\Interface\Service\IChats;
use Mifrial\Messages\Chat\Repository\ChatMessageRepository;

/**
 * HTTP-сценарии чата: актор, фасад, JSON.
 */
final class ChatHttpService
{
    /**
     * Создаёт сценарий.
     *
     * @param IUserAccess $userAccess Guard.
     * @param IChats $chats Фасад.
     * @param IUserAccounts $userAccounts Имена авторов.
     * @param ChatViewAssembler $viewAssembler JSON.
     * @param ChatMessageRepository $messageRepository Строка после send.
     *
     * @return void
     */
    public function __construct(
        private readonly IUserAccess $userAccess,
        private readonly IChats $chats,
        private readonly IUserAccounts $userAccounts,
        private readonly ChatViewAssembler $viewAssembler,
        private readonly ChatMessageRepository $messageRepository,
    ) {
    }

    /**
     * Свои чаты, до 500.
     *
     * @return array<int, array<string, mixed>> Chat[].
     *
     * @throws ActionException AUTH_REQUIRED, если актора нет.
     */
    public function getChats(): array
    {
        $actor = $this->userAccess->requireActor();

        return $this->viewAssembler->assembleInbox($actor->getUserId());
    }

    /**
     * Создаёт или возвращает private-чат с другой учёткой.
     *
     * @param AddPrivateChatInput $input Вторая учётка.
     *
     * @return array<string, mixed> Chat.
     *
     * @throws ActionException AUTH_REQUIRED, если актора нет.
     * @throws ChatNotFoundException Если учётки нет.
     * @throws ChatInvalidException Если пара из одного id.
     */
    public function addPrivate(AddPrivateChatInput $input): array
    {
        $actorId = $this->userAccess->requireActor()->getUserId();

        return $this->chatViewOf($actorId, $this->chats->addPrivate($actorId, $input->userId));
    }

    /**
     * Создаёт групповой чат; актор всегда член.
     *
     * @param AddGroupChatInput $input Имя и члены.
     *
     * @return array<string, mixed> Chat.
     *
     * @throws ActionException AUTH_REQUIRED, если актора нет.
     * @throws ChatNotFoundException Если учётки нет.
     * @throws ChatInvalidException Если имя пусто.
     */
    public function addGroup(AddGroupChatInput $input): array
    {
        $actorId = $this->userAccess->requireActor()->getUserId();
        $chatId = $this->chats->addGroup(
            (new ChatInputNormalizer())->newGroupChat($input->name, $actorId, $input->memberIds),
        );

        return $this->chatViewOf($actorId, $chatId);
    }

    /**
     * Страница сообщений чата.
     *
     * @param FindMessagePageInput $input Страница.
     *
     * @return array{items: array<int, array<string, mixed>>, total: int} Страница.
     *
     * @throws ActionException AUTH_REQUIRED, если актора нет.
     * @throws ChatNotFoundException Если нет чата или не член.
     * @throws ChatInvalidException Если страница недопустима.
     */
    public function findMessagePage(FindMessagePageInput $input): array
    {
        $actor = $this->userAccess->requireActor();
        $page = $this->chats->findMessagePage(
            $input->chatId,
            $actor->getUserId(),
            $input->limit,
            $input->offset,
        );

        return [
            'items' => $this->assembleMessageItems($page->getItems()),
            'total' => $page->getTotal(),
        ];
    }

    /**
     * Пишет сообщение и возвращает JSON.
     *
     * @param SendMessageInput $input Send.
     *
     * @return array<string, mixed> Message.
     *
     * @throws ActionException AUTH_REQUIRED, если актора нет.
     * @throws ChatNotFoundException Если нет чата или не член.
     * @throws ChatInvalidException Если пустой send или вложение.
     */
    public function sendMessage(SendMessageInput $input): array
    {
        $actor = $this->userAccess->requireActor();
        $messageId = $this->chats->send(
            $input->chatId,
            $actor->getUserId(),
            $input->content,
            $input->attachments,
            $this->audienceOf($input->visibility, $input->chatId),
        );
        $messageRecord = $this->messageRepository->getById($messageId);

        return $this->viewAssembler->assembleMessage(
            $messageRecord,
            $this->usernameOf($messageRecord->getUserId()),
        );
    }

    /**
     * Ставит last_read на max id видимых сообщений.
     *
     * @param int $chatId Чат.
     *
     * @return null Успех без data.
     *
     * @throws ActionException AUTH_REQUIRED, если актора нет.
     * @throws ChatNotFoundException Если нет чата или не член.
     */
    public function markChatRead(int $chatId): mixed
    {
        $actor = $this->userAccess->requireActor();
        $this->chats->markRead($chatId, $actor->getUserId());

        return null;
    }

    /**
     * Меняет аудиторию своего сообщения.
     *
     * @param UpdateMessageVisibilityInput $input Смена.
     *
     * @return array<string, mixed> Message.
     *
     * @throws ActionException AUTH_REQUIRED, если актора нет.
     * @throws ChatNotFoundException Если нет чата, не член, не автор или чужой чат.
     * @throws ChatInvalidException Если visibility недопустима.
     */
    public function updateMessageVisibility(UpdateMessageVisibilityInput $input): array
    {
        $actor = $this->userAccess->requireActor();
        $actorId = $actor->getUserId();
        $this->assertMember($input->chatId, $actorId);
        $messageRecord = $this->messageRepository->getById($input->messageId);
        if ($messageRecord->getChatId() !== $input->chatId || $messageRecord->getUserId() !== $actorId) {
            throw new ChatNotFoundException();
        }

        $this->messageRepository->updateAudience(
            $input->messageId,
            $this->audienceOf($input->visibility, $input->chatId),
            DateTime::now(),
        );

        return $this->viewAssembler->assembleMessage(
            $this->messageRepository->getById($input->messageId),
            $this->usernameOf($actorId),
        );
    }

    /**
     * JSON одного чата после create.
     *
     * @param int $actorId Актор.
     * @param int $chatId Чат.
     *
     * @return array<string, mixed> Chat.
     *
     * @throws ChatNotFoundException Если сборка пуста.
     */
    private function chatViewOf(int $actorId, int $chatId): array
    {
        $views = $this->viewAssembler->assembleChatsByIds($actorId, [$chatId]);
        if ($views === []) {
            throw new ChatNotFoundException();
        }

        return $views[0];
    }

    /**
     * JSON сообщений страницы.
     *
     * @param array<int, MessageRecord> $messageRecords Строки.
     *
     * @return array<int, array<string, mixed>> Message[].
     */
    private function assembleMessageItems(array $messageRecords): array
    {
        $namesByUserId = $this->namesByUserId($messageRecords);
        $items = [];
        foreach ($messageRecords as $messageRecord) {
            $items[] = $this->viewAssembler->assembleMessage(
                $messageRecord,
                $namesByUserId[$messageRecord->getUserId()] ?? '',
            );
        }

        return $items;
    }

    /**
     * Имена авторов пачкой; нет учётки → нет ключа.
     *
     * @param array<int, MessageRecord> $messageRecords Строки.
     *
     * @return array<int, string> user id → name.
     */
    private function namesByUserId(array $messageRecords): array
    {
        $userIds = [];
        foreach ($messageRecords as $messageRecord) {
            $userIds[] = $messageRecord->getUserId();
        }

        $namesByUserId = [];
        foreach ($this->userAccounts->getByIds($userIds) as $userRecord) {
            $namesByUserId[$userRecord->getId()] = $userRecord->getName();
        }

        return $namesByUserId;
    }

    /**
     * Имя учётки или пустая строка.
     *
     * @param int $userId Автор.
     *
     * @return string Имя.
     */
    private function usernameOf(int $userId): string
    {
        $userRecords = $this->userAccounts->getByIds([$userId]);
        if ($userRecords === []) {
            return '';
        }

        return $userRecords[0]->getName();
    }

    /**
     * Разбор visibility; члены чата с фасада.
     *
     * @param mixed $visibility JSON.
     * @param int $chatId Чат.
     *
     * @return MessageAudience Аудитория.
     *
     * @throws ChatNotFoundException Если чата нет.
     * @throws ChatInvalidException Если visibility недопустима.
     */
    private function audienceOf(mixed $visibility, int $chatId): MessageAudience
    {
        return (new ChatInputNormalizer())->normalizeAudience(
            $visibility,
            $this->chats->getMemberIds($chatId),
        );
    }

    /**
     * Актор — член чата.
     *
     * @param int $chatId Чат.
     * @param int $userId Актор.
     *
     * @return void
     *
     * @throws ChatNotFoundException Если нет чата или не член.
     */
    private function assertMember(int $chatId, int $userId): void
    {
        if (!in_array($userId, $this->chats->getMemberIds($chatId), true)) {
            throw new ChatNotFoundException();
        }
    }
}
