<?php

declare(strict_types=1);

namespace Mifrial\Messages\Chat\Repository;

use Closure;
use Mifrial\Core\Kernel\Value\DateTime;
use Mifrial\Core\SmartTable\Dto\AggregateQuery;
use Mifrial\Core\SmartTable\Dto\CountField;
use Mifrial\Core\SmartTable\Dto\ListQuery;
use Mifrial\Core\SmartTable\Dto\MaxField;
use Mifrial\Core\SmartTable\Dto\OuterColumn;
use Mifrial\Core\SmartTable\Dto\SubqueryValue;
use Mifrial\Core\SmartTable\Exception\Field\FieldInvalidException;
use Mifrial\Core\SmartTable\Exception\Field\FieldRequiredException;
use Mifrial\Core\SmartTable\Exception\Map\MapInvalidException;
use Mifrial\Core\SmartTable\Exception\Row\RowNotFoundException;
use Mifrial\Core\SmartTable\Exception\Row\UniqueConstraintException;
use Mifrial\Core\SmartTable\Interface\Service\IOpenedRecords;
use Mifrial\Messages\Chat\Dto\InboxStats;
use Mifrial\Messages\Chat\Dto\MessageAudience;
use Mifrial\Messages\Chat\Dto\MessagePage;
use Mifrial\Messages\Chat\Dto\MessageRecord;
use Mifrial\Messages\Chat\Exception\ChatDuplicateException;
use Mifrial\Messages\Chat\Exception\ChatInvalidException;
use Mifrial\Messages\Chat\Exception\ChatNotFoundException;
use Mifrial\Messages\Chat\Table\ChatMemberTable;

/**
 * Сообщения чата: страница, max id, inbox stats, без публичного порта.
 */
final class ChatMessageRepository
{
    /**
     * Создаёт репозиторий.
     *
     * @param IOpenedRecords $messageRecords Строки `chat_message`.
     *
     * @return void
     */
    public function __construct(
        private readonly IOpenedRecords $messageRecords,
    ) {
    }

    /**
     * Пишет сообщение с одним now на created_at и updated_at.
     *
     * @param int $chatId Чат.
     * @param int $userId Автор.
     * @param string $content Текст.
     * @param array<int, array{type: string, payload: mixed}> $attachments Вложения.
     * @param DateTime $sentAt Момент send.
     * @param MessageAudience|null $audience Аудитория; null — всем.
     *
     * @return int Id сообщения.
     *
     * @throws ChatInvalidException Если значения недопустимы.
     */
    public function add(
        int $chatId,
        int $userId,
        string $content,
        array $attachments,
        DateTime $sentAt,
        ?MessageAudience $audience = null,
    ): int {
        $resolvedAudience = $audience ?? MessageAudience::all();

        return $this->write(function () use (
            $chatId,
            $userId,
            $content,
            $attachments,
            $sentAt,
            $resolvedAudience,
        ): int {
            return $this->messageRecords->add([
                'chat_id' => $chatId,
                'user_id' => $userId,
                'content' => $content,
                'attachments' => $attachments,
                'audience' => $resolvedAudience->getKind(),
                'audience_user_ids' => $resolvedAudience->getUserIds(),
                'created_at' => $sentAt,
                'updated_at' => $sentAt,
            ]);
        });
    }

    /**
     * Сообщение по id.
     *
     * @param int $messageId Идентификатор.
     *
     * @return MessageRecord Сообщение.
     *
     * @throws ChatNotFoundException Если строки нет.
     */
    public function getById(int $messageId): MessageRecord
    {
        $row = $this->messageRecords->getById($messageId);
        if ($row === null) {
            throw new ChatNotFoundException();
        }

        return MessageRecord::fromNormalized($row);
    }

    /**
     * Сообщения по id; пустой список без запроса.
     *
     * @param array<int, int> $messageIds Идентификаторы.
     *
     * @return array<int, MessageRecord> Найденные.
     *
     * @throws ChatInvalidException Если фильтр недопустим.
     */
    public function getByIds(array $messageIds): array
    {
        if ($messageIds === []) {
            return [];
        }

        try {
            $records = [];
            foreach (
                $this->messageRecords->getList(ListQuery::fromOptions([
                    'filter' => ['id' => $messageIds],
                    'limit' => 500,
                ]))->rows() as $row
            ) {
                $records[] = MessageRecord::fromNormalized($row);
            }
        } catch (MapInvalidException $exception) {
            throw new ChatInvalidException('Message ids filter is invalid', $exception);
        }

        return $records;
    }

    /**
     * Страница видимых сообщений чата: created_at desc, id desc.
     *
     * @param int $chatId Чат.
     * @param int $viewerId Зритель.
     * @param int $limit Размер 1…500.
     * @param int $offset Сдвиг ≥ 0.
     *
     * @return MessagePage Страница.
     *
     * @throws ChatInvalidException Если страница недопустима.
     */
    public function findPage(int $chatId, int $viewerId, int $limit, int $offset): MessagePage
    {
        $this->assertPageBounds($limit, $offset);
        try {
            $listResult = $this->messageRecords->getList(ListQuery::fromOptions([
                'filter' => $this->messageFilters()->withVisibility([['chat_id' => $chatId]], $viewerId),
                'limit' => $limit,
                'offset' => $offset,
                'countTotal' => true,
                'sort' => [
                    'created_at' => 'desc',
                    'id' => 'desc',
                ],
            ]));
        } catch (MapInvalidException $exception) {
            throw new ChatInvalidException('Message page is invalid', $exception);
        }

        $items = [];
        foreach ($listResult->rows() as $row) {
            $items[] = MessageRecord::fromNormalized($row);
        }

        return new MessagePage($items, $listResult->total() ?? 0);
    }

    /**
     * Максимальный id видимых сообщений чата.
     *
     * @param int $chatId Чат.
     * @param int $viewerId Зритель.
     *
     * @return int|null Id или null, если пусто.
     */
    public function findMaxId(int $chatId, int $viewerId): ?int
    {
        $rows = $this->messageRecords->getList(ListQuery::fromOptions([
            'filter' => $this->messageFilters()->withVisibility([['chat_id' => $chatId]], $viewerId),
            'limit' => 1,
            'sort' => ['id' => 'desc'],
            'select' => ['id'],
        ]))->rows();
        if ($rows === []) {
            return null;
        }

        return (int) $rows[0]['id'];
    }

    /**
     * Сообщения после курсора до горизонта, видимые актору.
     *
     * @param array<int, int> $chatIds Чаты; пустой список без запроса.
     * @param int $viewerId Зритель.
     * @param int $sinceUnix Нижняя unix-секунда курсора.
     * @param int $afterId Id в секунде since; 0 — вся секунда (не id>0 в ST).
     * @param DateTime $horizon Верх `updated_at`.
     * @param int $limit Размер 1…500.
     *
     * @return array<int, MessageRecord> Порядок updated_at ASC, id ASC.
     *
     * @throws ChatInvalidException Если limit или фильтр недопустимы.
     */
    public function getUpdatedSince(
        array $chatIds,
        int $viewerId,
        int $sinceUnix,
        int $afterId,
        DateTime $horizon,
        int $limit,
    ): array {
        if ($chatIds === []) {
            return [];
        }

        $this->assertPageBounds($limit, 0);
        $records = [];
        foreach ($this->listSince($chatIds, $viewerId, $sinceUnix, $afterId, $horizon, $limit) as $row) {
            $records[] = MessageRecord::fromNormalized($row);
        }

        return $records;
    }

    /**
     * Unread и max видимого id по чатам; пустой список без ST.
     *
     * @param array<int, int> $chatIds Чаты inbox.
     * @param int $viewerId Зритель.
     *
     * @return InboxStats Счётчики.
     *
     * @throws ChatInvalidException Если агрегат недопустим.
     */
    public function getInboxStats(array $chatIds, int $viewerId): InboxStats
    {
        if ($chatIds === []) {
            return new InboxStats([], []);
        }

        try {
            return new InboxStats(
                $this->intMapByChatId($this->aggregateUnread($chatIds, $viewerId), 'unread'),
                $this->intMapByChatId($this->aggregateLastId($chatIds, $viewerId), 'last_id'),
            );
        } catch (MapInvalidException $exception) {
            throw new ChatInvalidException('Inbox aggregate is invalid', $exception);
        }
    }

    /**
     * Ставит аудиторию и updated_at сообщения.
     *
     * @param int $messageId Сообщение.
     * @param MessageAudience $audience Аудитория.
     * @param DateTime $updatedAt Момент.
     *
     * @return void
     *
     * @throws ChatNotFoundException Если строки нет.
     * @throws ChatInvalidException Если значения недопустимы.
     */
    public function updateAudience(int $messageId, MessageAudience $audience, DateTime $updatedAt): void
    {
        $this->write(function () use ($messageId, $audience, $updatedAt): mixed {
            $this->messageRecords->update($messageId, [
                'audience' => $audience->getKind(),
                'audience_user_ids' => $audience->getUserIds(),
                'updated_at' => $updatedAt,
            ]);

            return null;
        });
    }

    /**
     * Строки сообщений по keyset-фильтру.
     *
     * @param array<int, int> $chatIds Чаты.
     * @param int $viewerId Зритель.
     * @param int $sinceUnix Нижняя unix-секунда.
     * @param int $afterId Курсор id.
     * @param DateTime $horizon Верх.
     * @param int $limit Размер.
     *
     * @return array<int, array<string, mixed>> Строки ST.
     *
     * @throws ChatInvalidException Если фильтр недопустим.
     */
    private function listSince(
        array $chatIds,
        int $viewerId,
        int $sinceUnix,
        int $afterId,
        DateTime $horizon,
        int $limit,
    ): array {
        try {
            $filters = $this->messageFilters();
            $window = $filters->sinceFilter($chatIds, $sinceUnix, $afterId, $horizon);
            $window[] = $filters->visibilityFilter($viewerId);

            return $this->messageRecords->getList(ListQuery::fromOptions([
                'filter' => $window,
                'limit' => $limit,
                'sort' => [
                    'updated_at' => 'asc',
                    'id' => 'asc',
                ],
            ]))->rows();
        } catch (MapInvalidException $exception) {
            throw new ChatInvalidException('Message since filter is invalid', $exception);
        } catch (FieldInvalidException $exception) {
            throw new ChatInvalidException('Message since filter is invalid', $exception);
        }
    }

    /**
     * COUNT видимых с id > last_read.
     *
     * @param array<int, int> $chatIds Чаты.
     * @param int $viewerId Зритель.
     *
     * @return array<int, array<string, mixed>> Ряды.
     *
     * @throws MapInvalidException Если агрегат недопустим.
     */
    private function aggregateUnread(array $chatIds, int $viewerId): array
    {
        return $this->messageRecords->aggregate(AggregateQuery::fromOptions([
            'filter' => $this->messageFilters()->withVisibility(
                [
                    ['chat_id' => $chatIds],
                    [
                        '>id' => new SubqueryValue(
                            ChatMemberTable::class,
                            'last_read_message_id',
                            filter: [
                                'chat_id' => new OuterColumn('chat_id'),
                                'user_id' => $viewerId,
                            ],
                            coalesce: 0,
                        ),
                    ],
                ],
                $viewerId,
            ),
            'group' => ['chat_id'],
            'select' => ['chat_id', new CountField('unread')],
            'limit' => 500,
        ]))->rows();
    }

    /**
     * MAX(id) видимых по чату.
     *
     * @param array<int, int> $chatIds Чаты.
     * @param int $viewerId Зритель.
     *
     * @return array<int, array<string, mixed>> Ряды.
     *
     * @throws MapInvalidException Если агрегат недопустим.
     */
    private function aggregateLastId(array $chatIds, int $viewerId): array
    {
        return $this->messageRecords->aggregate(AggregateQuery::fromOptions([
            'filter' => $this->messageFilters()->withVisibility([['chat_id' => $chatIds]], $viewerId),
            'group' => ['chat_id'],
            'select' => ['chat_id', new MaxField('id', 'last_id')],
            'limit' => 500,
        ]))->rows();
    }

    /**
     * chat_id → int мера.
     *
     * @param array<int, array<string, mixed>> $rows Ряды агрегата.
     * @param string $measureKey Alias меры.
     *
     * @return array<int, int> Карта.
     */
    private function intMapByChatId(array $rows, string $measureKey): array
    {
        $mapped = [];
        foreach ($rows as $row) {
            $mapped[(int) $row['chat_id']] = (int) $row[$measureKey];
        }

        return $mapped;
    }

    /**
     * Границы страницы.
     *
     * @param int $limit Размер.
     * @param int $offset Сдвиг.
     *
     * @return void
     *
     * @throws ChatInvalidException Если вне диапазона.
     */
    private function assertPageBounds(int $limit, int $offset): void
    {
        if ($limit < 1 || $limit > 500 || $offset < 0) {
            throw new ChatInvalidException('Message page bounds are invalid');
        }
    }

    /**
     * Сборщик фильтра видимости.
     *
     * @return ChatMessageVisibilityFilter Фильтр.
     */
    private function messageFilters(): ChatMessageVisibilityFilter
    {
        return new ChatMessageVisibilityFilter();
    }

    /**
     * Выполняет запись и мапит ошибки строки в Chat.
     *
     * @param Closure $work Запись.
     *
     * @return mixed Результат $work.
     *
     * @throws ChatDuplicateException Если unique.
     * @throws ChatInvalidException Если поле/карта.
     * @throws ChatNotFoundException Если строки нет.
     */
    private function write(Closure $work): mixed
    {
        try {
            return $work();
        } catch (UniqueConstraintException $exception) {
            throw new ChatDuplicateException($exception);
        } catch (RowNotFoundException $exception) {
            throw new ChatNotFoundException($exception);
        } catch (FieldRequiredException $exception) {
            throw new ChatInvalidException('Message field is required', $exception);
        } catch (FieldInvalidException $exception) {
            throw new ChatInvalidException('Message field is invalid', $exception);
        } catch (MapInvalidException $exception) {
            throw new ChatInvalidException('Message map is invalid', $exception);
        }
    }
}
