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
use Mifrial\Core\SmartTable\Interface\Service\IOpenedRecords;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Roleplay\Game\Dto\GameBattleRecord;
use Mifrial\Roleplay\Game\Exception\GameBattleConflictException;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Game\Table\GameBattleTable;

/**
 * Строки открытого боя и его состава.
 */
final class GameBattleRepository
{
    public readonly GameBattleParticipantRepository $participants;

    private readonly IOpenedRecords $battleRecords;

    /**
     * Создаёт репозиторий.
     *
     * @param ISmartTableGateway $smartTableGateway Шлюз ST.
     *
     * @return void
     */
    public function __construct(ISmartTableGateway $smartTableGateway)
    {
        $this->battleRecords = $smartTableGateway->open(GameBattleTable::class)->records();
        $this->participants = new GameBattleParticipantRepository($smartTableGateway);
    }

    /**
     * Открывает бой с версией 1.
     *
     * @param int $sessionId Сессия.
     *
     * @return GameBattleRecord Строка.
     *
     * @throws GameNotFoundException Если сессии нет.
     * @throws GameInvalidException Если поле.
     */
    public function add(int $sessionId): GameBattleRecord
    {
        try {
            $id = $this->battleRecords->add([
                'session_id' => $sessionId,
                'state_version' => 1,
            ]);
        } catch (ReferenceConstraintException $exception) {
            throw new GameNotFoundException('Game session was not found', $exception);
        } catch (
            FieldRequiredException
            | FieldInvalidException
            | MapInvalidException
            | RowWriteFailedException $exception
        ) {
            throw new GameInvalidException('Game battle field is invalid', $exception);
        }

        return $this->require($id);
    }

    /**
     * Бой по id или null.
     *
     * @param int $battleId Бой.
     *
     * @return GameBattleRecord|null Строка.
     *
     * @throws GameInvalidException Если строка битая.
     */
    public function find(int $battleId): ?GameBattleRecord
    {
        $row = $this->battleRecords->getById($battleId);
        if ($row === null) {
            return null;
        }

        return GameBattleRecord::fromNormalized($row);
    }

    /**
     * Id боёв сессии.
     *
     * @param int $sessionId Сессия.
     *
     * @return list<int> Id.
     *
     * @throws GameInvalidException Если строка битая.
     */
    public function findIdsBySession(int $sessionId): array
    {
        $result = $this->battleRecords->getList(new ListQuery(
            new FilterGroup('AND', [
                new FilterCondition('session_id', '=', $sessionId),
            ]),
            ['id' => 'ASC'],
            ListQuery::MAX_LIMIT,
            0,
            false,
            null,
        ));
        $ids = [];
        foreach ($result->rows() as $row) {
            $ids[] = $this->rowId($row);
        }

        return $ids;
    }

    /**
     * Увеличивает версию, если она совпала.
     *
     * @param int $battleId Бой.
     * @param int $expectedVersion Ожидаемая версия.
     *
     * @return GameBattleRecord После записи.
     *
     * @throws GameBattleConflictException Если версия другая.
     * @throws GameNotFoundException Если боя нет.
     * @throws GameInvalidException Если поле.
     */
    public function advanceVersion(int $battleId, int $expectedVersion): GameBattleRecord
    {
        $this->assertVersion($battleId, $expectedVersion);
        $this->write($battleId, ['state_version' => $expectedVersion + 1]);

        return $this->require($battleId);
    }

    /**
     * Пишет карту ожидания и сдвигает версию одним update.
     *
     * @param int $battleId Бой.
     * @param int $expectedVersion Ожидаемая версия.
     * @param array<string, mixed> $pending Карта.
     * @param array<int, array<string, mixed>>|null $marks Метки или null, чтобы не трогать.
     *
     * @return GameBattleRecord Строка.
     *
     * @throws GameBattleConflictException Если версия другая.
     * @throws GameNotFoundException Если боя нет.
     * @throws GameInvalidException Если поле.
     */
    public function replacePending(
        int $battleId,
        int $expectedVersion,
        array $pending,
        ?array $marks = null,
    ): GameBattleRecord {
        $this->assertVersion($battleId, $expectedVersion);
        $fields = ['pending_action_effects' => $pending, 'state_version' => $expectedVersion + 1];
        if ($marks !== null) {
            $fields['marks'] = $marks;
        }

        $this->write($battleId, $fields);

        return $this->require($battleId);
    }

    /**
     * Пишет курсор, карту долга и ожидание одним CAS.
     *
     * @param int $battleId Бой.
     * @param int $expectedVersion Ожидаемая версия.
     * @param array<string, mixed>|null $pending Карта ожидания или null, чтобы не трогать.
     * @param array<string, mixed>|null $debt Карта долга или null.
     * @param int|null $activeIndex Курсор или null.
     * @param int|null $round Раунд или null.
     * @param array<string, mixed>|null $cycle Цикл концентрации или null.
     * @param array<int, array<string, mixed>>|null $marks Метки или null, чтобы не трогать.
     *
     * @return GameBattleRecord Строка.
     *
     * @throws GameBattleConflictException Если версия другая.
     * @throws GameNotFoundException Если боя нет.
     * @throws GameInvalidException Если поле.
     */
    public function replaceTracked(
        int $battleId,
        int $expectedVersion,
        ?array $pending,
        ?array $debt,
        ?int $activeIndex,
        ?int $round,
        ?array $cycle = null,
        ?array $marks = null,
    ): GameBattleRecord {
        $this->assertVersion($battleId, $expectedVersion);
        $this->write(
            $battleId,
            $this->trackedFields($expectedVersion, $pending, $debt, $activeIndex, $round, $cycle, $marks),
        );

        return $this->require($battleId);
    }

    /**
     * Снимает слот долга без сверки версии.
     *
     * @param int $battleId Бой.
     * @param array<string, mixed> $debt Карта.
     *
     * @return void
     *
     * @throws GameNotFoundException Если боя нет.
     * @throws GameInvalidException Если поле.
     */
    public function replaceActionDebt(int $battleId, array $debt): void
    {
        $this->require($battleId);
        $this->write($battleId, ['action_debt' => $debt]);
    }

    /**
     * Пишет порядок уже открытого боя.
     *
     * @param int $battleId Бой.
     * @param array<int, array<string, mixed>> $turnOrder Список.
     * @param int|null $activeIndex Индекс хода или null.
     * @param int|null $round Раунд или null.
     *
     * @return void
     *
     * @throws GameNotFoundException Если боя нет.
     * @throws GameInvalidException Если поле.
     */
    public function replaceTurnOrder(
        int $battleId,
        array $turnOrder,
        ?int $activeIndex = null,
        ?int $round = null,
    ): void {
        $this->require($battleId);
        $fields = ['turn_order' => $turnOrder];
        if ($activeIndex !== null) {
            $fields['active_index'] = $activeIndex;
        }

        if ($round !== null) {
            $fields['round'] = $round;
        }

        $this->write($battleId, $fields);
    }

    /**
     * Снимает состав и строку боя.
     *
     * @param int $battleId Бой.
     *
     * @return void
     *
     * @throws GameNotFoundException Если строки нет.
     * @throws GameInvalidException Если поле.
     */
    public function delete(int $battleId): void
    {
        $this->participants->deleteByBattle($battleId);
        $this->deleteRow($battleId);
    }

    /**
     * Бой или отказ.
     *
     * @param int $battleId Бой.
     *
     * @return GameBattleRecord Строка.
     *
     * @throws GameNotFoundException Если нет.
     * @throws GameInvalidException Если строка битая.
     */
    private function require(int $battleId): GameBattleRecord
    {
        $battle = $this->find($battleId);
        if (!$battle instanceof GameBattleRecord) {
            throw new GameNotFoundException('Game battle was not found');
        }

        return $battle;
    }

    /**
     * Сверяет версию боя с ожидаемой.
     *
     * @param int $battleId Бой.
     * @param int $expectedVersion Ожидаемая версия.
     *
     * @return void
     *
     * @throws GameBattleConflictException Если версия другая.
     * @throws GameNotFoundException Если боя нет.
     * @throws GameInvalidException Если строка битая.
     */
    private function assertVersion(int $battleId, int $expectedVersion): void
    {
        $current = $this->require($battleId);
        if ($current->getStateVersion() !== $expectedVersion) {
            throw new GameBattleConflictException($current->getStateVersion());
        }
    }

    /**
     * Пишет поля строки боя.
     *
     * @param int $battleId Бой.
     * @param array<string, mixed> $fields Поля.
     *
     * @return void
     *
     * @throws GameInvalidException Если поле.
     */
    private function write(int $battleId, array $fields): void
    {
        try {
            $this->battleRecords->update($battleId, $fields);
        } catch (
            FieldRequiredException
            | FieldInvalidException
            | MapInvalidException
            | RowWriteFailedException $exception
        ) {
            throw new GameInvalidException('Game battle field is invalid', $exception);
        }
    }

    /**
     * Поля CAS-записи без null-значений.
     *
     * @param int $expectedVersion Ожидаемая версия.
     * @param array<string, mixed>|null $pending Карта ожидания.
     * @param array<string, mixed>|null $debt Карта долга.
     * @param int|null $activeIndex Курсор.
     * @param int|null $round Раунд.
     * @param array<string, mixed>|null $cycle Цикл концентрации.
     * @param array<int, array<string, mixed>>|null $marks Метки.
     *
     * @return array<string, mixed> Поля.
     */
    private function trackedFields(
        int $expectedVersion,
        ?array $pending,
        ?array $debt,
        ?int $activeIndex,
        ?int $round,
        ?array $cycle,
        ?array $marks,
    ): array {
        $fields = ['state_version' => $expectedVersion + 1];
        $optional = [
            'pending_action_effects' => $pending,
            'action_debt' => $debt,
            'active_index' => $activeIndex,
            'round' => $round,
            'concentration_cycle' => $cycle,
            'marks' => $marks,
        ];
        foreach ($optional as $key => $value) {
            if ($value !== null) {
                $fields[$key] = $value;
            }
        }

        return $fields;
    }

    /**
     * Id одной строки списка.
     *
     * @param mixed $row Строка ST.
     *
     * @return int Id.
     *
     * @throws GameInvalidException Если id нет.
     */
    private function rowId(mixed $row): int
    {
        if (!is_array($row) || !is_int($row['id'] ?? null)) {
            throw new GameInvalidException('Game battle row is invalid');
        }

        return $row['id'];
    }

    /**
     * Снимает строку боя.
     *
     * @param int $rowId Id.
     *
     * @return void
     *
     * @throws GameNotFoundException Если строки нет.
     * @throws GameInvalidException Если поле.
     */
    private function deleteRow(int $rowId): void
    {
        try {
            $this->battleRecords->delete($rowId);
        } catch (RowNotFoundException $exception) {
            throw new GameNotFoundException('Game battle was not found', $exception);
        } catch (RowWriteFailedException $exception) {
            throw new GameInvalidException('Game battle field is invalid', $exception);
        }
    }
}
