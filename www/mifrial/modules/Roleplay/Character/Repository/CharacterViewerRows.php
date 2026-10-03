<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Repository;

use Mifrial\Core\SmartTable\Dto\ListQuery;
use Mifrial\Core\SmartTable\Exception\Field\FieldInvalidException;
use Mifrial\Core\SmartTable\Exception\Field\FieldRequiredException;
use Mifrial\Core\SmartTable\Exception\Map\MapInvalidException;
use Mifrial\Core\SmartTable\Exception\Row\ReferenceConstraintException;
use Mifrial\Core\SmartTable\Exception\Row\RowNotFoundException;
use Mifrial\Core\SmartTable\Exception\Row\RowWriteFailedException;
use Mifrial\Core\SmartTable\Exception\Row\UniqueConstraintException;
use Mifrial\Core\SmartTable\Interface\Service\IOpenedRecords;
use Mifrial\Roleplay\Character\Dto\CharacterViewerRecord;
use Mifrial\Roleplay\Character\Exception\CharacterInvalidException;
use Mifrial\Roleplay\Character\Exception\CharacterNotFoundException;

/**
 * Строки character_viewer. Право не проверяет.
 */
final class CharacterViewerRows
{
    /**
     * Создаёт доступ к строкам зрителей.
     *
     * @param IOpenedRecords $viewerRecords Открытая таблица.
     *
     * @return void
     */
    public function __construct(
        private readonly IOpenedRecords $viewerRecords,
    ) {
    }

    /**
     * Строки по фильтру.
     *
     * @param array<string, int> $filter Условия.
     *
     * @return array<int, array<string, mixed>> Строки.
     *
     * @throws CharacterInvalidException Если выборка битая.
     */
    public function rows(array $filter): array
    {
        try {
            return $this->viewerRecords->getList(ListQuery::fromOptions([
                'filter' => $filter,
                'sort' => ['id' => 'asc'],
                'limit' => ListQuery::MAX_LIMIT,
            ]))->rows();
        } catch (FieldRequiredException | FieldInvalidException | MapInvalidException $exception) {
            throw new CharacterInvalidException('Character viewer list is invalid', $exception);
        }
    }

    /**
     * Заменяет набор зрителей персонажа.
     *
     * @param int $characterId Персонаж.
     * @param list<CharacterViewerRecord> $viewers Новые гранты.
     *
     * @return void
     *
     * @throws CharacterNotFoundException Если нет user.
     * @throws CharacterInvalidException Если поле.
     */
    public function replace(int $characterId, array $viewers): void
    {
        $existingIds = $this->existingIds($characterId);
        $nextViewers = $this->nextViewers($viewers);
        $this->deleteMissing($existingIds, $nextViewers);
        $this->writeNext($characterId, $existingIds, $nextViewers);
    }

    /**
     * Id строк по user.
     *
     * @param int $characterId Персонаж.
     *
     * @return array<int, int> userId => row id.
     *
     * @throws CharacterInvalidException Если выборка битая.
     */
    private function existingIds(int $characterId): array
    {
        $existingIds = [];
        foreach ($this->rows(['character_id' => $characterId]) as $row) {
            $userId = $row['user_id'] ?? null;
            $rowId = $row['id'] ?? null;
            if (is_int($userId) && is_int($rowId)) {
                $existingIds[$userId] = $rowId;
            }
        }

        return $existingIds;
    }

    /**
     * Гранты по user id.
     *
     * @param list<CharacterViewerRecord> $viewers Новые гранты.
     *
     * @return array<int, CharacterViewerRecord> userId => грант.
     */
    private function nextViewers(array $viewers): array
    {
        $nextViewers = [];
        foreach ($viewers as $viewer) {
            $nextViewers[$viewer->getUserId()] = $viewer;
        }

        return $nextViewers;
    }

    /**
     * Удаляет зрителей, которых нет в новом списке.
     *
     * @param array<int, int> $existingIds Текущие строки.
     * @param array<int, CharacterViewerRecord> $nextViewers Новые гранты.
     *
     * @return void
     *
     * @throws CharacterInvalidException Если удаление не удалось.
     */
    private function deleteMissing(array $existingIds, array $nextViewers): void
    {
        foreach ($existingIds as $userId => $rowId) {
            if (!isset($nextViewers[$userId])) {
                $this->delete($rowId);
            }
        }
    }

    /**
     * Пишет новых и обновляет оставшихся.
     *
     * @param int $characterId Персонаж.
     * @param array<int, int> $existingIds Текущие строки.
     * @param array<int, CharacterViewerRecord> $nextViewers Новые гранты.
     *
     * @return void
     *
     * @throws CharacterNotFoundException Если нет user.
     * @throws CharacterInvalidException Если поле.
     */
    private function writeNext(int $characterId, array $existingIds, array $nextViewers): void
    {
        foreach ($nextViewers as $userId => $viewer) {
            $this->write($characterId, $userId, $viewer->getFields(), $existingIds[$userId] ?? null);
        }
    }

    /**
     * Add или update одной строки.
     *
     * @param int $characterId Персонаж.
     * @param int $userId Зритель.
     * @param array $fields Секции.
     * @param int|null $rowId Id строки или null.
     *
     * @return void
     *
     * @throws CharacterNotFoundException Если нет user.
     * @throws CharacterInvalidException Если поле.
     */
    private function write(int $characterId, int $userId, array $fields, ?int $rowId): void
    {
        try {
            $this->store($characterId, $userId, $fields, $rowId);
        } catch (ReferenceConstraintException | RowNotFoundException $exception) {
            throw new CharacterNotFoundException('Character viewer was not found', $exception);
        } catch (
            FieldRequiredException
            | FieldInvalidException
            | MapInvalidException
            | UniqueConstraintException
            | RowWriteFailedException $exception
        ) {
            throw new CharacterInvalidException('Character viewer field is invalid', $exception);
        }
    }

    /**
     * Insert или update без разбора ошибок.
     *
     * @param int $characterId Персонаж.
     * @param int $userId Зритель.
     * @param array $fields Секции.
     * @param int|null $rowId Id строки или null.
     *
     * @return void
     */
    private function store(int $characterId, int $userId, array $fields, ?int $rowId): void
    {
        if ($rowId === null) {
            $this->viewerRecords->add([
                'character_id' => $characterId,
                'user_id' => $userId,
                'fields' => $fields,
            ]);

            return;
        }

        $this->viewerRecords->update($rowId, ['fields' => $fields]);
    }

    /**
     * Удаляет строку зрителя.
     *
     * @param int $rowId Id.
     *
     * @return void
     *
     * @throws CharacterInvalidException Если удаление не удалось.
     */
    private function delete(int $rowId): void
    {
        try {
            $this->viewerRecords->delete($rowId);
        } catch (RowNotFoundException | RowWriteFailedException $exception) {
            throw new CharacterInvalidException('Character viewer delete failed', $exception);
        }
    }
}
