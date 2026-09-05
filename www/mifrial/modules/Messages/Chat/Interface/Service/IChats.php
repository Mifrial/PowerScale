<?php

declare(strict_types=1);

namespace Mifrial\Messages\Chat\Interface\Service;

use Mifrial\Messages\Chat\Dto\ChatRecord;
use Mifrial\Messages\Chat\Dto\MessageAudience;
use Mifrial\Messages\Chat\Dto\MessagePage;
use Mifrial\Messages\Chat\Dto\NewGroupChat;
use Mifrial\Messages\Chat\Exception\ChatDuplicateException;
use Mifrial\Messages\Chat\Exception\ChatInvalidException;
use Mifrial\Messages\Chat\Exception\ChatNotFoundException;

/**
 * Фасад чатов для соседей: без схемы и без таблиц.
 */
interface IChats
{
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
    public function addPrivate(int $firstUserId, int $secondUserId): int;

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
    public function addGroup(NewGroupChat $newGroupChat): int;

    /**
     * Возвращает чат по id без проверки членства.
     *
     * @param int $chatId Идентификатор.
     *
     * @return ChatRecord Чат.
     *
     * @throws ChatNotFoundException Если чата нет.
     */
    public function getById(int $chatId): ChatRecord;

    /**
     * Id чатов пользователя, до 500.
     *
     * @param int $userId Учётка.
     *
     * @return array<int, int> Id чатов.
     *
     * @throws ChatNotFoundException Если учётки нет.
     */
    public function getChatIdsOfUser(int $userId): array;

    /**
     * Id членов чата, до 500.
     *
     * @param int $chatId Чат.
     *
     * @return array<int, int> user id.
     *
     * @throws ChatNotFoundException Если чата нет.
     */
    public function getMemberIds(int $chatId): array;

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
    public function addMember(int $chatId, int $userId): void;

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
    public function removeMember(int $chatId, int $userId): void;

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
    ): int;

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
    public function findMessagePage(int $chatId, int $userId, int $limit, int $offset): MessagePage;

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
    public function markRead(int $chatId, int $userId): void;
}
