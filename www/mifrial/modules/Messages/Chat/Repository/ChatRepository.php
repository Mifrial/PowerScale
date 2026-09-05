<?php

declare(strict_types=1);

namespace Mifrial\Messages\Chat\Repository;

use Closure;
use Mifrial\Core\Kernel\Value\DateTime;
use Mifrial\Core\SmartTable\Dto\ListQuery;
use Mifrial\Core\SmartTable\Exception\Field\FieldInvalidException;
use Mifrial\Core\SmartTable\Exception\Field\FieldRequiredException;
use Mifrial\Core\SmartTable\Exception\Map\MapInvalidException;
use Mifrial\Core\SmartTable\Exception\Row\RowNotFoundException;
use Mifrial\Core\SmartTable\Exception\Row\UniqueConstraintException;
use Mifrial\Core\SmartTable\Interface\Service\IOpenedRecords;
use Mifrial\Messages\Chat\Dto\ChatRecord;
use Mifrial\Messages\Chat\Exception\ChatDuplicateException;
use Mifrial\Messages\Chat\Exception\ChatInvalidException;
use Mifrial\Messages\Chat\Exception\ChatNotFoundException;

/**
 * Коллекция чатов: pair_key внутри, снаружи ChatRecord.
 */
final class ChatRepository
{
    /**
     * Создаёт репозиторий.
     *
     * @param IOpenedRecords $chatRecords Строки `chat`.
     *
     * @return void
     */
    public function __construct(
        private readonly IOpenedRecords $chatRecords,
    ) {
    }

    /**
     * Находит или создаёт private-чат пары.
     *
     * @param int $firstUserId Первая учётка.
     * @param int $secondUserId Вторая учётка.
     *
     * @return int Id чата.
     *
     * @throws ChatInvalidException Если значения недопустимы.
     */
    public function addOrFindPrivate(int $firstUserId, int $secondUserId): int
    {
        $pairKey = $this->pairKey($firstUserId, $secondUserId);
        $existingId = $this->findIdByPairKey($pairKey);
        if ($existingId !== null) {
            return $existingId;
        }

        try {
            return $this->chatRecords->add([
                'type' => 'private',
                'name' => '',
                'pair_key' => $pairKey,
            ]);
        } catch (UniqueConstraintException) {
            return $this->requireIdByPairKey($pairKey);
        } catch (FieldRequiredException | FieldInvalidException | MapInvalidException $exception) {
            throw new ChatInvalidException('Chat field is invalid', $exception);
        }
    }

    /**
     * Создаёт групповой чат.
     *
     * @param string $name Имя.
     *
     * @return int Id чата.
     *
     * @throws ChatInvalidException Если значения недопустимы.
     */
    public function addGroup(string $name): int
    {
        return $this->write(function () use ($name): int {
            return $this->chatRecords->add([
                'type' => 'group',
                'name' => $name,
            ]);
        });
    }

    /**
     * Возвращает чат по id.
     *
     * @param int $chatId Идентификатор.
     *
     * @return ChatRecord Чат.
     *
     * @throws ChatNotFoundException Если строки нет.
     */
    public function getById(int $chatId): ChatRecord
    {
        $row = $this->chatRecords->getById($chatId);
        if ($row === null) {
            throw new ChatNotFoundException();
        }

        return ChatRecord::fromNormalized($row);
    }

    /**
     * Чаты по id: updated_at desc, id desc; пустой список без запроса.
     *
     * @param array<int, int> $chatIds Идентификаторы.
     *
     * @return array<int, ChatRecord> Найденные.
     */
    public function getByIds(array $chatIds): array
    {
        if ($chatIds === []) {
            return [];
        }

        $records = [];
        foreach (
            $this->chatRecords->getList(ListQuery::fromOptions([
                'filter' => ['id' => $chatIds],
                'limit' => 500,
                'sort' => [
                    'updated_at' => 'desc',
                    'id' => 'desc',
                ],
            ]))->rows() as $row
        ) {
            $records[] = ChatRecord::fromNormalized($row);
        }

        return $records;
    }

    /**
     * Ставит updated_at чата.
     *
     * @param int $chatId Чат.
     * @param DateTime $updatedAt Момент.
     *
     * @return void
     *
     * @throws ChatNotFoundException Если строки нет.
     * @throws ChatInvalidException Если значение недопустимо.
     */
    public function updateUpdatedAt(int $chatId, DateTime $updatedAt): void
    {
        $this->write(function () use ($chatId, $updatedAt): mixed {
            $this->chatRecords->update($chatId, ['updated_at' => $updatedAt]);

            return null;
        });
    }

    /**
     * Собирает pair_key меньший id первым.
     *
     * @param int $firstUserId Первая учётка.
     * @param int $secondUserId Вторая учётка.
     *
     * @return string Ключ.
     */
    private function pairKey(int $firstUserId, int $secondUserId): string
    {
        $minimumId = min($firstUserId, $secondUserId);
        $maximumId = max($firstUserId, $secondUserId);

        return $minimumId . ':' . $maximumId;
    }

    /**
     * Ищет id по pair_key.
     *
     * @param string $pairKey Ключ пары.
     *
     * @return int|null Id или null.
     */
    private function findIdByPairKey(string $pairKey): ?int
    {
        $row = $this->chatRecords->getUnique(ListQuery::fromOptions([
            'filter' => ['pair_key' => $pairKey],
            'limit' => 1,
            'select' => ['id'],
        ]));
        if ($row === null) {
            return null;
        }

        return (int) $row['id'];
    }

    /**
     * Id после гонки unique; строки уже нет — дубль без чтения.
     *
     * @param string $pairKey Ключ пары.
     *
     * @return int Id.
     *
     * @throws ChatDuplicateException Если после 1062 строки нет.
     */
    private function requireIdByPairKey(string $pairKey): int
    {
        $chatId = $this->findIdByPairKey($pairKey);
        if ($chatId === null) {
            throw new ChatDuplicateException();
        }

        return $chatId;
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
            throw new ChatInvalidException('Chat field is required', $exception);
        } catch (FieldInvalidException $exception) {
            throw new ChatInvalidException('Chat field is invalid', $exception);
        } catch (MapInvalidException $exception) {
            throw new ChatInvalidException('Chat map is invalid', $exception);
        }
    }
}
