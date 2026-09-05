<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Service\Query;

use Mifrial\Core\SmartTable\Dto\ListQuery;
use Mifrial\Core\SmartTable\Exception\Map\MapInvalidException;

/**
 * Проверки пачки addMany до SQL.
 */
final class InsertBatch
{
    /**
     * Отвергает пустой, не-list и слишком длинный набор рядов.
     *
     * @param array<int|string, mixed> $rows Сырой вход.
     *
     * @return void
     *
     * @throws MapInvalidException Если форма пачки неверна.
     */
    public function assertRows(array $rows): void
    {
        if (!array_is_list($rows) || $rows === []) {
            throw new MapInvalidException('addMany requires 1..10000 row maps');
        }

        if (count($rows) > ListQuery::MAX_LIMIT) {
            throw new MapInvalidException('addMany requires 1..10000 row maps');
        }

        foreach ($rows as $row) {
            $this->assertRowMap($row);
        }
    }

    /**
     * Ряд — ассоциативная карта, не список значений.
     *
     * @param mixed $row Сырой ряд.
     *
     * @return void
     *
     * @throws MapInvalidException Если ряд не карта.
     */
    private function assertRowMap(mixed $row): void
    {
        if (!is_array($row)) {
            throw new MapInvalidException('addMany row must be a map');
        }

        if ($row !== [] && array_is_list($row)) {
            throw new MapInvalidException('addMany row must be a map');
        }
    }
}
