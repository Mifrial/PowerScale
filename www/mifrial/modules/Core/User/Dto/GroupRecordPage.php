<?php

declare(strict_types=1);

namespace Mifrial\Core\User\Dto;

/**
 * Страница групп и COUNT фильтра.
 */
final class GroupRecordPage
{
    /**
     * Собирает страницу.
     *
     * @param array<int, GroupRecord> $records Строки.
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
     * @return array<int, GroupRecord> Группы.
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
