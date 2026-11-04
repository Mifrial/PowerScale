<?php

declare(strict_types=1);

namespace Mifrial\Core\File\Dto;

/**
 * Страница файлов и COUNT фильтра.
 */
final class FilePage
{
    /**
     * Собирает страницу.
     *
     * @param array<int, FileRecord> $records Строки.
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
     * @return array<int, FileRecord> Записи.
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
