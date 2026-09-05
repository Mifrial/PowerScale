<?php

declare(strict_types=1);

namespace Mifrial\Core\User\Dto;

/**
 * Страница user_id членств и COUNT группы.
 */
final class MemberIdPage
{
    /**
     * Собирает страницу.
     *
     * @param array<int, int> $ids User id в порядке id членства.
     * @param int $total Число членств группы.
     *
     * @return void
     */
    public function __construct(
        private readonly array $ids,
        private readonly int $total,
    ) {
    }

    /**
     * user id страницы.
     *
     * @return array<int, int> Id учёток.
     */
    public function getIds(): array
    {
        return $this->ids;
    }

    /**
     * Полное число членств группы.
     *
     * @return int COUNT.
     */
    public function getTotal(): int
    {
        return $this->total;
    }
}
