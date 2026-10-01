<?php

declare(strict_types=1);

namespace Mifrial\Core\Auth\Tests;

use Mifrial\Core\SmartTable\Dto\AggregateQuery;
use Mifrial\Core\SmartTable\Dto\AggregateResult;
use Mifrial\Core\SmartTable\Dto\ListQuery;
use Mifrial\Core\SmartTable\Dto\ListResult;
use Mifrial\Core\SmartTable\Interface\Service\IOpenedRecords;
use RuntimeException;

/**
 * Бросает на N-й мутации add, update или delete.
 */
final class FailingOpenedRecords implements IOpenedRecords
{
    private int $mutations = 0;

    /**
     * Создаёт обёртку.
     *
     * @param IOpenedRecords $openedRecords Настоящие строки.
     * @param string $operation add, update или delete.
     * @param int $failAt Номер мутации с 1.
     *
     * @return void
     */
    public function __construct(
        private readonly IOpenedRecords $openedRecords,
        private readonly string $operation,
        private readonly int $failAt,
    ) {
    }

    /**
     * @param array<string, mixed> $values Значения.
     *
     * @return int Id.
     */
    public function add(array $values): int
    {
        $this->failOrCount('add');

        return $this->openedRecords->add($values);
    }

    /**
     * @param array<int, array<string, mixed>> $rows Ряды.
     *
     * @return array<int, int> Id.
     */
    public function addMany(array $rows): array
    {
        return $this->openedRecords->addMany($rows);
    }

    /**
     * @param int $rowId Id.
     * @param array<string, mixed> $values Поля.
     *
     * @return void
     */
    public function update(int $rowId, array $values): void
    {
        $this->failOrCount('update');
        $this->openedRecords->update($rowId, $values);
    }

    /**
     * @param int $rowId Id.
     *
     * @return void
     */
    public function delete(int $rowId): void
    {
        $this->failOrCount('delete');
        $this->openedRecords->delete($rowId);
    }

    /**
     * @param int $rowId Id.
     * @param int|null $cacheTtl TTL.
     *
     * @return array<string, mixed>|null Строка.
     */
    public function getById(int $rowId, ?int $cacheTtl = null): ?array
    {
        return $this->openedRecords->getById($rowId, $cacheTtl);
    }

    /**
     * @param ListQuery $listQuery Запрос.
     * @param int|null $cacheTtl TTL.
     *
     * @return ListResult Страница.
     */
    public function getList(ListQuery $listQuery, ?int $cacheTtl = null): ListResult
    {
        return $this->openedRecords->getList($listQuery, $cacheTtl);
    }

    /**
     * @param ListQuery $listQuery Запрос.
     * @param int|null $cacheTtl TTL.
     *
     * @return array<string, mixed>|null Строка.
     */
    public function getUnique(ListQuery $listQuery, ?int $cacheTtl = null): ?array
    {
        return $this->openedRecords->getUnique($listQuery, $cacheTtl);
    }

    /**
     * @param ListQuery $listQuery Запрос.
     * @param int|null $cacheTtl TTL.
     *
     * @return array<string, mixed>|null Строка.
     */
    public function getFirst(ListQuery $listQuery, ?int $cacheTtl = null): ?array
    {
        return $this->openedRecords->getFirst($listQuery, $cacheTtl);
    }

    /**
     * @param AggregateQuery $aggregateQuery Запрос.
     * @param int|null $cacheTtl TTL.
     *
     * @return AggregateResult Агрегат.
     */
    public function aggregate(AggregateQuery $aggregateQuery, ?int $cacheTtl = null): AggregateResult
    {
        return $this->openedRecords->aggregate($aggregateQuery, $cacheTtl);
    }

    /**
     * Считает мутацию и бросает на заданном номере.
     *
     * @param string $operation Имя операции.
     *
     * @return void
     */
    private function failOrCount(string $operation): void
    {
        if ($operation !== $this->operation) {
            return;
        }

        $this->mutations++;
        if ($this->mutations === $this->failAt) {
            throw new RuntimeException('injected write failure');
        }
    }
}
