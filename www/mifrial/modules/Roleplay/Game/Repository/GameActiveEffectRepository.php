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
use Mifrial\Roleplay\Game\Dto\GameActiveEffectNew;
use Mifrial\Roleplay\Game\Dto\GameActiveEffectRecord;
use Mifrial\Roleplay\Game\Exception\GameBattleConflictException;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Game\Table\GameActiveEffectTable;

/**
 * Строки наложенных длительных эффектов боя. Лист не пишет.
 */
final class GameActiveEffectRepository
{
    private readonly IOpenedRecords $records;

    /**
     * Создаёт репозиторий.
     *
     * @param ISmartTableGateway $smartTableGateway Шлюз ST.
     *
     * @return void
     */
    public function __construct(ISmartTableGateway $smartTableGateway)
    {
        $this->records = $smartTableGateway->open(GameActiveEffectTable::class)->records();
    }

    /**
     * Вставляет строку.
     *
     * @param GameActiveEffectNew $new Поля новой строки.
     *
     * @return GameActiveEffectRecord Строка.
     *
     * @throws GameInvalidException Если поле.
     * @throws GameNotFoundException Если строки нет после вставки.
     */
    public function add(GameActiveEffectNew $new): GameActiveEffectRecord
    {
        try {
            $effectId = $this->records->add($new->fields());
        } catch (
            FieldRequiredException
            | FieldInvalidException
            | MapInvalidException
            | RowWriteFailedException $exception
        ) {
            throw new GameInvalidException('Game active effect field is invalid', $exception);
        }

        return $this->getById($effectId);
    }

    /**
     * Строка по id или null.
     *
     * @param int $effectId Id строки.
     *
     * @return GameActiveEffectRecord|null Строка или null.
     *
     * @throws GameInvalidException Если строка битая.
     */
    public function findById(int $effectId): ?GameActiveEffectRecord
    {
        $row = $this->records->getById($effectId);

        return is_array($row) ? GameActiveEffectRecord::fromNormalized($row) : null;
    }

    /**
     * Строка по id или отказ.
     *
     * @param int $effectId Id строки.
     *
     * @return GameActiveEffectRecord Строка.
     *
     * @throws GameNotFoundException Если строки нет.
     * @throws GameInvalidException Если строка битая.
     */
    public function getById(int $effectId): GameActiveEffectRecord
    {
        $effect = $this->findById($effectId);
        if (!$effect instanceof GameActiveEffectRecord) {
            throw new GameNotFoundException('Game active effect was not found');
        }

        return $effect;
    }

    /**
     * Строки боя.
     *
     * @param int $battleId Бой.
     *
     * @return array<int, GameActiveEffectRecord> Строки.
     *
     * @throws GameInvalidException Если строка битая.
     */
    public function findForBattle(int $battleId): array
    {
        return $this->rows(new FilterGroup('AND', [
            new FilterCondition('battle_id', '=', $battleId),
        ]));
    }

    /**
     * Строки текущей сессии.
     *
     * @param int $sessionId Сессия.
     *
     * @return array<int, GameActiveEffectRecord> Строки.
     *
     * @throws GameInvalidException Если строка битая.
     */
    public function findForSession(int $sessionId): array
    {
        return $this->rows(new FilterGroup('AND', [
            new FilterCondition('session_id', '=', $sessionId),
        ]));
    }

    /**
     * Строки эффектов на цели в бою.
     *
     * @param int $battleId Бой.
     * @param string $targetKind character или npc.
     * @param int $targetId Id цели.
     *
     * @return array<int, GameActiveEffectRecord> Строки.
     *
     * @throws GameInvalidException Если строка битая.
     */
    public function findForTarget(int $battleId, string $targetKind, int $targetId): array
    {
        return $this->rows(new FilterGroup('AND', [
            new FilterCondition('battle_id', '=', $battleId),
            new FilterCondition('target_kind', '=', $targetKind),
            new FilterCondition('target_id', '=', $targetId),
        ]));
    }

    /**
     * Меняет остаток тиков с проверкой версии строки.
     *
     * @param int $effectId Id строки.
     * @param int $expectedVersion Ожидаемая версия.
     * @param int $turnsLeft Новый остаток.
     *
     * @return GameActiveEffectRecord Строка после записи.
     *
     * @throws GameBattleConflictException Если версия другая.
     * @throws GameNotFoundException Если строки нет.
     * @throws GameInvalidException Если поле.
     */
    public function updateTurnsLeft(int $effectId, int $expectedVersion, int $turnsLeft): GameActiveEffectRecord
    {
        $this->assertVersion($effectId, $expectedVersion);
        $this->write($effectId, [
            'turns_left' => $turnsLeft,
            'state_version' => $expectedVersion + 1,
        ]);

        return $this->getById($effectId);
    }

    /**
     * Снимает строку с проверкой версии.
     *
     * @param int $effectId Id строки.
     * @param int $expectedVersion Ожидаемая версия.
     *
     * @return void
     *
     * @throws GameBattleConflictException Если версия другая.
     * @throws GameNotFoundException Если строки нет.
     * @throws GameInvalidException Если поле.
     */
    public function deleteWithVersion(int $effectId, int $expectedVersion): void
    {
        $this->assertVersion($effectId, $expectedVersion);
        $this->deleteRow($effectId);
    }

    /**
     * Снимает все строки боя.
     *
     * @param int $battleId Бой.
     *
     * @return void
     *
     * @throws GameInvalidException Если строка битая.
     */
    public function deleteForBattle(int $battleId): void
    {
        foreach ($this->findForBattle($battleId) as $effect) {
            $this->deleteRow($effect->getId());
        }
    }

    /**
     * Снимает строки сессии.
     *
     * @param int $sessionId Сессия.
     *
     * @return void
     *
     * @throws GameInvalidException Если строка битая.
     */
    public function deleteForSession(int $sessionId): void
    {
        foreach ($this->findForSession($sessionId) as $effect) {
            $this->deleteRow($effect->getId());
        }
    }

    /**
     * Снимает эффекты на цели в бою.
     *
     * @param int $battleId Бой.
     * @param string $targetKind character или npc.
     * @param int $targetId Id цели.
     *
     * @return void
     *
     * @throws GameInvalidException Если строка битая.
     */
    public function deleteForTarget(int $battleId, string $targetKind, int $targetId): void
    {
        foreach ($this->findForTarget($battleId, $targetKind, $targetId) as $effect) {
            $this->deleteRow($effect->getId());
        }
    }

    /**
     * Строки по фильтру.
     *
     * @param FilterGroup $filter Фильтр.
     *
     * @return array<int, GameActiveEffectRecord> Строки.
     *
     * @throws GameInvalidException Если строка битая.
     */
    private function rows(FilterGroup $filter): array
    {
        $result = $this->records->getList(new ListQuery(
            $filter,
            ['id' => 'ASC'],
            ListQuery::MAX_LIMIT,
            0,
            false,
            null,
        ));

        $rows = [];
        foreach ($result->rows() as $row) {
            $rows[] = GameActiveEffectRecord::fromNormalized($row);
        }

        return $rows;
    }

    /**
     * Строка и сверка версии.
     *
     * @param int $effectId Id строки.
     * @param int $expectedVersion Ожидаемая версия.
     *
     * @return GameActiveEffectRecord Строка.
     *
     * @throws GameBattleConflictException Если версия другая.
     * @throws GameNotFoundException Если строки нет.
     * @throws GameInvalidException Если строка битая.
     */
    private function assertVersion(int $effectId, int $expectedVersion): GameActiveEffectRecord
    {
        $current = $this->getById($effectId);
        if ($current->getStateVersion() !== $expectedVersion) {
            throw new GameBattleConflictException($current->getStateVersion());
        }

        return $current;
    }

    /**
     * Пишет поля строки.
     *
     * @param int $effectId Id строки.
     * @param array<string, mixed> $fields Поля.
     *
     * @return void
     *
     * @throws GameInvalidException Если поле.
     */
    private function write(int $effectId, array $fields): void
    {
        try {
            $this->records->update($effectId, $fields);
        } catch (
            FieldRequiredException
            | FieldInvalidException
            | MapInvalidException
            | RowWriteFailedException $exception
        ) {
            throw new GameInvalidException('Game active effect field is invalid', $exception);
        }
    }

    /**
     * Снимает строку.
     *
     * @param int $effectId Id строки.
     *
     * @return void
     *
     * @throws GameNotFoundException Если строки нет.
     * @throws GameInvalidException Если поле.
     */
    private function deleteRow(int $effectId): void
    {
        try {
            $this->records->delete($effectId);
        } catch (RowNotFoundException $exception) {
            throw new GameNotFoundException('Game active effect was not found', $exception);
        } catch (RowWriteFailedException $exception) {
            throw new GameInvalidException('Game active effect field is invalid', $exception);
        }
    }
}
