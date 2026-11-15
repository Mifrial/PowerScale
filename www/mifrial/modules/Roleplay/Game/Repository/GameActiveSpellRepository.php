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
use Mifrial\Roleplay\Game\Dto\GameActiveSpellNew;
use Mifrial\Roleplay\Game\Dto\GameActiveSpellRecord;
use Mifrial\Roleplay\Game\Exception\GameBattleConflictException;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Game\Table\GameActiveSpellTable;

/**
 * Строки поддерживаемых заклинаний боя. Лист не пишет.
 */
final class GameActiveSpellRepository
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
        $this->records = $smartTableGateway->open(GameActiveSpellTable::class)->records();
    }

    /**
     * Вставляет строку.
     *
     * @param GameActiveSpellNew $new Поля новой строки.
     *
     * @return GameActiveSpellRecord Строка.
     *
     * @throws GameInvalidException Если поле.
     * @throws GameNotFoundException Если строки нет после вставки.
     */
    public function add(GameActiveSpellNew $new): GameActiveSpellRecord
    {
        try {
            $spellId = $this->records->add($new->fields());
        } catch (
            FieldRequiredException
            | FieldInvalidException
            | MapInvalidException
            | RowWriteFailedException $exception
        ) {
            throw new GameInvalidException('Game active spell field is invalid', $exception);
        }

        return $this->getById($spellId);
    }

    /**
     * Строка по id или null.
     *
     * @param int $spellId Id строки.
     *
     * @return GameActiveSpellRecord|null Строка или null.
     *
     * @throws GameInvalidException Если строка битая.
     */
    public function findById(int $spellId): ?GameActiveSpellRecord
    {
        $row = $this->records->getById($spellId);

        return is_array($row) ? GameActiveSpellRecord::fromNormalized($row) : null;
    }

    /**
     * Строка по id или отказ.
     *
     * @param int $spellId Id строки.
     *
     * @return GameActiveSpellRecord Строка.
     *
     * @throws GameNotFoundException Если строки нет.
     * @throws GameInvalidException Если строка битая.
     */
    public function getById(int $spellId): GameActiveSpellRecord
    {
        $spell = $this->findById($spellId);
        if (!$spell instanceof GameActiveSpellRecord) {
            throw new GameNotFoundException('Game active spell was not found');
        }

        return $spell;
    }

    /**
     * Строки боя.
     *
     * @param int $battleId Бой.
     *
     * @return array<int, GameActiveSpellRecord> Строки.
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
     * @return array<int, GameActiveSpellRecord> Строки.
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
     * Строка того же кастера и источника в бою.
     *
     * @param int $battleId Бой.
     * @param string $casterKind character или npc.
     * @param int $casterId Id кастера.
     * @param string $sourceKey Ключ источника.
     *
     * @return GameActiveSpellRecord|null Строка или null.
     *
     * @throws GameInvalidException Если строка битая.
     */
    public function findForSource(int $battleId, string $casterKind, int $casterId, string $sourceKey): ?GameActiveSpellRecord
    {
        $rows = $this->rows(new FilterGroup('AND', [
            new FilterCondition('battle_id', '=', $battleId),
            new FilterCondition('caster_kind', '=', $casterKind),
            new FilterCondition('caster_id', '=', $casterId),
            new FilterCondition('source_key', '=', $sourceKey),
        ]));

        return $rows[0] ?? null;
    }

    /**
     * Число активных волшебств кастера в бою.
     *
     * @param int $battleId Бой.
     * @param string $casterKind character или npc.
     * @param int $casterId Id кастера.
     *
     * @return int Строки.
     *
     * @throws GameInvalidException Если строка битая.
     */
    public function countForCaster(int $battleId, string $casterKind, int $casterId): int
    {
        return count($this->rows(new FilterGroup('AND', [
            new FilterCondition('battle_id', '=', $battleId),
            new FilterCondition('caster_kind', '=', $casterKind),
            new FilterCondition('caster_id', '=', $casterId),
        ])));
    }

    /**
     * Меняет мощь поддержания с проверкой версии строки.
     *
     * @param int $spellId Id строки.
     * @param int $expectedVersion Ожидаемая версия.
     * @param array{base: int, size: int} $sustainPower Новая мощь.
     *
     * @return GameActiveSpellRecord Строка после записи.
     *
     * @throws GameBattleConflictException Если версия другая.
     * @throws GameNotFoundException Если строки нет.
     * @throws GameInvalidException Если поле.
     */
    public function updateSustainPower(int $spellId, int $expectedVersion, array $sustainPower): GameActiveSpellRecord
    {
        $current = $this->assertVersion($spellId, $expectedVersion);
        $this->write($current->getId(), [
            'sustain_power' => $sustainPower,
            'state_version' => $expectedVersion + 1,
        ]);

        return $this->getById($spellId);
    }

    /**
     * Снимает строку с проверкой версии.
     *
     * @param int $spellId Id строки.
     * @param int $expectedVersion Ожидаемая версия.
     *
     * @return void
     *
     * @throws GameBattleConflictException Если версия другая.
     * @throws GameNotFoundException Если строки нет.
     * @throws GameInvalidException Если поле.
     */
    public function deleteWithVersion(int $spellId, int $expectedVersion): void
    {
        $this->assertVersion($spellId, $expectedVersion);
        $this->deleteRow($spellId);
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
        foreach ($this->findForBattle($battleId) as $spell) {
            $this->deleteRow($spell->getId());
        }
    }

    /**
     * Снимает строки выбывшего кастера.
     *
     * @param int $battleId Бой.
     * @param string $casterKind character или npc.
     * @param int $casterId Id кастера.
     *
     * @return void
     *
     * @throws GameInvalidException Если строка битая.
     */
    public function deleteForCaster(int $battleId, string $casterKind, int $casterId): void
    {
        $rows = $this->rows(new FilterGroup('AND', [
            new FilterCondition('battle_id', '=', $battleId),
            new FilterCondition('caster_kind', '=', $casterKind),
            new FilterCondition('caster_id', '=', $casterId),
        ]));
        foreach ($rows as $spell) {
            $this->deleteRow($spell->getId());
        }
    }

    /**
     * Строки по фильтру.
     *
     * @param FilterGroup $filter Фильтр.
     *
     * @return array<int, GameActiveSpellRecord> Строки.
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
            $rows[] = GameActiveSpellRecord::fromNormalized($row);
        }

        return $rows;
    }

    /**
     * Строка и сверка версии.
     *
     * @param int $spellId Id строки.
     * @param int $expectedVersion Ожидаемая версия.
     *
     * @return GameActiveSpellRecord Строка.
     *
     * @throws GameBattleConflictException Если версия другая.
     * @throws GameNotFoundException Если строки нет.
     * @throws GameInvalidException Если строка битая.
     */
    private function assertVersion(int $spellId, int $expectedVersion): GameActiveSpellRecord
    {
        $current = $this->getById($spellId);
        if ($current->getStateVersion() !== $expectedVersion) {
            throw new GameBattleConflictException($current->getStateVersion());
        }

        return $current;
    }

    /**
     * Пишет поля строки.
     *
     * @param int $spellId Id строки.
     * @param array<string, mixed> $fields Поля.
     *
     * @return void
     *
     * @throws GameInvalidException Если поле.
     */
    private function write(int $spellId, array $fields): void
    {
        try {
            $this->records->update($spellId, $fields);
        } catch (
            FieldRequiredException
            | FieldInvalidException
            | MapInvalidException
            | RowWriteFailedException $exception
        ) {
            throw new GameInvalidException('Game active spell field is invalid', $exception);
        }
    }

    /**
     * Снимает строку.
     *
     * @param int $spellId Id строки.
     *
     * @return void
     *
     * @throws GameNotFoundException Если строки нет.
     * @throws GameInvalidException Если поле.
     */
    private function deleteRow(int $spellId): void
    {
        try {
            $this->records->delete($spellId);
        } catch (RowNotFoundException $exception) {
            throw new GameNotFoundException('Game active spell was not found', $exception);
        } catch (RowWriteFailedException $exception) {
            throw new GameInvalidException('Game active spell field is invalid', $exception);
        }
    }
}
