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
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Game\Table\GameCheckTable;

/**
 * Строки проверки.
 */
final class GameCheckRepository
{
    private readonly IOpenedRecords $checkRecords;

    /**
     * Создаёт репозиторий.
     *
     * @param ISmartTableGateway $smartTableGateway Шлюз ST.
     *
     * @return void
     */
    public function __construct(ISmartTableGateway $smartTableGateway)
    {
        $this->checkRecords = $smartTableGateway->open(GameCheckTable::class)->records();
    }

    /**
     * Строка process или null.
     *
     * @param int $processId Process.
     *
     * @return array{ruleCode: string, asked: array{base: int, size: int}|null}|null Пара.
     *
     * @throws GameInvalidException Если строка битая.
     */
    public function findByProcess(int $processId): ?array
    {
        $result = $this->checkRecords->getList(new ListQuery(
            new FilterGroup('AND', [
                new FilterCondition('process_id', '=', $processId),
            ]),
            ['id' => 'ASC'],
            1,
            0,
            false,
            null,
        ));
        $rows = $result->rows();
        if ($rows === []) {
            return null;
        }

        $row = $rows[0];
        if (!is_array($row) || !is_string($row['rule_code'] ?? null)) {
            throw new GameInvalidException('Game check row is invalid');
        }

        $asked = null;
        if (is_int($row['asked_base'] ?? null) && is_int($row['asked_size'] ?? null)) {
            $asked = ['base' => $row['asked_base'], 'size' => $row['asked_size']];
        }

        return ['ruleCode' => $row['rule_code'], 'asked' => $asked];
    }

    /**
     * Пишет строку.
     *
     * @param array<string, mixed> $fields Колонки.
     *
     * @return void
     *
     * @throws GameNotFoundException Если сессии нет.
     * @throws GameInvalidException Если поле.
     */
    public function add(array $fields): void
    {
        try {
            $this->checkRecords->add($fields);
        } catch (ReferenceConstraintException $exception) {
            throw new GameNotFoundException('Game session was not found', $exception);
        } catch (
            FieldRequiredException
            | FieldInvalidException
            | MapInvalidException
            | RowWriteFailedException $exception
        ) {
            throw new GameInvalidException('Game check field is invalid', $exception);
        }
    }

    /**
     * Пишет итог уже лежащей строки.
     *
     * @param int $processId Process.
     * @param string $offer pending, accepted или declined.
     * @param array{difficulty: array{base: int, size: int}, roll: array{base: int, size: int}, success: bool, rating: int}|null $thrown Итог или null.
     *
     * @return void
     *
     * @throws GameNotFoundException Если строки нет.
     * @throws GameInvalidException Если поле.
     */
    public function storeOutcome(int $processId, string $offer, ?array $thrown): void
    {
        $fields = ['offer' => $offer];
        if ($thrown !== null) {
            $fields['difficulty_base'] = $thrown['difficulty']['base'];
            $fields['difficulty_size'] = $thrown['difficulty']['size'];
            $fields['success_base'] = $thrown['roll']['base'];
            $fields['success_size'] = $thrown['roll']['size'];
            $fields['passed'] = $thrown['success'];
            $fields['rating'] = $thrown['rating'];
        }

        $this->updateByProcess($processId, $fields);
    }

    /**
     * Снимает проверки сессии.
     *
     * @param int $sessionId Сессия.
     *
     * @return void
     *
     * @throws GameNotFoundException Если строку уже сняли.
     * @throws GameInvalidException Если поле.
     */
    public function deleteBySession(int $sessionId): void
    {
        $result = $this->checkRecords->getList(new ListQuery(
            new FilterGroup('AND', [
                new FilterCondition('session_id', '=', $sessionId),
            ]),
            ['id' => 'ASC'],
            ListQuery::MAX_LIMIT,
            0,
            false,
            null,
        ));
        foreach ($result->rows() as $row) {
            if (!is_array($row) || !is_int($row['id'] ?? null)) {
                throw new GameInvalidException('Game check row is invalid');
            }

            try {
                $this->checkRecords->delete($row['id']);
            } catch (RowNotFoundException $exception) {
                throw new GameNotFoundException('Game check was not found', $exception);
            }
        }
    }

    /**
     * Обновляет строку process.
     *
     * @param int $processId Process.
     * @param array<string, mixed> $fields Колонки.
     *
     * @return void
     *
     * @throws GameNotFoundException Если строки нет.
     * @throws GameInvalidException Если поле.
     */
    private function updateByProcess(int $processId, array $fields): void
    {
        $result = $this->checkRecords->getList(new ListQuery(
            new FilterGroup('AND', [
                new FilterCondition('process_id', '=', $processId),
            ]),
            ['id' => 'ASC'],
            1,
            0,
            false,
            null,
        ));
        $row = $result->rows()[0] ?? null;
        if (!is_array($row) || !is_int($row['id'] ?? null)) {
            throw new GameNotFoundException('Game check was not found');
        }

        try {
            $this->checkRecords->update($row['id'], $fields);
        } catch (RowNotFoundException $exception) {
            throw new GameNotFoundException('Game check was not found', $exception);
        } catch (
            FieldRequiredException
            | FieldInvalidException
            | MapInvalidException
            | RowWriteFailedException $exception
        ) {
            throw new GameInvalidException('Game check field is invalid', $exception);
        }
    }
}
