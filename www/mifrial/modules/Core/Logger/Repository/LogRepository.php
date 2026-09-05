<?php

declare(strict_types=1);

namespace Mifrial\Core\Logger\Repository;

use Mifrial\Core\SmartTable\Dto\ListQuery;
use Mifrial\Core\SmartTable\Interface\Service\IOpenedRecords;

/**
 * Строки `log`.
 */
final class LogRepository
{
    /**
     * Создаёт репозиторий.
     *
     * @param IOpenedRecords $logRecords Строки.
     *
     * @return void
     */
    public function __construct(
        private readonly IOpenedRecords $logRecords,
    ) {
    }

    /**
     * Добавляет запись.
     *
     * @param array<string, mixed> $fields Поля строки.
     *
     * @return int Id.
     */
    public function add(array $fields): int
    {
        return $this->logRecords->add($fields);
    }

    /**
     * Последние строки для тестов.
     *
     * @param int $limit Лимит.
     *
     * @return array<int, array<string, mixed>> Строки.
     */
    public function listRecent(int $limit): array
    {
        return $this->logRecords->getList(ListQuery::fromOptions([
            'sort' => ['id' => 'desc'],
            'limit' => $limit,
        ]))->rows();
    }
}
