<?php

declare(strict_types=1);

namespace Mifrial\Core\Logger\Dto;

/**
 * Страница записей журнала и COUNT фильтра.
 */
final class LogRecordPage
{
    /**
     * Собирает страницу.
     *
     * @param array<int, LogRecord> $records Строки.
     * @param int $total Число совпадений.
     *
     * @return void
     */
    public function __construct(
        private readonly array $records,
        private readonly int $total,
    ) {
    }

    /**
     * Строки страницы.
     *
     * @return array<int, LogRecord> Записи.
     */
    public function getRecords(): array
    {
        return $this->records;
    }

    /**
     * Полное число совпадений.
     *
     * @return int COUNT.
     */
    public function getTotal(): int
    {
        return $this->total;
    }
}
