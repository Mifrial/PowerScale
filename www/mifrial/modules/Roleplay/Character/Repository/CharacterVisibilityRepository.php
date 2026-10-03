<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Repository;

use Mifrial\Core\Kernel\Value\DateTime;
use Mifrial\Core\SmartTable\Dto\ListQuery;
use Mifrial\Core\SmartTable\Exception\Field\FieldInvalidException;
use Mifrial\Core\SmartTable\Exception\Field\FieldRequiredException;
use Mifrial\Core\SmartTable\Exception\Map\MapInvalidException;
use Mifrial\Core\SmartTable\Exception\Row\RowNotFoundException;
use Mifrial\Core\SmartTable\Exception\Row\RowWriteFailedException;
use Mifrial\Core\SmartTable\Exception\Row\UniqueConstraintException;
use Mifrial\Core\SmartTable\Interface\Service\IOpenedRecords;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Roleplay\Character\Dto\CharacterRecord;
use Mifrial\Roleplay\Character\Dto\CharacterViewerRecord;
use Mifrial\Roleplay\Character\Exception\CharacterInvalidException;
use Mifrial\Roleplay\Character\Exception\CharacterNotFoundException;
use Mifrial\Roleplay\Character\Table\CharacterTable;
use Mifrial\Roleplay\Character\Table\CharacterViewerTable;

/**
 * Строки видимости и заметок. Права не проверяет.
 */
final class CharacterVisibilityRepository
{
    private readonly ISmartTableGateway $gateway;

    private readonly IOpenedRecords $characterRecords;

    private readonly CharacterViewerRows $viewerRows;

    /**
     * Открывает character и character_viewer.
     *
     * @param ISmartTableGateway $smartTableGateway Шлюз ST.
     *
     * @return void
     */
    public function __construct(
        ISmartTableGateway $smartTableGateway,
    ) {
        $this->characterRecords = $smartTableGateway->open(CharacterTable::class)->records();
        $this->viewerRows = new CharacterViewerRows($smartTableGateway->open(CharacterViewerTable::class)->records());
        $this->gateway = $smartTableGateway;
    }

    /**
     * Строка по id.
     *
     * @param int $characterId Идентификатор.
     *
     * @return CharacterRecord Персонаж.
     *
     * @throws CharacterNotFoundException Если строки нет.
     * @throws CharacterInvalidException Если Record неполный.
     */
    public function getById(int $characterId): CharacterRecord
    {
        $row = $this->characterRecords->getById($characterId);
        if ($row === null) {
            throw new CharacterNotFoundException();
        }

        return CharacterRecord::fromNormalized($row);
    }

    /**
     * Лист видимых строк. null — только владелец.
     *
     * @param int $ownerUserId Владелец.
     * @param list<int>|null $sharedCharacterIds Id зрителя или null.
     *
     * @return list<CharacterRecord> Строки.
     *
     * @throws CharacterInvalidException Если выборка битая.
     */
    public function findVisible(int $ownerUserId, ?array $sharedCharacterIds): array
    {
        $rows = $this->rows(ListQuery::fromOptions([
            'filter' => $this->visibleFilter($ownerUserId, $sharedCharacterIds),
            'sort' => ['id' => 'asc'],
            'limit' => ListQuery::MAX_LIMIT,
        ]));
        $records = [];
        foreach ($rows as $row) {
            $records[] = CharacterRecord::fromNormalized($row);
        }

        return $records;
    }

    /**
     * Id персонажей, где user — зритель.
     *
     * @param int $userId Зритель.
     *
     * @return list<int> Id.
     *
     * @throws CharacterInvalidException Если выборка битая.
     */
    public function findViewerCharacterIds(int $userId): array
    {
        $characterIds = [];
        foreach ($this->viewerRows->rows(['user_id' => $userId]) as $row) {
            $characterId = $row['character_id'] ?? null;
            if (is_int($characterId)) {
                $characterIds[] = $characterId;
            }
        }

        return $characterIds;
    }

    /**
     * Секции одного зрителя или null, если строки нет.
     *
     * @param int $characterId Персонаж.
     * @param int $userId Зритель.
     *
     * @return list<string>|null Коды.
     *
     * @throws CharacterInvalidException Если fields не list.
     */
    public function findViewerFields(int $characterId, int $userId): ?array
    {
        $rows = $this->viewerRows->rows([
            'character_id' => $characterId,
            'user_id' => $userId,
        ]);
        if ($rows === []) {
            return null;
        }

        $fields = $rows[0]['fields'] ?? null;
        if (!is_array($fields) || !array_is_list($fields)) {
            throw new CharacterInvalidException('Character viewer fields are invalid');
        }

        $codes = [];
        foreach ($fields as $field) {
            if (is_string($field)) {
                $codes[] = $field;
            }
        }

        return $codes;
    }

    /**
     * Все зрители персонажа.
     *
     * @param int $characterId Персонаж.
     *
     * @return list<CharacterViewerRecord> Гранты.
     *
     * @throws CharacterInvalidException Если строка неполная.
     */
    public function findViewers(int $characterId): array
    {
        $viewers = [];
        foreach ($this->viewerRows->rows(['character_id' => $characterId]) as $row) {
            $viewers[] = CharacterViewerRecord::fromNormalized($row);
        }

        return $viewers;
    }

    /**
     * Пишет секции «всем» и заменяет строки зрителей.
     *
     * @param int $characterId Персонаж.
     * @param list<string> $fields Секции всем.
     * @param bool $isPublic Зеркало непустого списка.
     * @param list<CharacterViewerRecord> $viewers Новые гранты.
     * @param DateTime $updatedAt Момент записи.
     *
     * @return CharacterRecord После записи.
     *
     * @throws CharacterNotFoundException Если нет персонажа или user.
     * @throws CharacterInvalidException Если поле.
     */
    public function replaceVisibility(
        int $characterId,
        array $fields,
        bool $isPublic,
        array $viewers,
        DateTime $updatedAt,
    ): CharacterRecord {
        $this->gateway->transaction(function () use ($characterId, $fields, $isPublic, $viewers, $updatedAt): void {
            $this->updateCharacter($characterId, [
                'visibility_fields' => $fields,
                'is_public' => $isPublic,
                'updated_at' => $updatedAt,
            ]);
            $this->viewerRows->replace($characterId, $viewers);
        });

        return $this->getById($characterId);
    }

    /**
     * Пишет заметки владельца.
     *
     * @param int $characterId Персонаж.
     * @param string $notes Текст.
     * @param DateTime $updatedAt Момент записи.
     *
     * @return CharacterRecord После записи.
     *
     * @throws CharacterNotFoundException Если строки нет.
     * @throws CharacterInvalidException Если поле.
     */
    public function replaceOwnerNotes(int $characterId, string $notes, DateTime $updatedAt): CharacterRecord
    {
        $this->updateCharacter($characterId, [
            'owner_notes' => $notes,
            'updated_at' => $updatedAt,
        ]);

        return $this->getById($characterId);
    }

    /**
     * Фильтр list: null не добавляет чужие строки.
     *
     * @param int $ownerUserId Владелец.
     * @param list<int>|null $sharedCharacterIds Id зрителя.
     *
     * @return array<string, mixed> Filter.
     */
    private function visibleFilter(int $ownerUserId, ?array $sharedCharacterIds): array
    {
        if ($sharedCharacterIds === null) {
            return ['owner_id' => $ownerUserId];
        }

        $filter = [
            'LOGIC' => 'OR',
            ['owner_id' => $ownerUserId],
            ['is_public' => true],
        ];
        if ($sharedCharacterIds !== []) {
            $filter[] = ['id' => $sharedCharacterIds];
        }

        return $filter;
    }

    /**
     * Строки character.
     *
     * @param ListQuery $listQuery Запрос.
     *
     * @return array<int, array<string, mixed>> Строки.
     *
     * @throws CharacterInvalidException Если выборка битая.
     */
    private function rows(ListQuery $listQuery): array
    {
        try {
            return $this->characterRecords->getList($listQuery)->rows();
        } catch (FieldRequiredException | FieldInvalidException | MapInvalidException $exception) {
            throw new CharacterInvalidException('Character list is invalid', $exception);
        }
    }

    /**
     * Пишет колонки персонажа.
     *
     * @param int $characterId Идентификатор.
     * @param array<string, mixed> $fields Колонки.
     *
     * @return void
     *
     * @throws CharacterNotFoundException Если строки нет.
     * @throws CharacterInvalidException Если поле.
     */
    private function updateCharacter(int $characterId, array $fields): void
    {
        try {
            $this->characterRecords->update($characterId, $fields);
        } catch (RowNotFoundException $exception) {
            throw new CharacterNotFoundException('Character was not found', $exception);
        } catch (
            FieldRequiredException
            | FieldInvalidException
            | MapInvalidException
            | UniqueConstraintException
            | RowWriteFailedException $exception
        ) {
            throw new CharacterInvalidException('Character field is invalid', $exception);
        }
    }
}
