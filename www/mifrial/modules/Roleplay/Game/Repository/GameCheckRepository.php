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
     * @return array{ruleCode: string, mode: string, offer: string, asked: array{base: int, size: int}|null, targetType: string|null, targetId: int|null, proposal: array<string, mixed>|null, rolls: array<string, mixed>|null}|null Пара.
     *
     * @throws GameInvalidException Если строка битая.
     */
    public function findByProcess(int $processId): ?array
    {
        $row = $this->rows([new FilterCondition('process_id', '=', $processId)], 1)[0] ?? null;
        if ($row === null) {
            return null;
        }

        $ruleCode = $row['rule_code'] ?? null;
        if (!is_string($ruleCode)) {
            throw new GameInvalidException('Game check row is invalid');
        }

        return [
            'ruleCode' => $ruleCode,
            'mode' => is_string($row['mode'] ?? null) ? $row['mode'] : 'solo',
            'offer' => is_string($row['offer'] ?? null) ? $row['offer'] : 'accepted',
            'asked' => $this->askedOf($row),
            'targetType' => is_string($row['target_type'] ?? null) ? $row['target_type'] : null,
            'targetId' => is_int($row['target_id'] ?? null) ? $row['target_id'] : null,
            'proposal' => is_array($row['proposal'] ?? null) ? $row['proposal'] : null,
            'rolls' => is_array($row['rolls'] ?? null) ? $row['rolls'] : null,
        ];
    }

    /**
     * Ожидающие предложения сессии или участника.
     *
     * @param int $sessionId Сессия.
     * @param array{type: string, id: int}|null $entity Участник или null.
     *
     * @return array<int, array<string, mixed>> Строки.
     *
     * @throws GameInvalidException Если строка битая.
     */
    public function findPending(int $sessionId, ?array $entity = null): array
    {
        $conditions = [
            new FilterCondition('session_id', '=', $sessionId),
            new FilterCondition('offer', '=', 'pending'),
            new FilterCondition('mode', '=', 'pairwise'),
        ];
        if ($entity !== null) {
            $conditions[] = new FilterCondition('target_type', '=', $entity['type']);
            $conditions[] = new FilterCondition('target_id', '=', $entity['id']);
        }

        $rows = [];
        foreach ($this->rows($conditions, ListQuery::MAX_LIMIT) as $row) {
            $rows[] = $this->pendingRow($row);
        }

        return $rows;
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
     * @param string $offer Статус pending, accepted, declined или cancelled.
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
     * Пишет предложение.
     *
     * @param int $processId Process.
     * @param array<string, mixed> $proposal Предложение.
     *
     * @return void
     */
    public function storeProposal(int $processId, array $proposal): void
    {
        $this->updateByProcess($processId, ['proposal' => $proposal]);
    }

    /**
     * Пишет совместные броски.
     *
     * @param int $processId Process.
     * @param array<string, mixed> $rolls Броски.
     *
     * @return void
     */
    public function storeRolls(int $processId, array $rolls): void
    {
        $this->updateByProcess($processId, ['rolls' => $rolls]);
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
        $rows = $this->rows([new FilterCondition('session_id', '=', $sessionId)], ListQuery::MAX_LIMIT);
        foreach ($rows as $row) {
            $rowId = $row['id'] ?? null;
            if (!is_int($rowId)) {
                throw new GameInvalidException('Game check row is invalid');
            }

            try {
                $this->checkRecords->delete($rowId);
            } catch (RowNotFoundException $exception) {
                throw new GameNotFoundException('Game check was not found', $exception);
            }
        }
    }

    /**
     * Строка ожидающего предложения.
     *
     * @param array<string, mixed> $row Строка ST.
     *
     * @return array<string, mixed> Пара.
     *
     * @throws GameInvalidException Если process_id битый.
     */
    private function pendingRow(array $row): array
    {
        $processId = $row['process_id'] ?? null;
        if (!is_int($processId)) {
            throw new GameInvalidException('Game check row is invalid');
        }

        return [
            'processId' => $processId,
            'ruleCode' => is_string($row['rule_code'] ?? null) ? $row['rule_code'] : '',
            'asked' => $this->askedOf($row),
            'targetType' => is_string($row['target_type'] ?? null) ? $row['target_type'] : null,
            'targetId' => is_int($row['target_id'] ?? null) ? $row['target_id'] : null,
            'proposal' => is_array($row['proposal'] ?? null) ? $row['proposal'] : null,
        ];
    }

    /**
     * Размер пары asked или null.
     *
     * @param array<string, mixed> $row Строка ST.
     *
     * @return array{base: int, size: int}|null Пара или null.
     */
    private function askedOf(array $row): ?array
    {
        $base = $row['asked_base'] ?? null;
        $size = $row['asked_size'] ?? null;
        if (!is_int($base) || !is_int($size)) {
            return null;
        }

        return ['base' => $base, 'size' => $size];
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
        $row = $this->rows([new FilterCondition('process_id', '=', $processId)], 1)[0] ?? null;
        $rowId = $row['id'] ?? null;
        if (!is_int($rowId)) {
            throw new GameNotFoundException('Game check was not found');
        }

        try {
            $this->checkRecords->update($rowId, $fields);
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

    /**
     * Страница строк по фильтру.
     *
     * @param array<int, FilterGroup|FilterCondition> $conditions Условия через AND.
     * @param int $limit Предел страницы.
     *
     * @return array<int, array<string, mixed>> Строки.
     */
    private function rows(array $conditions, int $limit): array
    {
        $result = $this->checkRecords->getList(new ListQuery(
            new FilterGroup('AND', $conditions),
            ['id' => 'ASC'],
            $limit,
            0,
            false,
            null,
        ));

        return $result->rows();
    }
}
