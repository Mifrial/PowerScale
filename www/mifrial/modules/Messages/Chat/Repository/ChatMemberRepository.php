<?php

declare(strict_types=1);

namespace Mifrial\Messages\Chat\Repository;

use Closure;
use Mifrial\Core\SmartTable\Dto\ListQuery;
use Mifrial\Core\SmartTable\Exception\Field\FieldInvalidException;
use Mifrial\Core\SmartTable\Exception\Field\FieldRequiredException;
use Mifrial\Core\SmartTable\Exception\Map\MapInvalidException;
use Mifrial\Core\SmartTable\Exception\Row\RowNotFoundException;
use Mifrial\Core\SmartTable\Exception\Row\UniqueConstraintException;
use Mifrial\Core\SmartTable\Interface\Service\IOpenedRecords;
use Mifrial\Messages\Chat\Dto\ChatMemberRecord;
use Mifrial\Messages\Chat\Exception\ChatDuplicateException;
use Mifrial\Messages\Chat\Exception\ChatInvalidException;
use Mifrial\Messages\Chat\Exception\ChatNotFoundException;

/**
 * Членство: пара чат+юзер и списки id, без публичного порта.
 */
final class ChatMemberRepository
{
    /**
     * Создаёт репозиторий.
     *
     * @param IOpenedRecords $memberRecords Строки `chat_member`.
     *
     * @return void
     */
    public function __construct(
        private readonly IOpenedRecords $memberRecords,
    ) {
    }

    /**
     * Добавляет членство.
     *
     * @param int $chatId Чат.
     * @param int $userId Учётка.
     *
     * @return int Id строки членства.
     *
     * @throws ChatDuplicateException Если пара уже есть.
     * @throws ChatInvalidException Если значения недопустимы.
     */
    public function add(int $chatId, int $userId): int
    {
        return $this->write(function () use ($chatId, $userId): int {
            return $this->memberRecords->add([
                'chat_id' => $chatId,
                'user_id' => $userId,
            ]);
        });
    }

    /**
     * Удаляет строку членства по id.
     *
     * @param int $memberId Идентификатор строки.
     *
     * @return void
     *
     * @throws ChatNotFoundException Если строки нет.
     */
    public function deleteById(int $memberId): void
    {
        $this->write(function () use ($memberId): mixed {
            $this->memberRecords->delete($memberId);

            return null;
        });
    }

    /**
     * Ищет id строки членства.
     *
     * @param int $chatId Чат.
     * @param int $userId Учётка.
     *
     * @return int|null Id или null.
     */
    public function findId(int $chatId, int $userId): ?int
    {
        $row = $this->memberRecords->getUnique(ListQuery::fromOptions([
            'filter' => [
                'chat_id' => $chatId,
                'user_id' => $userId,
            ],
            'limit' => 1,
            'select' => ['id'],
        ]));
        if ($row === null) {
            return null;
        }

        return (int) $row['id'];
    }

    /**
     * Id пользователей в чате (до 500).
     *
     * @param int $chatId Чат.
     *
     * @return array<int, int> user id.
     */
    public function getMemberIds(int $chatId): array
    {
        return $this->intColumn($this->pageByField('chat_id', $chatId, 'user_id'), 'user_id');
    }

    /**
     * Id чатов пользователя (до 500).
     *
     * @param int $userId Учётка.
     *
     * @return array<int, int> chat id.
     */
    public function getChatIdsOfUser(int $userId): array
    {
        return $this->intColumn($this->pageByField('user_id', $userId, 'chat_id'), 'chat_id');
    }

    /**
     * Членства учётки (до 500).
     *
     * @param int $userId Учётка.
     *
     * @return array<int, ChatMemberRecord> Строки.
     */
    public function getByUserId(int $userId): array
    {
        return $this->recordsFromRows($this->memberRecords->getList(ListQuery::fromOptions([
            'filter' => ['user_id' => $userId],
            'limit' => 500,
        ]))->rows());
    }

    /**
     * Члены чатов пачкой; пустой список без запроса.
     *
     * @param array<int, int> $chatIds Чаты.
     *
     * @return array<int, ChatMemberRecord> Строки.
     */
    public function getByChatIds(array $chatIds): array
    {
        if ($chatIds === []) {
            return [];
        }

        $records = [];
        $offset = 0;
        do {
            $pageRows = $this->memberRecords->getList(ListQuery::fromOptions([
                'filter' => ['chat_id' => $chatIds],
                'limit' => 500,
                'offset' => $offset,
            ]))->rows();
            foreach ($this->recordsFromRows($pageRows) as $record) {
                $records[] = $record;
            }

            $offset += 500;
        } while (count($pageRows) === 500);

        return $records;
    }

    /**
     * Число членств чата.
     *
     * @param int $chatId Чат.
     *
     * @return int COUNT.
     */
    public function countMembers(int $chatId): int
    {
        $listResult = $this->memberRecords->getList(ListQuery::fromOptions([
            'filter' => ['chat_id' => $chatId],
            'limit' => 1,
            'countTotal' => true,
            'select' => ['id'],
        ]));

        return $listResult->total() ?? 0;
    }

    /**
     * Ставит last_read_message_id членства.
     *
     * @param int $chatId Чат.
     * @param int $userId Учётка.
     * @param int|null $messageId Max id или null.
     *
     * @return void
     *
     * @throws ChatNotFoundException Если членства нет.
     * @throws ChatInvalidException Если значение недопустимо.
     */
    public function updateLastRead(int $chatId, int $userId, ?int $messageId): void
    {
        $memberId = $this->findId($chatId, $userId);
        if ($memberId === null) {
            throw new ChatNotFoundException();
        }

        $this->write(function () use ($memberId, $messageId): mixed {
            $this->memberRecords->update($memberId, ['last_read_message_id' => $messageId]);

            return null;
        });
    }

    /**
     * Страница членств по одному полю.
     *
     * @param string $filterName Поле фильтра.
     * @param int $filterValue Значение.
     * @param string $selectName Колонка в select.
     *
     * @return array<int, array<string, mixed>> Строки.
     */
    private function pageByField(string $filterName, int $filterValue, string $selectName): array
    {
        return $this->memberRecords->getList(ListQuery::fromOptions([
            'filter' => [$filterName => $filterValue],
            'limit' => 500,
            'select' => [$selectName],
        ]))->rows();
    }

    /**
     * Собирает int-колонку.
     *
     * @param array<int, array<string, mixed>> $rows Строки.
     * @param string $columnName Имя.
     *
     * @return array<int, int> Значения.
     */
    private function intColumn(array $rows, string $columnName): array
    {
        $values = [];
        foreach ($rows as $row) {
            $values[] = (int) $row[$columnName];
        }

        return $values;
    }

    /**
     * Record из строк членства.
     *
     * @param array<int, array<string, mixed>> $rows Строки.
     *
     * @return array<int, ChatMemberRecord> Членства.
     */
    private function recordsFromRows(array $rows): array
    {
        $records = [];
        foreach ($rows as $row) {
            $records[] = ChatMemberRecord::fromNormalized($row);
        }

        return $records;
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
            throw new ChatInvalidException('Member field is required', $exception);
        } catch (FieldInvalidException $exception) {
            throw new ChatInvalidException('Member field is invalid', $exception);
        } catch (MapInvalidException $exception) {
            throw new ChatInvalidException('Member map is invalid', $exception);
        }
    }
}
