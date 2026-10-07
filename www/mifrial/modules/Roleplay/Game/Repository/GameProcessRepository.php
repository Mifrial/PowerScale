<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Repository;

use Mifrial\Core\SmartTable\Dto\FilterCondition;
use Mifrial\Core\SmartTable\Dto\FilterGroup;
use Mifrial\Core\SmartTable\Dto\ListQuery;
use Mifrial\Core\SmartTable\Exception\Field\FieldInvalidException;
use Mifrial\Core\SmartTable\Exception\Field\FieldRequiredException;
use Mifrial\Core\SmartTable\Exception\Map\MapInvalidException;
use Mifrial\Core\SmartTable\Exception\Row\RowNotFoundException;
use Mifrial\Core\SmartTable\Exception\Row\RowWriteFailedException;
use Mifrial\Core\SmartTable\Interface\Service\IOpenedRecords;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Roleplay\Game\Dto\GameProcessRecord;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Game\Table\GameProcessTable;

/**
 * Строки process. Лист не пишет.
 */
final class GameProcessRepository
{
    private readonly IOpenedRecords $processRecords;

    /**
     * Создаёт репозиторий.
     *
     * @param ISmartTableGateway $smartTableGateway Шлюз ST.
     *
     * @return void
     */
    public function __construct(ISmartTableGateway $smartTableGateway)
    {
        $this->processRecords = $smartTableGateway->open(GameProcessTable::class)->records();
    }

    /**
     * Вставляет open.
     *
     * @param int $sessionId Сессия.
     * @param int|null $battleId Бой или null.
     * @param string $participantType Тип character или npc.
     * @param int $participantId Участник.
     *
     * @return GameProcessRecord Строка.
     *
     * @throws GameInvalidException Если поле.
     * @throws GameNotFoundException Если строки нет после вставки.
     */
    public function add(int $sessionId, ?int $battleId, string $participantType, int $participantId): GameProcessRecord
    {
        $fields = [
            'session_id' => $sessionId,
            'participant_type' => $participantType,
            'participant_id' => $participantId,
            'status' => 'open',
        ];
        if ($battleId !== null) {
            $fields['battle_id'] = $battleId;
        }

        try {
            $processId = $this->processRecords->add($fields);
        } catch (
            FieldRequiredException
            | FieldInvalidException
            | MapInvalidException
            | RowWriteFailedException $exception
        ) {
            throw new GameInvalidException('Game process field is invalid', $exception);
        }

        return $this->getById($processId);
    }

    /**
     * Строка по id.
     *
     * @param int $processId Process.
     *
     * @return GameProcessRecord Строка.
     *
     * @throws GameNotFoundException Если нет.
     * @throws GameInvalidException Если строка битая.
     */
    public function getById(int $processId): GameProcessRecord
    {
        $row = $this->processRecords->getById($processId);
        if ($row === null) {
            throw new GameNotFoundException('Game process was not found');
        }

        return GameProcessRecord::fromNormalized($row);
    }

    /**
     * Ставит resolved.
     *
     * @param int $processId Process.
     *
     * @return void
     *
     * @throws GameNotFoundException Если строки нет.
     * @throws GameInvalidException Если поле.
     */
    public function markResolved(int $processId): void
    {
        $this->update($processId, ['status' => 'resolved']);
    }

    /**
     * Ставит cancelled.
     *
     * @param int $processId Process.
     *
     * @return void
     *
     * @throws GameNotFoundException Если строки нет.
     * @throws GameInvalidException Если поле.
     */
    public function markCancelled(int $processId): void
    {
        $this->update($processId, ['status' => 'cancelled']);
    }

    /**
     * Гасит open персонажа одной сессии.
     *
     * @param int|null $sessionId Сессия или null.
     * @param int $characterId Персонаж.
     *
     * @return void
     *
     * @throws GameNotFoundException Если строку сняли.
     * @throws GameInvalidException Если поле.
     */
    public function cancelOpenForCharacter(?int $sessionId, int $characterId): void
    {
        if ($sessionId === null) {
            return;
        }

        $this->cancelOpen([
            new FilterCondition('session_id', '=', $sessionId),
            new FilterCondition('participant_type', '=', 'character'),
            new FilterCondition('participant_id', '=', $characterId),
        ]);
    }

    /**
     * Гасит open одного боя.
     *
     * @param int $battleId Бой.
     *
     * @return void
     *
     * @throws GameNotFoundException Если строку сняли.
     * @throws GameInvalidException Если поле.
     */
    public function cancelOpenForBattle(int $battleId): void
    {
        $this->cancelOpen([
            new FilterCondition('battle_id', '=', $battleId),
        ]);
    }

    /**
     * Гасит оставшиеся open сессии.
     *
     * @param int $sessionId Сессия.
     *
     * @return void
     *
     * @throws GameNotFoundException Если строку сняли.
     * @throws GameInvalidException Если поле.
     */
    public function cancelOpenForSession(int $sessionId): void
    {
        $this->cancelOpen([
            new FilterCondition('session_id', '=', $sessionId),
        ]);
    }

    /**
     * Переводит найденные open в cancelled.
     *
     * @param list<FilterCondition> $conditions Условия сверх статуса.
     *
     * @return void
     *
     * @throws GameNotFoundException Если строку сняли.
     * @throws GameInvalidException Если поле.
     */
    private function cancelOpen(array $conditions): void
    {
        $filter = new FilterGroup('AND', [
            new FilterCondition('status', '=', 'open'),
            ...$conditions,
        ]);
        foreach ($this->ids($filter) as $processId) {
            $this->update($processId, ['status' => 'cancelled']);
        }
    }

    /**
     * Id выборки.
     *
     * @param FilterGroup $filter Фильтр.
     *
     * @return list<int> Id.
     *
     * @throws GameInvalidException Если строка битая.
     */
    private function ids(FilterGroup $filter): array
    {
        $result = $this->processRecords->getList(new ListQuery(
            $filter,
            ['id' => 'ASC'],
            ListQuery::MAX_LIMIT,
            0,
            false,
            null,
        ));
        $ids = [];
        foreach ($result->rows() as $row) {
            $id = $row['id'] ?? null;
            if (!is_int($id)) {
                throw new GameInvalidException('Game process row is invalid');
            }

            $ids[] = $id;
        }

        return $ids;
    }

    /**
     * Пишет поля.
     *
     * @param int $processId Id.
     * @param array<string, mixed> $fields Колонки.
     *
     * @return void
     *
     * @throws GameNotFoundException Если строки нет.
     * @throws GameInvalidException Если поле.
     */
    private function update(int $processId, array $fields): void
    {
        try {
            $this->processRecords->update($processId, $fields);
        } catch (RowNotFoundException $exception) {
            throw new GameNotFoundException('Game process was not found', $exception);
        } catch (
            FieldRequiredException
            | FieldInvalidException
            | MapInvalidException
            | RowWriteFailedException $exception
        ) {
            throw new GameInvalidException('Game process field is invalid', $exception);
        }
    }
}
