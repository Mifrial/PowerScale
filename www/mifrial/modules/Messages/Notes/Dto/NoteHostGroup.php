<?php

declare(strict_types=1);

namespace Mifrial\Messages\Notes\Dto;

use Mifrial\Core\Kernel\Value\DateTime;

/**
 * Сущность с заметками для левой колонки `/notes`.
 */
final class NoteHostGroup
{
    /**
     * Собирает группу.
     *
     * @param string $hostType Тип сущности.
     * @param int $hostId Id сущности.
     * @param int $count Число заметок.
     * @param DateTime $lastUpdatedAt Последнее обновление.
     *
     * @return void
     */
    public function __construct(
        private readonly string $hostType,
        private readonly int $hostId,
        private readonly int $count,
        private readonly DateTime $lastUpdatedAt,
    ) {
    }

    /**
     * Тип сущности.
     *
     * @return string Тип.
     */
    public function getHostType(): string
    {
        return $this->hostType;
    }

    /**
     * Id сущности.
     *
     * @return int Id.
     */
    public function getHostId(): int
    {
        return $this->hostId;
    }

    /**
     * Число заметок.
     *
     * @return int COUNT.
     */
    public function getCount(): int
    {
        return $this->count;
    }

    /**
     * Последнее обновление.
     *
     * @return DateTime Момент.
     */
    public function getLastUpdatedAt(): DateTime
    {
        return $this->lastUpdatedAt;
    }
}
