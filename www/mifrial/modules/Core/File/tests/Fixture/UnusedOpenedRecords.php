<?php

declare(strict_types=1);

namespace Mifrial\Core\File\Tests\Fixture;

use LogicException;
use Mifrial\Core\SmartTable\Dto\AggregateQuery;
use Mifrial\Core\SmartTable\Dto\AggregateResult;
use Mifrial\Core\SmartTable\Dto\ListQuery;
use Mifrial\Core\SmartTable\Dto\ListResult;
use Mifrial\Core\SmartTable\Interface\Service\IOpenedRecords;

/**
 * Строки, к которым сервис не должен обращаться в отказных сценариях.
 */
final class UnusedOpenedRecords implements IOpenedRecords
{
    /**
     * Не используется.
     *
     * @param array<string, mixed> $values Поля.
     *
     * @return int Id.
     */
    public function add(array $values): int
    {
        throw new LogicException('Opened records must not be used');
    }

    /**
     * Не используется.
     *
     * @param array<int, array<string, mixed>> $rows Строки.
     *
     * @return array<int, int> Id.
     */
    public function addMany(array $rows): array
    {
        throw new LogicException('Opened records must not be used');
    }

    /**
     * Не используется.
     *
     * @param int $rowId Id.
     * @param array<string, mixed> $values Поля.
     *
     * @return void
     */
    public function update(int $rowId, array $values): void
    {
        throw new LogicException('Opened records must not be used');
    }

    /**
     * Не используется.
     *
     * @param int $rowId Id.
     *
     * @return void
     */
    public function delete(int $rowId): void
    {
        throw new LogicException('Opened records must not be used');
    }

    /**
     * Не используется.
     *
     * @param int $rowId Id.
     * @param int|null $cacheTtl TTL.
     *
     * @return array<string, mixed>|null Строка.
     */
    public function getById(int $rowId, ?int $cacheTtl = null): ?array
    {
        throw new LogicException('Opened records must not be used');
    }

    /**
     * Не используется.
     *
     * @param ListQuery $listQuery Запрос.
     * @param int|null $cacheTtl TTL.
     *
     * @return ListResult Результат.
     */
    public function getList(ListQuery $listQuery, ?int $cacheTtl = null): ListResult
    {
        throw new LogicException('Opened records must not be used');
    }

    /**
     * Не используется.
     *
     * @param ListQuery $listQuery Запрос.
     * @param int|null $cacheTtl TTL.
     *
     * @return array<string, mixed>|null Строка.
     */
    public function getUnique(ListQuery $listQuery, ?int $cacheTtl = null): ?array
    {
        throw new LogicException('Opened records must not be used');
    }

    /**
     * Не используется.
     *
     * @param ListQuery $listQuery Запрос.
     * @param int|null $cacheTtl TTL.
     *
     * @return array<string, mixed>|null Строка.
     */
    public function getFirst(ListQuery $listQuery, ?int $cacheTtl = null): ?array
    {
        throw new LogicException('Opened records must not be used');
    }

    /**
     * Не используется.
     *
     * @param AggregateQuery $aggregateQuery Запрос.
     * @param int|null $cacheTtl TTL.
     *
     * @return AggregateResult Результат.
     */
    public function aggregate(AggregateQuery $aggregateQuery, ?int $cacheTtl = null): AggregateResult
    {
        throw new LogicException('Opened records must not be used');
    }
}
