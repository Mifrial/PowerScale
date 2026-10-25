<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Repository;

use Mifrial\Core\SmartTable\Dto\FilterCondition;
use Mifrial\Core\SmartTable\Dto\FilterGroup;
use Mifrial\Core\SmartTable\Dto\ListQuery;
use Mifrial\Core\SmartTable\Exception\Field\FieldInvalidException;
use Mifrial\Core\SmartTable\Exception\Field\FieldRequiredException;
use Mifrial\Core\SmartTable\Exception\Map\MapInvalidException;
use Mifrial\Core\SmartTable\Exception\Row\ReferenceConstraintException;
use Mifrial\Core\SmartTable\Exception\Row\RowNotFoundException;
use Mifrial\Core\SmartTable\Exception\Row\RowWriteFailedException;
use Mifrial\Core\SmartTable\Exception\Row\UniqueConstraintException;
use Mifrial\Core\SmartTable\Interface\Service\IConditionalOpenedRecords;
use Mifrial\Core\SmartTable\Interface\Service\IOpenedRecords;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Game\Table\GameActionTable;

/**
 * Общие строки действия боя.
 */
final class GameActionRepository
{
    private readonly IOpenedRecords $records;

    private readonly IConditionalOpenedRecords $conditionalRecords;

    private readonly GameActionStrikeRepository $strikes;

    /**
     * Создаёт репозиторий.
     *
     * @param ISmartTableGateway $smartTableGateway Шлюз ST.
     *
     * @return void
     */
    public function __construct(
        private readonly ISmartTableGateway $smartTableGateway,
    ) {
        $openedTable = $smartTableGateway->open(GameActionTable::class);
        $this->records = $openedTable->records();
        $this->conditionalRecords = $openedTable->conditionalRecords();
        $this->strikes = new GameActionStrikeRepository($smartTableGateway);
    }

    /**
     * Открытое действие боя или null. null kind — любой вид.
     *
     * @param int $battleId Бой.
     * @param string|null $kind Вид действия.
     *
     * @return array<string, mixed>|null Колонки.
     *
     * @throws GameInvalidException Если строка битая.
     */
    public function findOpenByBattle(int $battleId, ?string $kind = null): ?array
    {
        foreach ($this->rowsOfBattle($battleId, $kind) as $row) {
            if (($row['open'] ?? null) === true) {
                return $row;
            }
        }

        return null;
    }

    /**
     * Любое открытое действие боя или null.
     *
     * @param int $battleId Бой.
     *
     * @return array<string, mixed>|null Колонки.
     *
     * @throws GameInvalidException Если строка битая.
     */
    public function findOpenAny(int $battleId): ?array
    {
        return $this->findOpenByBattle($battleId, null);
    }

    /**
     * Последнее действие вида или null.
     *
     * @param int $battleId Бой.
     * @param string $kind Вид действия.
     *
     * @return array<string, mixed>|null Колонки.
     *
     * @throws GameInvalidException Если строка битая.
     */
    public function findLatestByBattle(int $battleId, string $kind): ?array
    {
        $rows = $this->rowsOfBattle($battleId, $kind);
        if ($rows === []) {
            return null;
        }

        return $rows[count($rows) - 1];
    }

    /**
     * Открытое действие боя вместе с ударами или null.
     *
     * @param int $battleId Бой.
     * @param string|null $kind Вид действия.
     *
     * @return array<string, mixed>|null Колонки действия и `strikes`.
     *
     * @throws GameInvalidException Если строка битая.
     */
    public function findOpenWithStrikes(int $battleId, ?string $kind = null): ?array
    {
        $action = $this->findOpenByBattle($battleId, $kind);

        return $action === null ? null : $this->withStrikes($action);
    }

    /**
     * Последнее действие вида вместе с ударами или null.
     *
     * @param int $battleId Бой.
     * @param string $kind Вид действия.
     *
     * @return array<string, mixed>|null Колонки действия и `strikes`.
     *
     * @throws GameInvalidException Если строка битая.
     */
    public function findLatestWithStrikes(int $battleId, string $kind): ?array
    {
        $action = $this->findLatestByBattle($battleId, $kind);

        return $action === null ? null : $this->withStrikes($action);
    }

    /**
     * Удары действия.
     *
     * @param int $actionId Действие.
     *
     * @return list<array<string, mixed>> Строки.
     *
     * @throws GameInvalidException Если строка битая.
     */
    public function getStrikes(int $actionId): array
    {
        return $this->strikes->getList($actionId);
    }

    /**
     * Вставляет действие и его удары одной транзакцией.
     *
     * @param array<string, mixed> $action Колонки действия.
     * @param list<array<string, mixed>> $strikes Колонки ударов.
     *
     * @return int Id действия.
     *
     * @throws GameNotFoundException Если сессии нет.
     * @throws GameInvalidException Если поле.
     */
    public function addWithStrikes(array $action, array $strikes): int
    {
        return $this->smartTableGateway->transaction(function () use ($action, $strikes): int {
            $actionId = $this->add($action);
            foreach (array_values($strikes) as $index => $strike) {
                $strike['strike_index'] = $index;
                $this->addStrike($actionId, $strike);
            }

            return $actionId;
        });
    }

    /**
     * Вставляет удар действия.
     *
     * @param int $actionId Действие.
     * @param array<string, mixed> $fields Колонки удара.
     *
     * @return int Id удара.
     *
     * @throws GameInvalidException Если поле или позиция повторена.
     */
    public function addStrike(int $actionId, array $fields): int
    {
        $fields['action_id'] = $actionId;

        return $this->strikes->add($fields);
    }

    /**
     * Пишет ответ удара.
     *
     * @param int $strikeId Удар.
     * @param array<string, mixed> $fields Колонки.
     *
     * @return void
     *
     * @throws GameNotFoundException Если строки нет.
     * @throws GameInvalidException Если поле.
     */
    public function answerStrike(int $strikeId, array $fields): void
    {
        $this->strikes->update($strikeId, $fields);
    }

    /**
     * Пишет ответ единственного удара и закрывает действие.
     *
     * @param int $actionId Действие.
     * @param array<string, mixed> $fields Колонки ответа.
     *
     * @return void
     *
     * @throws GameNotFoundException Если строки нет.
     * @throws GameInvalidException Если поле.
     */
    public function closeWithAnswer(int $actionId, array $fields): void
    {
        $this->smartTableGateway->transaction(function () use ($actionId, $fields): void {
            $strike = $this->strikes->findFirst($actionId);
            $strikeId = $strike['id'] ?? null;
            if (!is_int($strikeId)) {
                throw new GameNotFoundException('Game action strike was not found');
            }

            $this->strikes->update($strikeId, $fields);
            $this->close($actionId);
        });
    }

    /**
     * Снимает действия вида вместе с ударами.
     *
     * @param int $sessionId Сессия.
     * @param string $kind Вид действия.
     *
     * @return list<int> Id снятых действий.
     *
     * @throws GameNotFoundException Если строку уже сняли.
     * @throws GameInvalidException Если поле.
     */
    public function deleteBySession(int $sessionId, string $kind): array
    {
        return $this->smartTableGateway->transaction(function () use ($sessionId, $kind): array {
            $ids = $this->findIdsBySession($sessionId, $kind);
            $this->strikes->deleteByActions($ids);
            foreach ($ids as $actionId) {
                $this->delete($actionId);
            }

            return $ids;
        });
    }

    /**
     * Строка действия по id без блокировки.
     *
     * @param int $actionId Действие.
     *
     * @return array<string, mixed>|null Колонки.
     */
    public function getById(int $actionId): ?array
    {
        return $this->records->getById($actionId);
    }

    /**
     * Строка действия по id с блокировкой до конца транзакции.
     *
     * @param int $actionId Действие.
     *
     * @return array<string, mixed>|null Колонки.
     */
    public function lockById(int $actionId): ?array
    {
        return $this->conditionalRecords->getCurrentById($actionId);
    }

    /**
     * Id действий сессии. null kind — любой вид.
     *
     * @param int $sessionId Сессия.
     * @param string|null $kind Вид действия.
     *
     * @return list<int> Id.
     *
     * @throws GameInvalidException Если строка битая.
     */
    public function findIdsBySession(int $sessionId, ?string $kind = null): array
    {
        $conditions = [new FilterCondition('session_id', '=', $sessionId)];
        if ($kind !== null) {
            $conditions[] = new FilterCondition('kind', '=', $kind);
        }

        $ids = [];
        foreach ($this->rows($conditions) as $row) {
            $ids[] = $this->rowId($row);
        }

        return $ids;
    }

    /**
     * Вставляет действие.
     *
     * @param array<string, mixed> $fields Колонки.
     *
     * @return int Id.
     *
     * @throws GameNotFoundException Если сессии нет.
     * @throws GameInvalidException Если поле.
     */
    public function add(array $fields): int
    {
        try {
            return $this->records->add($fields);
        } catch (ReferenceConstraintException $exception) {
            throw new GameNotFoundException('Game session was not found', $exception);
        } catch (
            FieldRequiredException
            | FieldInvalidException
            | MapInvalidException
            | RowWriteFailedException
            | UniqueConstraintException $exception
        ) {
            throw new GameInvalidException('Game action field is invalid', $exception);
        }
    }

    /**
     * Закрывает действие.
     *
     * @param int $actionId Действие.
     *
     * @return void
     *
     * @throws GameNotFoundException Если строки нет.
     * @throws GameInvalidException Если поле.
     */
    public function close(int $actionId): void
    {
        try {
            $this->records->update($actionId, ['open' => false]);
        } catch (RowNotFoundException $exception) {
            throw new GameNotFoundException('Game action was not found', $exception);
        } catch (
            FieldRequiredException
            | FieldInvalidException
            | MapInvalidException
            | RowWriteFailedException
            | UniqueConstraintException $exception
        ) {
            throw new GameInvalidException('Game action field is invalid', $exception);
        }
    }

    /**
     * Удаляет действие.
     *
     * @param int $actionId Действие.
     *
     * @return void
     *
     * @throws GameNotFoundException Если строки нет.
     * @throws GameInvalidException Если поле.
     */
    public function delete(int $actionId): void
    {
        try {
            $this->records->delete($actionId);
        } catch (RowNotFoundException $exception) {
            throw new GameNotFoundException('Game action was not found', $exception);
        } catch (
            FieldInvalidException
            | MapInvalidException
            | RowWriteFailedException
            | ReferenceConstraintException $exception
        ) {
            throw new GameInvalidException('Game action field is invalid', $exception);
        }
    }

    /**
     * Строки боя.
     *
     * @param int $battleId Бой.
     * @param string|null $kind Вид действия.
     *
     * @return list<array<string, mixed>> Строки.
     *
     * @throws GameInvalidException Если строка битая.
     */
    private function rowsOfBattle(int $battleId, ?string $kind): array
    {
        $conditions = [new FilterCondition('battle_id', '=', $battleId)];
        if ($kind !== null) {
            $conditions[] = new FilterCondition('kind', '=', $kind);
        }

        return $this->rows($conditions);
    }

    /**
     * Действие вместе с ударами.
     *
     * @param array<string, mixed> $action Колонки действия.
     *
     * @return array<string, mixed> Колонки действия и `strikes`.
     *
     * @throws GameInvalidException Если строка битая.
     */
    private function withStrikes(array $action): array
    {
        $actionId = $this->rowId($action);

        return array_merge($action, ['strikes' => $this->strikes->getList($actionId)]);
    }

    /**
     * Строки фильтра.
     *
     * @param array<int, FilterCondition> $conditions Условия.
     *
     * @return list<array<string, mixed>> Строки.
     *
     * @throws GameInvalidException Если строка битая.
     */
    private function rows(array $conditions): array
    {
        $result = $this->records->getList(new ListQuery(
            new FilterGroup('AND', $conditions),
            ['id' => 'ASC'],
            ListQuery::MAX_LIMIT,
            0,
            false,
            null,
        ));
        $rows = [];
        foreach ($result->rows() as $row) {
            if (!is_array($row)) {
                throw new GameInvalidException('Game action row is invalid');
            }

            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * Id строки.
     *
     * @param array<string, mixed> $row Строка.
     *
     * @return int Id.
     *
     * @throws GameInvalidException Если id битый.
     */
    private function rowId(array $row): int
    {
        if (!is_int($row['id'] ?? null)) {
            throw new GameInvalidException('Game action row is invalid');
        }

        return $row['id'];
    }
}
